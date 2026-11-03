<?php

/**
 * The La Suite shell geometry layer: loaded for the lasuite set only, and only
 * on a Nextcloud major it was written against.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/lasuite-shell-geometry/specs/lasuite-stack/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppBrandService;
use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\CustomCssService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\GroupThemingService;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Walks the real design-systems.json and token-sets.json through
 * CssInjectionService::getStylesheetManifest(), the same list inject() emits.
 */
class LasuiteShellGeometryTest extends TestCase {

	/**
	 * The stylesheet that carries the La Suite shell geometry on Nextcloud 35.
	 */
	private const SHELL_NC35 = 'systems/lasuite/shell-nc35';

	/**
	 * The repo root.
	 *
	 * @return string The absolute path.
	 */
	private function root(): string {
		return dirname(__DIR__, 3);
	}//end root()

	/**
	 * Build the service against the real manifests, on a given server version.
	 *
	 * @param string $serverVersion The `version` system value, e.g. `35.0.1.0`.
	 *
	 * @return CssInjectionService The service under test.
	 */
	private function service(string $serverVersion): CssInjectionService {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getSystemValueString')->willReturnCallback(
			static fn (string $key, string $default = ''): string => (match ($key === 'version') {
				true => $serverVersion, default => $default
			})
		);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $default
		);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->root());

		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('linkTo')->willReturnCallback(
			static fn (string $appName, string $file): string => '/apps/' . $appName . '/' . $file
		);

		$stockTokens = $this->createMock(originalClassName: StockTokensService::class);
		$stockTokens->method('getCss')->willReturn(null);

		$routes = $this->createMock(originalClassName: IURLGenerator::class);
		$routes->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => 'runtime:' . $args['name']);
		$runtimeFiles = new RuntimeFileLocator(
			appManager: $appManager,
			store: new DirectoryRuntimeFileStore(root: sys_get_temp_dir() . '/thematiq-shell-geometry-' . getmypid()),
			urlGenerator: $routes,
			tempManager: $this->createMock(originalClassName: ITempManager::class)
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);

		return new CssInjectionService(
			config: $config,
			designSystemService: new DesignSystemService(appManager: $appManager, config: $config),
			customCssService: $this->createMock(originalClassName: CustomCssService::class),
			fontService: $this->createMock(originalClassName: FontService::class),
			urlGenerator: $urlGenerator,
			groupThemingService: $this->createMock(originalClassName: GroupThemingService::class),
			previewBannerService: $this->createMock(originalClassName: ThemePreviewBannerService::class),
			logger: $logger,
			stockTokens: $stockTokens,
			runtimeFiles: $runtimeFiles,
			logoLayer: new LogoLayerService(config: $config, urlGenerator: $urlGenerator, logger: $logger, files: $runtimeFiles),
			appBrands: $this->createMock(originalClassName: AppBrandService::class)
		);
	}//end service()

	/**
	 * The stylesheet paths of a set's manifest, in cascade order.
	 *
	 * @param string $tokenSet The token set id.
	 * @param string $serverVersion The server version.
	 *
	 * @return array<int, string> The app-relative paths without `.css` and query.
	 */
	private function files(string $tokenSet, string $serverVersion): array {
		$manifest = $this->service(serverVersion: $serverVersion)->getStylesheetManifest(tokenSet: $tokenSet);
		$files = [];
		foreach ($manifest['layers'] as $layer) {
			if (isset($layer['href']) === true) {
				$files[] = (string)preg_replace('#^/apps/thematiq/css/|\.css\?.*$#', '', (string)$layer['href']);
			}
		}

		return $files;
	}//end files()

	/**
	 * On Nextcloud 35 the lasuite set loads the shell layer directly after its
	 * design-system stylesheets and before the token layer.
	 *
	 * @return void
	 */
	public function testLasuiteOnNextcloud35LoadsTheShellLayerAfterTheDesignSystem(): void {
		$files = $this->files(tokenSet: 'lasuite', serverVersion: '35.0.1.0');

		$this->assertContains(needle: self::SHELL_NC35, haystack: $files);
		$shell = array_search(self::SHELL_NC35, $files, true);
		$this->assertSame(expected: array_search('systems/lasuite/element-overrides', $files, true) + 1, actual: $shell);
		$this->assertGreaterThan(expected: $shell, actual: array_search('tokens/lasuite', $files, true));
	}//end testLasuiteOnNextcloud35LoadsTheShellLayerAfterTheDesignSystem()

	/**
	 * A major the layer was not written against gets no shell layer at all:
	 * the stock Nextcloud geometry stays, rather than half-fitting overrides.
	 *
	 * @return void
	 */
	public function testAnUnknownNextcloudMajorGetsNoShellLayer(): void {
		foreach (['34.0.5.0', '36.0.0.0', ''] as $version) {
			$this->assertNotContains(
				needle: self::SHELL_NC35,
				haystack: $this->files(tokenSet: 'lasuite', serverVersion: $version),
				message: "version '$version'"
			);
		}
	}//end testAnUnknownNextcloudMajorGetsNoShellLayer()

	/**
	 * The geometry belongs to the La Suite deployment, not to the unbranded
	 * Cunningham sibling or any other design system.
	 *
	 * @return void
	 */
	public function testOnlyTheLasuiteSetLoadsTheShellLayer(): void {
		foreach (['cunningham', 'rijkshuisstijl', 'nextcloud'] as $set) {
			$this->assertNotContains(needle: self::SHELL_NC35, haystack: $this->files(tokenSet: $set, serverVersion: '35.0.1.0'), message: $set);
		}
	}//end testOnlyTheLasuiteSetLoadsTheShellLayer()

	/**
	 * Every version-scoped stylesheet the manifest declares exists on disk.
	 *
	 * @return void
	 */
	public function testEveryVersionScopedStylesheetExists(): void {
		$systems = json_decode((string)file_get_contents($this->root() . '/design-systems.json'), true);
		$declared = 0;
		foreach ($systems as $system) {
			foreach (($system['versioned_stylesheets'] ?? []) as $major => $stylesheets) {
				$this->assertMatchesRegularExpression(
					pattern: '/^\d+$/',
					string: (string)$major,
					message: "{$system['id']}: keys are Nextcloud majors."
				);
				foreach ($stylesheets as $stylesheet) {
					$declared++;
					$this->assertFileExists(filename: $this->root() . '/css/' . $stylesheet . '.css');
				}
			}
		}

		$this->assertGreaterThan(expected: 0, actual: $declared);
	}//end testEveryVersionScopedStylesheetExists()

	/**
	 * The shell layer sets La Suite Docs' header row height through
	 * Nextcloud's own variable, so every consumer of it follows.
	 *
	 * @return void
	 */
	public function testTheShellLayerSetsTheLaSuiteHeaderHeight(): void {
		$css = (string)file_get_contents($this->root() . '/css/' . self::SHELL_NC35 . '.css');
		$this->assertMatchesRegularExpression(pattern: '/body\s*\{[^}]*--header-height:\s*64px\s*!important/s', string: $css);
	}//end testTheShellLayerSetsTheLaSuiteHeaderHeight()

	/**
	 * The shared La Suite overrides follow the header height instead of
	 * assuming Nextcloud 34's 50px, so a taller (or the NC 35 shorter) header
	 * leaves no gap under it and keeps the search field centred.
	 *
	 * @return void
	 */
	public function testTheSharedOverridesFollowTheHeaderHeight(): void {
		$css = (string)file_get_contents($this->root() . '/css/systems/lasuite/element-overrides.css');
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/margin:\s*50px\s+0\s+0/',
			string: $css,
			message: '#content must clear var(--header-height), not 50px.'
		);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/inset-block-start:\s*8px/',
			string: $css,
			message: 'The search field must centre on var(--header-height).'
		);
		// #932: core centres the field's row on NC 35, so the field sits on it
		// with inset-block: 0 and auto block margins instead of a fixed offset.
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/inset-block-start:\s*15px/',
			string: $css,
			message: 'A fixed 15px offset pushes the field below the centre of the 64px header.'
		);
		$this->assertStringContainsString(needle: 'inset-block: 0 !important;', haystack: $css);
		$this->assertStringContainsString(needle: 'margin-block: auto !important;', haystack: $css);
	}//end testTheSharedOverridesFollowTheHeaderHeight()
}//end class
