<?php

/**
 * Unit tests for the brand per app: storage and validation, the resolution order on a
 * page of a branded app, the logo layer, and the endpoints.
 *
 * The resolution tests run the REAL GroupThemingService inside a CssInjectionService
 * whose stylesheet emitters are captured, so the set a page renders in is read off the
 * token layer it actually emits.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/per-app-theming/spec.md
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\AppBrandController;
use OCA\Thematiq\Service\AppBrandLogoStore;
use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\ImageSniffer;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ITempManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Tests for the brand per app.
 */
class AppBrandServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = ['token_set' => 'rijkshuisstijl', 'disabled_apps' => '["spreed"]'];

	/**
	 * In-memory app data files.
	 *
	 * @var array<string, string>
	 */
	private array $files = [];

	/**
	 * The preview set of the signed-in admin, or null.
	 *
	 * @var string|null
	 */
	private ?string $preview = null;

	/**
	 * Whether a user is signed in.
	 *
	 * @var bool
	 */
	private bool $signedIn = true;

	/**
	 * The in-memory config.
	 *
	 * @return IConfig The config.
	 */
	private function config(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);

		return $config;
	}//end config()

	/**
	 * The token set double: four sets exist.
	 *
	 * @return TokenSetService The double.
	 */
	private function tokenSets(): TokenSetService {
		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			fn (string $id): bool => in_array($id, ['rijkshuisstijl', 'gemeente-a-huisstijl', 'kennisbank', 'amsterdam'], true)
		);
		$tokenSets->method('getPublicCatalogue')->willReturn([]);

		return $tokenSets;
	}//end tokenSets()

	/**
	 * The brand service on in-memory storage.
	 *
	 * @return AppBrandService The service.
	 */
	private function brands(): AppBrandService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturnCallback(fn (string $id): bool => in_array($id, ['collectives', 'files', 'spreed', 'settings'], true));
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $name): bool => isset($this->files[$name]));
		$folder->method('newFile')->willReturnCallback(
			function (string $name, $content = null): ISimpleFile {
				$this->files[$name] = (string)$content;

				return $this->createMock(ISimpleFile::class);
			}
		);
		$folder->method('getFile')->willReturnCallback(
			function (string $name): ISimpleFile {
				$file = $this->createMock(ISimpleFile::class);
				$file->method('getContent')->willReturnCallback(fn (): string => $this->files[$name]);
				$file->method('delete')->willReturnCallback(
					function () use ($name): void {
						unset($this->files[$name]);
					}
				);

				return $file;
			}
		);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(fn (string $route, array $params): string => '/brand/' . $params['appId'] . '/' . $params['size']);

		return new AppBrandService(
			$this->config(),
			$appManager,
			new AppThemingService($this->config(), $appManager),
			$this->tokenSets(),
			new AppBrandLogoStore($appData, new ImageSniffer()),
			$urls
		);
	}//end brands()

	/**
	 * The injection service with the real group resolution; emitted stylesheets are captured.
	 *
	 * @param array<int, string> $styles The emitted stylesheet files.
	 * @param array<int, string> $inline The emitted inline styles.
	 *
	 * @return CssInjectionService The service.
	 */
	private function injection(array &$styles, array &$inline): CssInjectionService {
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('piet');
		$session->method('getUser')->willReturnCallback(fn (): ?IUser => ($this->signedIn === true ? $user : null));
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn(['gemeente-a']);
		$preview = $this->createMock(ThemePreviewService::class);
		$preview->method('resolveEffectiveTokenSet')->willReturnCallback(
			fn (IUserSession $s, string $active): array => ['tokenSet' => ($this->preview ?? $active), 'previewActive' => ($this->preview !== null), 'expiresAt' => null]
		);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$this->app['group_token_sets'] = '[{"group":"gemeente-a","tokenSet":"gemeente-a-huisstijl"}]';
		$groupTheming = new GroupThemingService($this->config(), $groupManager, $session, $this->tokenSets(), $preview, $cacheFactory);

		$designSystem = $this->createMock(DesignSystemService::class);
		$designSystem->method('getTokenSetMeta')->willReturn(['design_system' => 'nldesign']);
		$designSystem->method('getDesignSystem')->willReturn(['id' => 'nldesign', 'name' => 'NL', 'description' => '', 'stylesheets' => ['systems/nldesign/theme']]);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkTo')->willReturnCallback(fn (string $app, string $file): string => '/apps/' . $app . '/' . $file);
		$urls->method('imagePath')->willReturn('/core/img/logo/logo.svg');
		$stock = $this->createMock(StockTokensService::class);
		$stock->method('getCss')->willReturn(null);

		$repo = $this->createMock(IAppManager::class);
		$repo->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$runtimeFiles = new RuntimeFileLocator(
			$repo,
			new DirectoryRuntimeFileStore(sys_get_temp_dir() . '/thematiq-brand-' . bin2hex(random_bytes(4))),
			$urls,
			$this->createMock(ITempManager::class)
		);
		$logger = $this->createMock(LoggerInterface::class);

		$service = $this->getMockBuilder(CssInjectionService::class)
			->setConstructorArgs(
				[
					$this->config(),
					$designSystem,
					$this->createMock(CustomCssService::class),
					$this->createMock(FontService::class),
					$urls,
					$groupTheming,
					$this->createMock(ThemePreviewBannerService::class),
					$logger,
					$stock,
					$runtimeFiles,
					new LogoLayerService($this->config(), $urls, $logger, $runtimeFiles),
					$this->brands(),
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();
		$service->method('emitStyle')->willReturnCallback(
			function (string $file) use (&$styles): void {
				$styles[] = $file;
			}
		);
		$service->method('emitInlineStyle')->willReturnCallback(
			function (string $css) use (&$inline): void {
				$inline[] = $css;
			}
		);

		return $service;
	}//end injection()

	/**
	 * The token set a render emitted.
	 *
	 * @param string $context The render context.
	 * @param string|null $appId The rendered app.
	 *
	 * @return array{0: string|null, 1: array<int, string>} The set and the inline styles.
	 */
	private function render(string $context, ?string $appId): array {
		$styles = [];
		$inline = [];
		$this->injection(styles: $styles, inline: $inline)->inject(context: $context, appId: $appId);
		foreach ($styles as $file) {
			if (preg_match('#^tokens/([a-z0-9-]+)$#', $file, $match) === 1) {
				return [$match[1], $inline];
			}
		}

		return [null, $inline];
	}//end render()

	/**
	 * Brand Collectives with a large and a small logo.
	 *
	 * @return void
	 */
	private function brandCollectives(): void {
		$brands = $this->brands();
		$brands->setBrand(appId: 'collectives', tokenSet: 'kennisbank');
		$brands->storeLogo(appId: 'collectives', size: 'large', bytes: "\x89PNG\r\n\x1a\nlarge");
		$brands->storeLogo(appId: 'collectives', size: 'small', bytes: '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
	}//end brandCollectives()

	/**
	 * A branded app renders in its set for a group member; another app renders in the group's set.
	 *
	 * @return void
	 */
	public function testBrandedAppLooksTheSameForEveryGroup(): void {
		$this->brandCollectives();

		$this->assertSame('kennisbank', $this->render(context: 'user', appId: 'collectives')[0]);
		$this->assertSame('gemeente-a-huisstijl', $this->render(context: 'user', appId: 'files')[0]);
	}//end testBrandedAppLooksTheSameForEveryGroup()

	/**
	 * An admin preview still wins on a branded app.
	 *
	 * @return void
	 */
	public function testPreviewStillWins(): void {
		$this->brandCollectives();
		$this->preview = 'amsterdam';

		[$set, $inline] = $this->render(context: 'user', appId: 'collectives');
		$this->assertSame('amsterdam', $set);
		$this->assertStringNotContainsString('/brand/collectives', implode('', $inline), 'The brand logo goes with the brand set.');
	}//end testPreviewStillWins()

	/**
	 * Sessionless pages and the login page keep the instance default.
	 *
	 * @return void
	 */
	public function testSessionlessAndLoginKeepTheDefault(): void {
		$this->brandCollectives();

		$this->signedIn = false;
		$this->assertSame('rijkshuisstijl', $this->render(context: 'login', appId: null)[0]);
		$this->assertSame('rijkshuisstijl', $this->render(context: 'public', appId: 'collectives')[0]);
	}//end testSessionlessAndLoginKeepTheDefault()

	/**
	 * The logo layer carries the large logo, and the small one under the narrow-screen breakpoint.
	 *
	 * @return void
	 */
	public function testLogoLayerUsesLargeAndSmall(): void {
		$this->brandCollectives();

		$css = implode('', $this->render(context: 'user', appId: 'collectives')[1]);

		$this->assertStringContainsString(':root{--nldesign-logo-url:url(/brand/collectives/large)', $css);
		$this->assertStringContainsString('@media (max-width:1024px){:root{--nldesign-logo-url:url(/brand/collectives/small)}}', $css);

		$brands = json_decode($this->app['app_brands'], true);
		$brands['collectives']['logoSmall'] = null;
		$this->app['app_brands'] = json_encode($brands);
		$this->assertStringNotContainsString('@media', implode('', $this->render(context: 'user', appId: 'collectives')[1]));
	}//end testLogoLayerUsesLargeAndSmall()

	/**
	 * Protected, excluded and uninstalled apps, and unknown sets, are refused.
	 *
	 * @return void
	 */
	public function testRefusals(): void {
		$cases = [
			['settings', 'kennisbank', AppBrandException::PROTECTED],
			['spreed', 'kennisbank', AppBrandException::EXCLUDED],
			['nope', 'kennisbank', AppBrandException::NOT_INSTALLED],
			['collectives', 'missing', AppBrandException::UNKNOWN_SET],
		];
		foreach ($cases as [$appId, $set, $reason]) {
			try {
				$this->brands()->setBrand(appId: $appId, tokenSet: $set);
				$this->fail('Refused: ' . $appId);
			} catch (AppBrandException $e) {
				$this->assertSame($reason, $e->reason);
			}
		}

		$this->assertArrayNotHasKey('app_brands', $this->app);
	}//end testRefusals()

	/**
	 * Logo checks: SVG with script, too large, and not an image are refused.
	 *
	 * @return void
	 */
	public function testLogoChecks(): void {
		$this->brands()->setBrand(appId: 'collectives', tokenSet: 'kennisbank');
		$cases = [
			['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 422],
			[str_repeat('x', AppBrandLogoStore::MAX_BYTES + 1), 413],
			['GIF89a', 422],
		];
		foreach ($cases as [$bytes, $status]) {
			try {
				$this->brands()->storeLogo(appId: 'collectives', size: 'large', bytes: $bytes);
				$this->fail('Refused with ' . $status);
			} catch (AppBrandException $e) {
				$this->assertSame($status, $e->getCode());
			}
		}

		$this->assertSame([], $this->files);
	}//end testLogoChecks()

	/**
	 * A brand whose app was removed is ignored at resolution and flagged stale.
	 *
	 * @return void
	 */
	public function testStaleBrandIsIgnored(): void {
		$this->app['app_brands'] = '{"removedapp":{"tokenSet":"kennisbank"}}';

		$this->assertNull($this->brands()->brandFor(appId: 'removedapp'));
		$this->assertTrue($this->brands()->getBrands()['removedapp']['stale']);
	}//end testStaleBrandIsIgnored()

	/**
	 * The settings endpoints are admin settings; the logo is for signed-in users; a protected app gets 422.
	 *
	 * @return void
	 */
	public function testEndpoints(): void {
		foreach (['index', 'save', 'remove', 'uploadLogo'] as $method) {
			$attributes = (new ReflectionMethod(AppBrandController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()[0]);
		}

		$this->assertCount(1, (new ReflectionMethod(AppBrandController::class, 'logo'))->getAttributes(NoAdminRequired::class));

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$appTheming = $this->createMock(AppThemingService::class);
		$controller = new AppBrandController('thematiq', $this->createMock(IRequest::class), $this->brands(), $appTheming, $this->tokenSets(), $l10n);
		$refused = $controller->save(appId: 'settings', tokenSet: 'kennisbank');
		$this->assertSame(422, $refused->getStatus());
		$this->assertStringContainsString('settings pages always follow the house style', $refused->getData()['error']);
		$this->assertSame(200, $controller->save(appId: 'collectives', tokenSet: 'kennisbank')->getStatus());
		$this->assertSame(404, $controller->logo(appId: 'collectives', size: 'large')->getStatus());
	}//end testEndpoints()
}//end class
