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

	private const FAILURES = ['brand', 'scopes', 'custom-css', 'banner', 'header', 'menu', 'search', 'fonts', 'stock', 'meta'];

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
	 * The seeded randomizer of the current case.
	 *
	 * @var Randomizer
	 */
	private Randomizer $random;

	/**
	 * Whether the current case keeps every option at its default.
	 *
	 * @var boolean
	 */
	private bool $plain = true;

	/**
	 * Every set a case can render.
	 *
	 * @var array<int, string>
	 */
	private array $sets = [];

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
		$this->sets = $sets;

		$actual = [];
		foreach ($sets as $index => $set) {
			$actual['set:' . $set] = $this->runCase(seed: (1000 + $index), set: $set, plain: true);
		}

		$special = ['nextcloud', 'summer-breeze', 'hoog-contrast', 'lasuite', 'frankendesk', 'cunningham', self::CUSTOM_SET, 'missing-set'];
		for ($seed = 1; $seed <= 300; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$pool = $sets;
			if ($random->getInt(0, 1) === 1) {
				$pool = $special;
			}

			$actual['seed:' . $seed] = $this->runCase(seed: $seed, set: $pool[$random->getInt(0, (count($pool) - 1))], plain: false);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
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
		$this->random = new Randomizer(engine: new Mt19937(seed: $seed));
		$this->plain = $plain;
		$this->log = [];
		$this->dir = sys_get_temp_dir() . '/thematiq-css-golden-' . bin2hex(random_bytes(4));
		$this->app = ['token_set' => $set];
		if ($plain === false) {
			$this->seedConfig();
		}

		$store = new DirectoryRuntimeFileStore(root: $this->dir);
		$this->seedStore(store: $store, set: $set);
		$failing = $this->pick(options: array_merge(array_fill(0, 6, ''), self::FAILURES));
		if ($plain === true) {
			$failing = '';
		}

		$case = ['failing' => $failing, 'store' => $store, 'signedIn' => ($plain === true || $this->random->getInt(0, 1) === 1), 'preview' => null];
		if ($this->chance(outOf: 6) === true) {
			$case['preview'] = $this->pick(options: ['amsterdam', 'lasuite', 'nextcloud', self::CUSTOM_SET]);
		}

		$case['brand'] = null;
		if ($this->chance(outOf: 3) === true) {
			$case['brand'] = $this->pick(options: $this->sets);
		}

		$case['brandLogo'] = $this->chance(outOf: 2);
		$case['collaborators'] = ($plain === false && $this->random->getInt(0, 3) !== 0);
		$service = $this->service(case: $case);
		$context = 'user';
		$appId = null;
		if ($plain === false) {
			$context = $this->pick(options: self::CONTEXTS);
			$appId = $this->pick(options: [null, 'files', 'collectives', '']);
		}

		$record = ['failing' => $failing, 'context' => $context, 'appId' => $appId];
		$record['themed'] = '';
		foreach (self::CONTEXTS as $name) {
			$record['themed'] .= (int)$service->isContextThemed(context: $name);
		}

		$record['manifestBefore'] = $this->manifest(service: $service, set: $set);
		$service->inject(context: $context, appId: $appId);
		$record['inject'] = $this->log;
		$this->log = [];
		$other = $set;
		if ($plain === false) {
			$other = $this->pick(options: ['amsterdam', 'lasuite', 'nextcloud', 'hoog-contrast', 'summer-breeze', $set]);
		}

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
	 * One of a list, drawn from the case's randomizer.
	 *
	 * @param array<int, mixed> $options The list.
	 *
	 * @return mixed The pick.
	 */
	private function pick(array $options): mixed {
		return $options[$this->random->getInt(0, (count($options) - 1))];
	}//end pick()

	/**
	 * True one time in N for a varied case, never for a plain one.
	 *
	 * @param int $outOf The N.
	 *
	 * @return bool The draw.
	 */
	private function chance(int $outOf): bool {
		return ($this->plain === false && $this->random->getInt(1, $outOf) === 1);
	}//end chance()

	/**
	 * Seeds the app config of a varied case.
	 *
	 * @return void
	 */
	private function seedConfig(): void {
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
			if ($this->chance(outOf: 2) === true) {
				$this->app[$key] = $this->pick(options: $values);
			}
		}

		if ($this->chance(outOf: 3) === true) {
			$this->app['group_token_sets'] = (string)json_encode(
				[
					['group' => 'gemeente-a', 'tokenSet' => $this->pick(options: ['amsterdam', 'lasuite', 'nextcloud', self::CUSTOM_SET, 'gone-set'])],
					['group' => 'staff', 'tokenSet' => $this->pick(options: ['denhaag', 'hoog-contrast', 'summer-breeze'])],
				]
			);
		}
	}//end seedConfig()

	/**
	 * Seeds the runtime files of a case: the uploaded set, dark variants and overrides.
	 *
	 * @param DirectoryRuntimeFileStore $store The store.
	 * @param string $set The default set.
	 *
	 * @return void
	 */
	private function seedStore(DirectoryRuntimeFileStore $store, string $set): void {
		$system = 'nldesign';
		if ($this->chance(outOf: 2) === true) {
			$system = 'none';
		}

		$custom = ['id' => self::CUSTOM_SET, 'name' => 'Golden', 'design_system' => $system];
		$this->app['custom_token_sets'] = (string)json_encode([self::CUSTOM_SET => $custom]);
		$store->write(name: 'css/tokens/' . self::CUSTOM_SET . '.css', content: ":root {\n  --nldesign-color-primary: #123456;\n}\n");
		if ($this->chance(outOf: 2) === true) {
			$store->write(name: 'css/tokens/dark/' . self::CUSTOM_SET . '.css', content: $this->darkCss(internal: $this->chance(outOf: 2)));
		}

		if ($this->chance(outOf: 4) === true) {
			$store->write(
				name: 'css/tokens/' . $set . '.css',
				content: ":root {\n  --nldesign-color-primary: #654321;\n  --nldesign-cn-email-accent: #222222;\n}\n"
			);
		}

		if ($this->chance(outOf: 4) === true) {
			$store->write(name: 'css/tokens/dark/' . $set . '.css', content: $this->darkCss(internal: $this->chance(outOf: 2)));
		}

		foreach (['nldesign', 'none'] as $system) {
			if ($this->chance(outOf: 2) === true) {
				$store->write(
					name: 'css/' . CustomOverridesService::fileFor(tokenSet: $set, designSystemId: $system) . '.css',
					content: ":root {\n  --nldesign-component-button-primary-background-color: #010203;\n  --nldesign-cn-color-swatch-checker: #0a0b0c;\n}\n"
				);
			}
		}
	}//end seedStore()

	/**
	 * A dark variant, with or without an internal token only the dark variant gives a value.
	 *
	 * @param bool $internal Whether to declare the internal token.
	 *
	 * @return string The CSS.
	 */
	private function darkCss(bool $internal): string {
		$extra = '';
		if ($internal === true) {
			$extra = "  --nldesign-cn-ai-pointer-offset: 3px;\n";
		}

		return ":root {\n  --nldesign-color-primary: #abcdef;\n" . $extra . "}\n";
	}//end darkCss()

	/**
	 * The service of one case, with its stylesheet emitters and collaborators recorded.
	 *
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return CssInjectionService The service.
	 */
	private function service(array $case): CssInjectionService {
		$random = $this->random;
		$failing = $case['failing'];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$config->method('getSystemValueString')->willReturnCallback(fn (string $key, string $default = '') => ($this->app[$key] ?? $default));

		$repo = $this->createMock(originalClassName: IAppManager::class);
		$repo->method('getAppPath')->willReturn(dirname(__DIR__, 3));
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkTo')->willReturnCallback(fn (string $app, string $file): string => '/apps/' . $app . '/' . $file);
		$urls->method('linkToRoute')->willReturnCallback(
			function (string $route, array $args = []): string {
				if ($args === []) {
					return '/route/' . $route;
				}

				return '/route/' . $route . '/' . http_build_query($args);
			}
		);
		$urls->method('imagePath')->willReturnCallback(fn (string $app, string $file): string => '/img/' . $app . '/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(fn (string $url): string => 'https://cloud.example' . $url);
		$runtimeFiles = new RuntimeFileLocator(
			appManager: $repo,
			store: $case['store'],
			urlGenerator: $urls,
			tempManager: $this->createMock(originalClassName: ITempManager::class)
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message, array $context = []): void {
				$this->log[] = 'warning:' . $message . '|' . ($context['layer'] ?? '') . '|' . $this->describe(error: ($context['exception'] ?? null));
			}
		);

		$customCss = $this->createMock(originalClassName: CustomCssService::class);
		$customCss->method('isEnabled')->willReturnCallback(
			fn (): bool => $this->failOr(failing: ($failing === 'custom-css'), message: 'custom css', value: true)
		);
		$customCss->method('hasContent')->willReturn(($this->plain === false && $random->getInt(0, 1) === 1));
		$fonts = $this->createMock(originalClassName: FontService::class);
		$hasFonts = ($this->plain === false && $random->getInt(0, 1) === 1);
		$fonts->method('hasFonts')->willReturnCallback(fn (): bool => $this->failOr(failing: ($failing === 'fonts'), message: 'fonts', value: $hasFonts));
		$fonts->method('getRevision')->willReturn($random->getInt(1, 9));
		$stock = $this->createMock(originalClassName: StockTokensService::class);
		$stockCss = null;
		if ($random->getInt(0, 1) === 1) {
			$stockCss = ":root {\n  --nldesign-color-primary: #0082c9;\n}\n";
		}

		$stock->method('getCss')->willReturnCallback(fn (): ?string => $this->failOr(failing: ($failing === 'stock'), message: 'stock', value: $stockCss));

		$banner = $this->createMock(originalClassName: ThemePreviewBannerService::class);
		$banner->method('inject')->willReturnCallback(
			function (string $tokenSet, array $tokenSetMeta) use ($failing): void {
				if ($failing === 'banner') {
					throw new RuntimeException(message: 'banner');
				}

				$this->log[] = 'banner:' . $tokenSet . '|' . json_encode($tokenSetMeta);
			}
		);

		$scopes = null;
		if ($failing === 'scopes') {
			$scopes = $this->createMock(originalClassName: InternalScopesService::class);
			$scopes->method('forSet')->willThrowException(new RuntimeException(message: 'scopes'));
		}

		$service = $this->getMockBuilder(className: CssInjectionService::class)
			->setConstructorArgs(
				[
					$config,
					$this->designSystems(repo: $repo, config: $config, case: $case),
					$customCss,
					$fonts,
					$urls,
					$this->groupTheming(config: $config, case: $case),
					$banner,
					$logger,
					$stock,
					$runtimeFiles,
					new LogoLayerService(config: $config, urlGenerator: $urls, logger: $logger, files: $runtimeFiles),
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
	 * The real design system service; with failing `meta` it reads no token set metadata,
	 * as when token-sets.json cannot be read.
	 *
	 * @param IAppManager $repo The app manager pointing at the repository.
	 * @param IConfig $config The config.
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return DesignSystemService The service.
	 */
	private function designSystems(IAppManager $repo, IConfig $config, array $case): DesignSystemService {
		if ($case['failing'] !== 'meta') {
			return new DesignSystemService(appManager: $repo, config: $config, store: $case['store']);
		}

		return new class($repo, $config, $case['store']) extends DesignSystemService {
			/**
			 * No metadata for any set.
			 *
			 * @param string $tokenSetId The token set.
			 *
			 * @return array<string, mixed> Nothing.
			 */
			public function getTokenSetMeta(string $tokenSetId): array {
				return [];
			}//end getTokenSetMeta()
		};
	}//end designSystems()

	/**
	 * The real per-group resolution over a seeded session, mapping and preview.
	 *
	 * @param IConfig $config The config.
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return GroupThemingService The service.
	 */
	private function groupTheming(IConfig $config, array $case): GroupThemingService {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('piet');
		$session->method('getUser')->willReturnCallback(fn (): ?IUser => $this->userIf(signedIn: $case['signedIn'], user: $user));
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('getUserGroupIds')->willReturn(['staff', 'gemeente-a']);
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(fn (string $id): bool => ($id !== 'gone-set'));
		$preview = $this->createMock(originalClassName: ThemePreviewService::class);
		$previewSet = $case['preview'];
		$preview->method('resolveEffectiveTokenSet')->willReturnCallback(
			fn (IUserSession $s, string $active): array => [
				'tokenSet' => ($previewSet ?? $active),
				'previewActive' => ($previewSet !== null),
				'expiresAt' => null,
			]
		);
		$cacheFactory = $this->createMock(originalClassName: ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));

		return new GroupThemingService(
			config: $config,
			groupManager: $groups,
			userSession: $session,
			tokenSetService: $tokenSets,
			previewService: $preview,
			cacheFactory: $cacheFactory
		);
	}//end groupTheming()

	/**
	 * The brand per app: a seeded brand for `collectives`, its logo layer when it has a logo.
	 *
	 * @param array<string, mixed> $case The case options.
	 *
	 * @return AppBrandService The double.
	 */
	private function brands(array $case): AppBrandService {
		$brands = $this->createMock(originalClassName: AppBrandService::class);
		$brandSet = $case['brand'];
		$failing = $case['failing'];
		$brands->method('brandFor')->willReturnCallback(
			function (?string $appId) use ($brandSet, $failing): ?array {
				if ($failing === 'brand') {
					throw new RuntimeException(message: 'brand');
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

				$css = ':root { --brand-logo: url(' . $brand['large'] . '); } /* ' . $tokenSet . ' */';

				return ['layer' => 'logo', 'kind' => 'inline', 'css' => $css, 'id' => $styleId];
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
		$header = $this->createMock(originalClassName: HeaderUserService::class);
		$header->method('inject')->willReturnCallback(
			function (string $tokenSet, LayoutOptionsService $layoutOptions) use ($failing): void {
				if ($failing === 'header') {
					throw new RuntimeException(message: 'header');
				}

				$this->log[] = 'header:' . $tokenSet . '|' . json_encode($layoutOptions->resolved(tokenSet: $tokenSet));
			}
		);
		$menu = $this->createMock(originalClassName: AppMenuService::class);
		$menu->method('inject')->willReturnCallback(
			function (string $tokenSet, LayoutOptionsService $layoutOptions) use ($failing): void {
				if ($failing === 'menu') {
					throw new RuntimeException(message: 'menu');
				}

				$this->log[] = 'menu:' . $tokenSet . '|' . $layoutOptions->workplaceLayout(tokenSet: $tokenSet);
			}
		);
		$search = $this->createMock(originalClassName: SearchPlaceholderService::class);
		$search->method('inject')->willReturnCallback(
			function (?string $appId) use ($failing): void {
				if ($failing === 'search') {
					throw new RuntimeException(message: 'search');
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

			$parts = [];
			foreach ($layer as $key => $value) {
				$parts[] = $key . '=' . var_export($value, true);
			}

			$manifest['layers'][$index] = implode('|', $parts);
		}

		return $manifest;
	}//end manifest()

	/**
	 * A collaborator's answer, or the failure the case asks for.
	 *
	 * @param bool $failing Whether to fail.
	 * @param string $message The failure's message.
	 * @param mixed $value The answer.
	 *
	 * @return mixed The answer.
	 *
	 * @throws RuntimeException When failing.
	 */
	private function failOr(bool $failing, string $message, mixed $value): mixed {
		if ($failing === true) {
			throw new RuntimeException(message: $message);
		}

		return $value;
	}//end failOr()

	/**
	 * The user of a signed-in request, or null.
	 *
	 * @param bool $signedIn Whether a user is signed in.
	 * @param IUser $user The user.
	 *
	 * @return IUser|null The user.
	 */
	private function userIf(bool $signedIn, IUser $user): ?IUser {
		if ($signedIn === true) {
			return $user;
		}

		return null;
	}//end userIf()

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

		foreach ((array)scandir($path) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$full = $path . '/' . $entry;
			if (is_dir($full) === true) {
				$this->removeDir(path: $full);
				continue;
			}

			unlink($full);
		}

		rmdir($path);
	}//end removeDir()
}//end class
