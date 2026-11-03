<?php

/**
 * A custom token set's own design system.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\DesignSystemService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Which design system a custom set resolves to.
 *
 * This is not a cosmetic field. `CssInjectionService` reads it to decide which
 * stylesheet layers a page emits, so getting it wrong rebuilds the instance:
 * a theme saved off stock Nextcloud that answers `nldesign` comes back wearing
 * Fira Sans, `defaults.css` and `element-overrides.css`, none of which the
 * admin asked for.
 */
class DesignSystemServiceCustomMetaTest extends TestCase {

	/**
	 * Build the service over a manifest of custom sets.
	 *
	 * @param string $manifest The raw appconfig value.
	 *
	 * @return DesignSystemService The service under test.
	 */
	private function build(string $manifest): DesignSystemService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(sys_get_temp_dir() . '/thematiq-no-manifest');

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use ($manifest): string {
				if ($key === CustomTokenSetService::MANIFEST_KEY) {
					return $manifest;
				}

				return $default;
			}
		);

		return new DesignSystemService(appManager: $appManager, config: $config);
	}//end build()

	/**
	 * A set saved off stock Nextcloud keeps saying so.
	 */
	public function testACustomSetCarriesTheDesignSystemItWasSavedFrom(): void {
		$service = $this->build(manifest: '{"custom-openwoo":{"name":"OpenWoo","design_system":"none"}}');

		$this->assertSame(
			expected: 'none',
			actual: $service->getTokenSetMeta(tokenSetId: 'custom-openwoo')['design_system'] ?? null
		);
	}//end testACustomSetCarriesTheDesignSystemItWasSavedFrom()

	/**
	 * A set written before the field existed has no opinion, and must not
	 * acquire one: CssInjectionService's own `?? 'nldesign'` keeps it exactly
	 * what it has always been.
	 */
	public function testASetWithoutTheFieldStaysUndecided(): void {
		$service = $this->build(manifest: '{"custom-lansingerland":{"name":"lansingerland"}}');

		$meta = $service->getTokenSetMeta(tokenSetId: 'custom-lansingerland');

		$this->assertArrayNotHasKey(key: 'design_system', array: $meta);
		$this->assertSame(expected: 'lansingerland', actual: $meta['name']);
	}//end testASetWithoutTheFieldStaysUndecided()

	/**
	 * A shipped set never consults the custom manifest, and an id that is in
	 * neither answers with nothing rather than with someone else's entry.
	 */
	public function testAnUnknownSetAnswersEmpty(): void {
		$service = $this->build(manifest: '{"custom-openwoo":{"name":"OpenWoo","design_system":"none"}}');

		$this->assertSame(expected: [], actual: $service->getTokenSetMeta(tokenSetId: 'custom-missing'));
		$this->assertSame(expected: [], actual: $service->getTokenSetMeta(tokenSetId: 'rijkshuisstijl'));
	}//end testAnUnknownSetAnswersEmpty()

	/**
	 * A manifest that is not JSON must not take the panel down with it.
	 */
	public function testABrokenManifestDegradesToEmpty(): void {
		$service = $this->build(manifest: 'not json at all');

		$this->assertSame(expected: [], actual: $service->getTokenSetMeta(tokenSetId: 'custom-openwoo'));
	}//end testABrokenManifestDegradesToEmpty()

	/**
	 * An entry that is not an object is treated as no entry, not handed on
	 * for a caller to index into.
	 */
	public function testAMalformedEntryDegradesToEmpty(): void {
		$service = $this->build(manifest: '{"custom-openwoo":"none"}');

		$this->assertSame(expected: [], actual: $service->getTokenSetMeta(tokenSetId: 'custom-openwoo'));
	}//end testAMalformedEntryDegradesToEmpty()
}//end class
