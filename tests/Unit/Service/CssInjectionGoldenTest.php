<?php

/**
 * Golden test for the stylesheet cascade a page receives and the manifest the admin panel reads.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\AppMenuService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\HeaderUserService;
use OCA\Thematiq\Service\InternalScopesService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\LayoutOptionValues;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\SearchPlaceholderService;
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Throwable;

/**
 * Renders seeded pages over every shipped token set and records what `inject()` emits, in
 * order, together with the stylesheet manifest before and after the render. The seeds vary
 * the design system, dark variants, Marianne, the Nextcloud version, runtime files (uploaded
 * sets, dark variants, overrides), custom CSS, fonts, the layout options, the uploaded and
 * per-app logos, an anonymous or signed-in request with group mappings and a preview, the
 * render context and the `themed_contexts` gate, and a failure in each guarded layer.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 */
class CssInjectionGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/css-injection.golden.json';

	private const CONTEXTS = ['user', 'login', 'guest', 'public', 'error', 'other'];

	private const CUSTOM_SET = 'custom-golden';

	/**
	 * The app config of the current case.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * The ordered record of the current case.
	 *
	 * @var array<int, string>
	 */
	private array $log = [];

	/**
	 * The temporary store of the current case.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Every case renders and lists what was recorded.
	 *
	 * @return void
	 */
	public function testCascadeMatchesTheGoldenRecording(): void {
		$sets = [];
		foreach (json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/token-sets.json'), true) as $entry) {
			$sets[] = (string)$entry['id'];
		}

		sort($sets);
		$sets[] = self::CUSTOM_SET;
		$sets[] = 'missing-set';

		$actual = [];
		foreach ($sets as $index => $set) {
			$actual['set:' . $set] = $this->runCase(seed: (1000 + $index), set: $set, plain: true);
		}

		$special = ['nextcloud', 'summer-breeze', 'hoog-contrast', 'lasuite', 'frankendesk', 'cunningham', self::CUSTOM_SET, 'missing-set'];
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$pool = ($random->getInt(0, 1) === 1) ? $special : $sets;
			$actual['seed:' . $seed] = $this->runCase(seed: $seed, set: $pool[$random->getInt(0, (count($pool) - 1))], plain: false);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], $name);
		}
	}//end testCascadeMatchesTheGoldenRecording()

	/**
	 * One seeded page: the manifest on a fresh service, the render, then the manifest again.
	 *
	 * @param int $seed The seed.
	 * @param string $set The token set the instance defaults to.
	 * @param bool $plain Whether to keep every option at its default.
	 *
	 * @return string The record as JSON text.
	 */
	private function runCase(int $seed, string $set, bool $plain): string {
		$random = new Randomizer(new Mt19937($seed));
		$pick = fn (array $options) => $options[$random->getInt(0, (count($options) - 1))];
		$chance = fn (int $outOf): bool => ($plain === false && $random->getInt(1, $outOf) === 1);
		$this->log = [];
		$this->dir = sys_get_temp_dir() . '/thematiq-css-golden-' . bin2hex(random_bytes(4));
		$this->app = ['token_set' => $set];
		if ($plain === false) {
			$this->seedConfig(pick: $pick, chance: $chance);
		}

		$store = new DirectoryRuntimeFileStore($this->dir);
		$this->seedStore(store: $store, chance: $chance, set: $set);
		$failing = $pick(['', '', '', '', '', '', 'brand', 'scopes', 'custom-css', 'banner', 'header', 'menu', 'search', 'fonts', 'stock']);
		if ($plain === true) {
			$failing = '';
		}

		$service = $this->service(
			case: [
				'random' => $random,
				'plain' => $plain,
				'failing' => $failing,
				'store' => $store,
				'signedIn' => ($plain === true || $random->getInt(0, 1) === 1),
				'preview' => $chance(6) ? $pick(['amsterdam', 'lasuite', 'nextcloud', self::CUSTOM_SET]) : null,
				'brand' => $chance(3) ? $pick(['denhaag', 'lasuite', 'nextcloud', 'hoog-contrast', self::CUSTOM_SET]) : null,
				'brandLogo' => $chance(2),
				'collaborators' => ($plain === false && $random->getInt(0, 3) !== 0),
			]
		);
		$context = $plain ? 'user' : $pick(self::CONTEXTS);
		$appId = $plain ? null : $pick([null, 'files', 'collectives', '']);
		$record = ['failing' => $failing, 'context' => $context, 'appId' => $appId];
		$record['themed'] = '';
		foreach (self::CONTEXTS as $name) {
			$record['themed'] .= ($service->isContextThemed(context: $name) === true) ? '1' : '0';
		}

		$record['manifestBefore'] = $this->manifest(service: $service, set: $set);
		$service->inject(context: $context, appId: $appId);
		$record['inject'] = $this->log;
		$this->log = [];
		$other = $plain ? $set : $pick(['amsterdam', 'lasuite', 'nextcloud', 'hoog-contrast', 'summer-breeze', $set]);
		$record['manifestAfter'] = $this->manifest(service: $service, set: $other);
		$record['afterLog'] = $this->log;
		$this->removeDir(path: $this->dir);

		return str_replace(
			['/apps/thematiq/css/', '/route/thematiq.runtimeFile.serve/name=css%2F'],
			['~/', '@/'],
			(string)json_encode($record, (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION))
		);
	}//end runCase()

	/**
	 * Seeds the app config of a varied case.
	 *
	 * @param callable $pick Picks one of a list.
	 * @param callable $chance True one time in N.
	 *
	 * @return void
	 */
	private function seedConfig(callable $pick, callable $chance): void {
		$optional = [
			'dark_variants' => ['0', '1', 'x'],
			'marianne_enabled' => ['0', '1'],
			'hide_slogan' => ['0', '1'],
			'show_menu_labels' => ['0', '1'],
			'primary_drives_components' => ['0', '1'],
			'themed_contexts' => ['[]', '["user"]', '["login","public"]', '["guest","error","user"]', 'not json', '{"a":"user"}', '[""]'],
			LayoutOptionsService::WORKPLACE_LAYOUT_KEY => ['', 'default', 'light', 'odd'],
			LayoutOptionsService::BRAND_STRIPE_KEY => ['', '0', '1'],
			LayoutOptionValues::NAVIGATION_WIDTH_KEY => ['', '240', '480', '100'],
			LayoutOptionValues::NAVIGATION_ACTIVE_STYLE_KEY => ['', 'default', 'soft'],
			LayoutOptionValues::BRAND_STRIPE_PLACEMENT_KEY => ['', 'header', 'login', 'header-and-login'],
			LayoutOptionValues::LOGIN_WATERMARK_KEY => ['', '0', '1'],
			LayoutOptionValues::HEADER_STYLE_KEY => ['', 'default', 'workplace'],
			'version' => ['31.0.0', '32.0.1', '33.0.0', '34.0.0'],
			'logoMime' => ['', 'image/svg+xml'],
			'logoheaderMime' => ['', 'image/png'],
			'installed_version' => ['1.2.3', '0.9.0-beta'],
		];
		foreach ($optional as $key => $values) {
			if ($chance(2) === true) {
				$this->app[$key] = $pick($values);
			}
		}

		if ($chance(3) === true) {
			$this->app['group_token_sets'] = (string)json_encode(
				[
					['group' => 'gemeente-a', 'tokenSet' => $pick(['amsterdam', 'lasuite', 'nextcloud', self::CUSTOM_SET, 'gone-set'])],
					['group' => 'staff', 'tokenSet' => $pick(['denhaag', 'hoog-contrast', 'summer-breeze'])],
				]
			);
		}
	}//end seedConfig()

	/**
	 * Seeds the runtime files of a case: the uploaded set, dark variants and overrides.
	 *
	 * @param DirectoryRuntimeFileStore $store The store.
	 * @param callable $chance True one time in N.
	 * @param string $set The default set.
	 *
	 * @return void
	 */
	private function seedStore(DirectoryRuntimeFileStore $store, callable $chance, string $set): void {
		$this->app['custom_token_sets'] = (string)json_encode(
			[self::CUSTOM_SET => ['id' => self::CUSTOM_SET, 'name' => 'Golden', 'design_system' => ($chance(2) ? 'none' : 'nldesign')]]
		);
		$store->write(name: 'css/tokens/' . self::CUSTOM_SET . '.css', content: ":root {\n  --nldesign-color-primary: #123456;\n}\n");
		if ($chance(2) === true) {
			$store->write(name: 'css/tokens/dark/' . self::CUSTOM_SET . '.css', content: ":root {\n  --nldesign-color-primary: #abcdef;\n}\n");
		}

		if ($chance(4) === true) {
			$store->write(name: 'css/tokens/' . $set . '.css', content: ":root {\n  --nldesign-color-primary: #654321;\n}\n");
		}

		if ($chance(4) === true) {
			$store->write(name: 'css/tokens/dark/' . $set . '.css', content: ":root {\n  --nldesign-color-primary: #fedcba;\n}\n");
		}

		foreach (['nldesign', 'none'] as $system) {
			if ($chance(2) === true) {
				$store->write(
					name: 'css/' . CustomOverridesService::fileFor(tokenSet: $set, designSystemId: $system) . '.css',
					content: ":root {\n  --nldesign-component-button-primary-background-color: #010203;\n  --nldesign-color-primary: #0a0b0c;\n}\n"
				);
			}
		}
	}//end seedStore()

	/**
	 * The service of one case, with its stylesheet emitters and collaborators recorded.
	 *
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return CssInjectionService The service.
	 */
	private function service(array $case): CssInjectionService {
		$random = $case['random'];
		$failing = $case['failing'];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('getSystemValueString')->willReturnCallback(fn (string $key, string $default = '') => ($this->app[$key] ?? $default));

		$repo = $this->createMock(IAppManager::class);
		$repo->method('getAppPath')->willReturn(dirname(__DIR__, 3));
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkTo')->willReturnCallback(fn (string $app, string $file): string => '/apps/' . $app . '/' . $file);
		$urls->method('linkToRoute')->willReturnCallback(
			fn (string $route, array $args = []): string => '/route/' . $route . ($args === [] ? '' : '/' . http_build_query($args))
		);
		$urls->method('imagePath')->willReturnCallback(fn (string $app, string $file): string => '/img/' . $app . '/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(fn (string $url): string => 'https://cloud.example' . $url);
		$runtimeFiles = new RuntimeFileLocator($repo, $case['store'], $urls, $this->createMock(ITempManager::class));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->log[] = 'warning:' . $message . '|' . ($context['layer'] ?? '') . '|' . $this->describe(error: ($context['exception'] ?? null));
			}
		);

		$customCss = $this->createMock(CustomCssService::class);
		$customCss->method('isEnabled')->willReturnCallback(fn (): bool => ($failing === 'custom-css') ? throw new RuntimeException('custom css') : true);
		$customCss->method('hasContent')->willReturn(($case['plain'] === false && $random->getInt(0, 1) === 1));
		$fonts = $this->createMock(FontService::class);
		$hasFonts = ($case['plain'] === false && $random->getInt(0, 1) === 1);
		$fonts->method('hasFonts')->willReturnCallback(fn (): bool => ($failing === 'fonts') ? throw new RuntimeException('fonts') : $hasFonts);
		$fonts->method('getRevision')->willReturn($random->getInt(1, 9));
		$stock = $this->createMock(StockTokensService::class);
		$stockCss = ($random->getInt(0, 1) === 1) ? ":root {\n  --nldesign-color-primary: #0082c9;\n}\n" : null;
		$stock->method('getCss')->willReturnCallback(fn (): ?string => ($failing === 'stock') ? throw new RuntimeException('stock') : $stockCss);

		$banner = $this->createMock(ThemePreviewBannerService::class);
		$banner->method('inject')->willReturnCallback(
			function (string $tokenSet, array $tokenSetMeta) use ($failing): void {
				if ($failing === 'banner') {
					throw new RuntimeException('banner');
				}

				$this->log[] = 'banner:' . $tokenSet . '|' . json_encode($tokenSetMeta);
			}
		);

		$scopes = null;
		if ($failing === 'scopes') {
			$scopes = $this->createMock(InternalScopesService::class);
			$scopes->method('forSet')->willThrowException(new RuntimeException('scopes'));
		}

		$service = $this->getMockBuilder(CssInjectionService::class)
			->setConstructorArgs(
				[
					$config,
					new DesignSystemService($repo, $config, $case['store']),
					$customCss,
					$fonts,
					$urls,
					$this->groupTheming(config: $config, case: $case),
					$banner,
					$logger,
					$stock,
					$runtimeFiles,
					new LogoLayerService($config, $urls, $logger, $runtimeFiles),
					$this->brands(case: $case),
					$scopes,
					null,
					...$this->collaborators(case: $case),
				]
			)
			->onlyMethods(['emitStyle', 'emitStylesheetLink', 'emitInlineStyle'])
			->getMock();
		$service->method('emitStyle')->willReturnCallback(
			function (string $file): void {
				$this->log[] = 'style:' . $file;
			}
		);
		$service->method('emitStylesheetLink')->willReturnCallback(
			function (string $url): void {
				$this->log[] = 'link:' . $url;
			}
		);
		$service->method('emitInlineStyle')->willReturnCallback(
			function (string $css, ?string $id = null): void {
				$this->log[] = 'inline:' . (string)$id . ':' . $this->shorten(text: $css);
			}
		);

		return $service;
	}//end service()

	/**
	 * The real per-group resolution over a seeded session, mapping and preview.
	 *
	 * @param IConfig $config The config.
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return GroupThemingService The service.
	 */
	private function groupTheming(IConfig $config, array $case): GroupThemingService {
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('piet');
		$session->method('getUser')->willReturn($case['signedIn'] === true ? $user : null);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturn(['staff', 'gemeente-a']);
		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(fn (string $id): bool => ($id !== 'gone-set'));
		$preview = $this->createMock(ThemePreviewService::class);
		$previewSet = $case['preview'];
		$preview->method('resolveEffectiveTokenSet')->willReturnCallback(
			fn (IUserSession $s, string $active): array => ['tokenSet' => ($previewSet ?? $active), 'previewActive' => ($previewSet !== null), 'expiresAt' => null]
		);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));

		return new GroupThemingService($config, $groups, $session, $tokenSets, $preview, $cacheFactory);
	}//end groupTheming()

	/**
	 * The brand per app: a seeded brand for `collectives`, its logo layer when it has a logo.
	 *
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return AppBrandService The double.
	 */
	private function brands(array $case): AppBrandService {
		$brands = $this->createMock(AppBrandService::class);
		$brandSet = $case['brand'];
		$failing = $case['failing'];
		$brands->method('brandFor')->willReturnCallback(
			function (?string $appId) use ($brandSet, $failing): ?array {
				if ($failing === 'brand') {
					throw new RuntimeException('brand');
				}

				if ($appId !== 'collectives' || $brandSet === null) {
					return null;
				}

				return ['tokenSet' => $brandSet, 'large' => '/brand/large', 'small' => null];
			}
		);
		$withLogo = $case['brandLogo'];
		$brands->method('logoLayer')->willReturnCallback(
			function (?array $brand, string $tokenSet, string $styleId) use ($withLogo): ?array {
				if ($brand === null || $withLogo === false) {
					return null;
				}

				return ['layer' => 'logo', 'kind' => 'inline', 'css' => ':root { --brand-logo: url(' . $brand['large'] . '); } /* ' . $tokenSet . ' */', 'id' => $styleId];
			}
		);

		return $brands;
	}//end brands()

	/**
	 * The optional top bar collaborators: recorded doubles, or null.
	 *
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return array<int, object|null> The header user, apps menu and search placeholder.
	 */
	private function collaborators(array $case): array {
		if ($case['collaborators'] === false) {
			return [null, null, null];
		}

		$failing = $case['failing'];
		$header = $this->createMock(HeaderUserService::class);
		$header->method('inject')->willReturnCallback(
			function (string $tokenSet, LayoutOptionsService $layoutOptions) use ($failing): void {
				if ($failing === 'header') {
					throw new RuntimeException('header');
				}

				$this->log[] = 'header:' . $tokenSet . '|' . json_encode($layoutOptions->resolved(tokenSet: $tokenSet));
			}
		);
		$menu = $this->createMock(AppMenuService::class);
		$menu->method('inject')->willReturnCallback(
			function (string $tokenSet, LayoutOptionsService $layoutOptions) use ($failing): void {
				if ($failing === 'menu') {
					throw new RuntimeException('menu');
				}

				$this->log[] = 'menu:' . $tokenSet . '|' . $layoutOptions->workplaceLayout(tokenSet: $tokenSet);
			}
		);
		$search = $this->createMock(SearchPlaceholderService::class);
		$search->method('inject')->willReturnCallback(
			function (?string $appId) use ($failing): void {
				if ($failing === 'search') {
					throw new RuntimeException('search');
				}

				$this->log[] = 'search:' . var_export($appId, true);
			}
		);

		return [$header, $menu, $search];
	}//end collaborators()

	/**
	 * The manifest for a set, or the error it raised.
	 *
	 * @param CssInjectionService $service The service.
	 * @param string $set The token set.
	 *
	 * @return array<string, mixed>|string The manifest with long CSS shortened, or the error.
	 */
	private function manifest(CssInjectionService $service, string $set): array|string {
		try {
			$manifest = $service->getStylesheetManifest(tokenSet: $set);
		} catch (Throwable $e) {
			return 'error:' . $this->describe(error: $e);
		}

		foreach ($manifest['layers'] as $index => $layer) {
			if (isset($layer['css']) === true) {
				$layer['css'] = $this->shorten(text: $layer['css']);
			}

			$manifest['layers'][$index] = implode('|', array_map(fn (string $key, $value): string => $key . '=' . var_export($value, true), array_keys($layer), $layer));
		}

		return $manifest;
	}//end manifest()

	/**
	 * Long CSS as its length and hash, short CSS as it is.
	 *
	 * @param string $text The CSS.
	 *
	 * @return string The text or its digest.
	 */
	private function shorten(string $text): string {
		if (strlen($text) <= 160) {
			return $text;
		}

		return 'sha1:' . sha1($text) . ':' . strlen($text);
	}//end shorten()

	/**
	 * An error as its class and message.
	 *
	 * @param mixed $error The error, or anything else.
	 *
	 * @return string The description.
	 */
	private function describe(mixed $error): string {
		if ($error instanceof Throwable) {
			return get_class($error) . ':' . $error->getMessage();
		}

		return '';
	}//end describe()

	/**
	 * Removes a temporary directory.
	 *
	 * @param string $path The directory.
	 *
	 * @return void
	 */
	private function removeDir(string $path): void {
		if (is_dir($path) === false) {
			return;
		}

		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$full = $path . '/' . $entry;
			is_dir($full) ? $this->removeDir(path: $full) : unlink($full);
		}

		rmdir($path);
	}//end removeDir()
}//end class
