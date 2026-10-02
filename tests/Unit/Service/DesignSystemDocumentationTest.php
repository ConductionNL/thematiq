<?php

/**
 * The settings page's Documentation link follows the design system.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignSystemService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Reads the shipped design-systems.json, not a fixture: the defect (#662) was
 * a link nothing derived from the design system, and only the real manifest
 * says which design systems name their own docs. Admin::getForm() turns these
 * into links, with the fallback (AdminInitialStateTest).
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-documentation-link-follows-the-design-system
 */
class DesignSystemDocumentationTest extends TestCase {

	/**
	 * A service reading a given app directory.
	 *
	 * @param string $appDir The app directory.
	 *
	 * @return DesignSystemService The service.
	 */
	private function service(string $appDir): DesignSystemService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appDir);

		return new DesignSystemService($appManager, $this->createMock(IConfig::class));
	}//end service()

	/**
	 * The shipped manifest names each design system's own docs, and only https.
	 *
	 * @return void
	 */
	public function testShippedDesignSystemsNameTheirOwnDocumentation(): void {
		$systems = $this->service(appDir: dirname(__DIR__, 3))->getDesignSystems();
		$urls = array_map(static fn (array $ds) => ($ds['documentation_url'] ?? null), $systems);

		$this->assertSame('https://nldesignsystem.nl', $urls['nldesign']);
		$this->assertSame('https://github.com/suitenumerique/cunningham', $urls['lasuite']);
		$this->assertSame('https://github.com/suitenumerique/cunningham', $urls['cunningham']);
		foreach (['none', 'summer-breeze', 'high-contrast'] as $id) {
			$this->assertNull($urls[$id], $id . ' has no docs of its own, so Admin falls back to the app docs');
		}

		foreach (array_filter($urls) as $id => $url) {
			$this->assertStringStartsWith('https://', $url, $id);
			$this->assertStringNotContainsString('nldesign.app', $url, $id . ': nldesign.app no longer resolves');
		}
	}//end testShippedDesignSystemsNameTheirOwnDocumentation()
}//end class
