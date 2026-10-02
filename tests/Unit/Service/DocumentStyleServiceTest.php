<?php

/**
 * Unit tests for the document house style profile, its assets and its endpoints.
 *
 * TokenSetPreviewService, DesignSystemService and ContrastService are the real classes
 * reading the shipped token sets, so the colours and logos in the profile are the ones a
 * document would really get.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\DocumentStyleController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DocumentAssetService;
use OCA\Thematiq\Service\DocumentStyleService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UserTokenSetResolver;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Tests for the document house style.
 */
class DocumentStyleServiceTest extends TestCase {

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = ['token_set' => 'rijkshuisstijl'];

	/**
	 * In-memory app data files.
	 *
	 * @var array<string, string>
	 */
	private array $files = [];

	/**
	 * The uploaded font manifest.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $fontManifest = [];

	/**
	 * Group membership by user.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $groups = ['bea' => ['gemeente-utrecht'], 'kees' => []];

	/**
	 * The signed-in user id.
	 *
	 * @var string|null
	 */
	private ?string $sessionUid = null;

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
	 * The asset service on in-memory app data.
	 *
	 * @return DocumentAssetService The service.
	 */
	private function assets(): DocumentAssetService {
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

		return new DocumentAssetService($appData, $this->config());
	}//end assets()

	/**
	 * The profile service with the real preview, design system and contrast classes.
	 *
	 * @param string|null $tokenDir An app directory to read the token sets from; the repository when null.
	 *
	 * @return DocumentStyleService The service.
	 */
	private function service(?string $tokenDir = null): DocumentStyleService {
		$repo = $this->createMock(IAppManager::class);
		$repo->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$tokenApp = $this->createMock(IAppManager::class);
		$tokenApp->method('getAppPath')->willReturn(($tokenDir ?? \dirname(__DIR__, 3)));

		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturn(true);
		$tokenSets->method('getAvailableTokenSets')->willReturn(
			[['id' => 'rijkshuisstijl', 'name' => 'Rijkshuisstijl'], ['id' => 'utrecht', 'name' => 'Gemeente Utrecht']]
		);
		$groupTheming = $this->createMock(GroupThemingService::class);
		$groupTheming->method('getMapping')->willReturn([['group' => 'gemeente-utrecht', 'tokenSet' => 'utrecht']]);
		$groupTheming->method('resolveTokenSetForRequest')->willReturnCallback(
			fn (): string => ($this->groups[(string)$this->sessionUid] === ['gemeente-utrecht'] ? 'utrecht' : $this->app['token_set'])
		);
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(fn (string $uid): ?IUser => $this->user(uid: $uid));
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturnCallback(fn (IUser $user): array => ($this->groups[$user->getUID()] ?? []));

		$resolver = new UserTokenSetResolver($groupTheming, $tokenSets, $groupManager, $users, $this->session(), $this->config());

		$fonts = $this->createMock(FontService::class);
		$fonts->method('getManifest')->willReturnCallback(fn (): array => $this->fontManifest);
		$email = $this->createMock(EmailThemingService::class);
		$email->method('getFooterConfig')->willReturn(
			['orgName' => 'Gemeente Voorbeeld', 'accessibilityUrl' => 'https://voorbeeld.nl/toegankelijkheid', 'privacyUrl' => 'https://voorbeeld.nl/privacy']
		);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(fn (string $route, array $params = []): string => '/' . $route . '?' . http_build_query($params));
		$urls->method('imagePath')->willReturnCallback(fn (string $app, string $file): string => '/apps/' . $app . '/img/' . $file);

		return new DocumentStyleService(
			$resolver,
			$tokenSets,
			new TokenSetPreviewService($tokenApp),
			new DesignSystemService($repo, $this->config()),
			$fonts,
			$email,
			$this->assets(),
			new ContrastService(),
			$urls
		);
	}//end service()

	/**
	 * A user double.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser|null The user, null for an unknown id.
	 */
	private function user(string $uid): ?IUser {
		if (isset($this->groups[$uid]) === false) {
			return null;
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}//end user()

	/**
	 * The session double.
	 *
	 * @return IUserSession The session.
	 */
	private function session(): IUserSession {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(fn (): ?IUser => ($this->sessionUid === null ? null : $this->user(uid: $this->sessionUid)));

		return $session;
	}//end session()

	/**
	 * The instance default profile: the default set's colours, logo, fonts and footer.
	 *
	 * @return void
	 */
	public function testInstanceDefaultProfile(): void {
		$profile = $this->service()->forUser(uid: null);

		$this->assertSame(['id' => 'rijkshuisstijl', 'name' => 'Rijkshuisstijl'], $profile['tokenSet']);
		$this->assertSame('Gemeente Voorbeeld', $profile['organisation']);
		$this->assertSame('#154273', strtolower((string)$profile['colours']['primary']));
		$this->assertSame('#ffffff', $profile['colours']['background']);
		$this->assertSame(['url' => '/apps/thematiq/img/logos/rijkshuisstijl.svg', 'mime' => 'image/svg+xml'], $profile['logo']);
		$this->assertNull($profile['cover']);
		$this->assertSame(['Gemeente Voorbeeld'], $profile['footer']['lines']);
		$this->assertSame('https://voorbeeld.nl/privacy', $profile['footer']['privacyUrl']);
		$this->assertSame([], $profile['warnings'], 'Rijkshuisstijl text meets 4.5:1 on white.');
	}//end testInstanceDefaultProfile()

	/**
	 * A group-mapped user gets the group's set, in-process and for the signed-in user alike.
	 *
	 * @return void
	 */
	public function testGroupMappedUserGetsTheGroupSet(): void {
		$inProcess = $this->service()->forUser(uid: 'bea');
		$this->assertSame('utrecht', $inProcess['tokenSet']['id']);
		$this->assertSame('/apps/thematiq/img/logos/utrecht.svg', $inProcess['logo']['url']);

		$this->sessionUid = 'bea';
		$controller = new DocumentStyleController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->assets(), $this->session(), $this->createMock(IL10N::class));
		$this->assertSame($inProcess['colours'], $controller->show()->getData()['colours'], 'HTTP and in-process return the same values.');
		$this->assertNotSame($this->service()->forUser(uid: 'kees')['colours']['primary'], $inProcess['colours']['primary']);
	}//end testGroupMappedUserGetsTheGroupSet()

	/**
	 * A set without a logo and without uploaded fonts.
	 *
	 * @return void
	 */
	public function testSetWithoutLogoAndSystemFonts(): void {
		$this->app['token_set'] = 'westervoort';

		$profile = $this->service()->forUser(uid: null);

		$this->assertNull($profile['logo']);
		$this->assertNull($profile['fonts']['heading']['url']);
		$this->assertNull($profile['fonts']['body']['url']);
		$this->assertNotSame('', (string)$profile['fonts']['body']['family']);
		$this->assertStringNotContainsString('var(', (string)$profile['fonts']['heading']['family']);
	}//end testSetWithoutLogoAndSystemFonts()

	/**
	 * An uploaded font names its family and its file URL.
	 *
	 * @return void
	 */
	public function testUploadedFontsCarryTheirUrl(): void {
		$this->fontManifest = ['custom-corporate' => ['name' => 'Corporate', 'role' => 'heading']];

		$heading = $this->service()->forUser(uid: null)['fonts']['heading'];

		$this->assertSame('Corporate', $heading['family']);
		$this->assertSame('/thematiq.font.serve?id=custom-corporate', $heading['url']);
	}//end testUploadedFontsCarryTheirUrl()

	/**
	 * A document logo replaces the set logo in the profile only; the footer line is added.
	 *
	 * @return void
	 */
	public function testDocumentLogoAndFooterLine(): void {
		$this->assets()->store(kind: 'logo', bytes: "\x89PNG\r\n\x1a\n" . str_repeat('0', 32));
		$this->assets()->setFooterLine(line: 'Postbus 1, 1234 AB Voorbeeld');

		$profile = $this->service()->forUser(uid: null);

		$this->assertSame('image/png', $profile['logo']['mime']);
		$this->assertStringStartsWith('/thematiq.documentStyle.asset?kind=logo', $profile['logo']['url']);
		$this->assertSame(['Gemeente Voorbeeld', 'Postbus 1, 1234 AB Voorbeeld'], $profile['footer']['lines']);
		$this->assertSame('rijkshuisstijl', $this->app['token_set'], 'The web header logo is untouched.');
	}//end testDocumentLogoAndFooterLine()

	/**
	 * A set whose text fails 4.5:1 on the paper carries the warning.
	 *
	 * @return void
	 */
	public function testPoorContrastCarriesAWarning(): void {
		$dir = sys_get_temp_dir() . '/thematiq-doc-' . uniqid();
		mkdir($dir . '/css/tokens', 0777, true);
		mkdir($dir . '/css/systems/nldesign', 0777, true);
		copy(\dirname(__DIR__, 3) . '/css/systems/nldesign/defaults.css', $dir . '/css/systems/nldesign/defaults.css');
		file_put_contents($dir . '/css/tokens/grey.css', ":root {\n\t--nldesign-color-text: #bbbbbb;\n}\n");
		file_put_contents($dir . '/token-sets.json', '[]');
		$this->app['token_set'] = 'grey';

		$this->assertSame('text-contrast', $this->service(tokenDir: $dir)->forUser(uid: null)['warnings'][0]['code']);
		exec('rm -rf ' . escapeshellarg($dir));
	}//end testPoorContrastCarriesAWarning()

	/**
	 * Uploads: an SVG with script and a file over 2 MB are refused; a clean SVG is kept.
	 *
	 * @return void
	 */
	public function testUploadChecks(): void {
		$cases = [
			['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 422],
			['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', 422],
			["\x89PNG\r\n\x1a\n" . str_repeat('0', DocumentAssetService::MAX_BYTES), 413],
			['GIF89a', 422],
		];
		foreach ($cases as [$bytes, $code]) {
			try {
				$this->assets()->store(kind: 'cover', bytes: $bytes);
				$this->fail('Refused with ' . $code);
			} catch (RuntimeException $e) {
				$this->assertSame($code, $e->getCode());
			}
		}

		$this->assertSame([], $this->files);
		$this->assets()->store(kind: 'cover', bytes: '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
		$this->assertSame('image/svg+xml', $this->assets()->getAssets()['cover']['mime']);
		$this->assertArrayHasKey('cover.svg', $this->files);
	}//end testUploadChecks()

	/**
	 * The asset endpoint serves an uploaded image and answers 404 for a missing image or an
	 * unknown kind. (Its headers need a server container to read, so they are checked live.)
	 *
	 * @return void
	 */
	public function testAssetEndpointServesTheImage(): void {
		$controller = new DocumentStyleController('thematiq', $this->createMock(IRequest::class), $this->service(), $this->assets(), $this->session(), $this->createMock(IL10N::class));
		$this->assertSame(404, $controller->asset(kind: 'logo')->getStatus());
		$this->assertSame(404, $controller->asset(kind: 'nope')->getStatus());

		$png = "\x89PNG\r\n\x1a\n" . str_repeat('0', 16);
		$this->assets()->store(kind: 'logo', bytes: $png);
		$response = $controller->asset(kind: 'logo');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($png, $response->render());
	}//end testAssetEndpointServesTheImage()

	/**
	 * Reads need a session; the settings endpoints are admin settings.
	 *
	 * @return void
	 */
	public function testEndpointAttributes(): void {
		foreach (['show', 'asset'] as $method) {
			$reflection = new ReflectionMethod(DocumentStyleController::class, $method);
			$this->assertCount(1, $reflection->getAttributes(NoAdminRequired::class));
			$this->assertCount(0, $reflection->getAttributes(PublicPage::class), 'No session, no profile.');
		}

		foreach (['settings', 'saveFooter', 'upload', 'remove'] as $method) {
			$attributes = (new ReflectionMethod(DocumentStyleController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()[0]);
		}
	}//end testEndpointAttributes()
}//end class
