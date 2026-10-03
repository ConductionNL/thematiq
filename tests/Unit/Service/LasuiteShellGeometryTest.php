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
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturnCallback(
			static fn (string $key, string $default = ''): string => ($key === 'version' ? $serverVersion : $default)
		);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $default
		);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->root());

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkTo')->willReturnCallback(
			static fn (string $appName, string $file): string => '/apps/' . $appName . '/' . $file
		);

		$stockTokens = $this->createMock(StockTokensService::class);
		$stockTokens->method('getCss')->willReturn(null);

		$routes = $this->createMock(IURLGenerator::class);
		$routes->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => 'runtime:' . $args['name']);
		$runtimeFiles = new RuntimeFileLocator(
			$appManager,
			new DirectoryRuntimeFileStore(sys_get_temp_dir() . '/thematiq-shell-geometry-' . getmypid()),
			$routes,
			$this->createMock(ITempManager::class)
		);
		$logger = $this->createMock(LoggerInterface::class);

		return new CssInjectionService(
			$config,
			new DesignSystemService($appManager, $config),
			$this->createMock(CustomCssService::class),
			$this->createMock(FontService::class),
			$urlGenerator,
			$this->createMock(GroupThemingService::class),
			$this->createMock(ThemePreviewBannerService::class),
			$logger,
			$stockTokens,
			$runtimeFiles,
			new LogoLayerService($config, $urlGenerator, $logger, $runtimeFiles),
			$this->createMock(AppBrandService::class)
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
	 */
	public function testLasuiteOnNextcloud35LoadsTheShellLayerAfterTheDesignSystem(): void {
		$files = $this->files(tokenSet: 'lasuite', serverVersion: '35.0.1.0');

		$this->assertContains(self::SHELL_NC35, $files);
		$shell = array_search(self::SHELL_NC35, $files, true);
		$this->assertSame(array_search('systems/lasuite/element-overrides', $files, true) + 1, $shell);
		$this->assertGreaterThan($shell, array_search('tokens/lasuite', $files, true));
	}//end testLasuiteOnNextcloud35LoadsTheShellLayerAfterTheDesignSystem()

	/**
	 * A major the layer was not written against gets no shell layer at all:
	 * the stock Nextcloud geometry stays, rather than half-fitting overrides.
	 */
	public function testAnUnknownNextcloudMajorGetsNoShellLayer(): void {
		foreach (['34.0.5.0', '36.0.0.0', ''] as $version) {
			$this->assertNotContains(self::SHELL_NC35, $this->files(tokenSet: 'lasuite', serverVersion: $version), "version '$version'");
		}
	}//end testAnUnknownNextcloudMajorGetsNoShellLayer()

	/**
	 * The geometry belongs to the La Suite deployment, not to the unbranded
	 * Cunningham sibling or any other design system.
	 */
	public function testOnlyTheLasuiteSetLoadsTheShellLayer(): void {
		foreach (['cunningham', 'rijkshuisstijl', 'nextcloud'] as $set) {
			$this->assertNotContains(self::SHELL_NC35, $this->files(tokenSet: $set, serverVersion: '35.0.1.0'), $set);
		}
	}//end testOnlyTheLasuiteSetLoadsTheShellLayer()

	/**
	 * Every version-scoped stylesheet the manifest declares exists on disk.
	 */
	public function testEveryVersionScopedStylesheetExists(): void {
		$systems = json_decode((string)file_get_contents($this->root() . '/design-systems.json'), true);
		$declared = 0;
		foreach ($systems as $system) {
			foreach (($system['versioned_stylesheets'] ?? []) as $major => $stylesheets) {
				$this->assertMatchesRegularExpression('/^\d+$/', (string)$major, "{$system['id']}: keys are Nextcloud majors.");
				foreach ($stylesheets as $stylesheet) {
					$declared++;
					$this->assertFileExists($this->root() . '/css/' . $stylesheet . '.css');
				}
			}
		}

		$this->assertGreaterThan(0, $declared);
	}//end testEveryVersionScopedStylesheetExists()

	/**
	 * The shell layer sets La Suite Docs' header row height through
	 * Nextcloud's own variable, so every consumer of it follows.
	 */
	public function testTheShellLayerSetsTheLaSuiteHeaderHeight(): void {
		$css = (string)file_get_contents($this->root() . '/css/' . self::SHELL_NC35 . '.css');
		$this->assertMatchesRegularExpression('/body\s*\{[^}]*--header-height:\s*64px\s*!important/s', $css);
	}//end testTheShellLayerSetsTheLaSuiteHeaderHeight()

	/**
	 * The shared La Suite overrides follow the header height instead of
	 * assuming Nextcloud 34's 50px, so a taller (or the NC 35 shorter) header
	 * leaves no gap under it and keeps the search field centred.
	 */
	public function testTheSharedOverridesFollowTheHeaderHeight(): void {
		$css = (string)file_get_contents($this->root() . '/css/systems/lasuite/element-overrides.css');
		$this->assertDoesNotMatchRegularExpression('/margin:\s*50px\s+0\s+0/', $css, '#content must clear var(--header-height), not 50px.');
		$this->assertDoesNotMatchRegularExpression('/inset-block-start:\s*8px/', $css, 'The search field must centre on var(--header-height).');
		$this->assertStringContainsString('inset-block-start: calc((var(--header-height, 50px) - 34px) / 2) !important;', $css);
	}//end testTheSharedOverridesFollowTheHeaderHeight()
}//end class
