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
 * says which design systems name their own docs.
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
	 * Every shipped design system gets a link, and each points at its own docs.
	 *
	 * @return void
	 */
	public function testShippedDesignSystemsLinkToTheirOwnDocumentation(): void {
		$urls = $this->service(appDir: dirname(__DIR__, 3))->getDocumentationUrls();

		$this->assertSame('https://nldesignsystem.nl', $urls['nldesign']);
		$this->assertSame('https://github.com/suitenumerique/cunningham', $urls['lasuite']);
		$this->assertSame('https://github.com/suitenumerique/cunningham', $urls['cunningham']);
		foreach (['none', 'summer-breeze', 'high-contrast'] as $id) {
			$this->assertSame(DesignSystemService::DEFAULT_DOCUMENTATION_URL, $urls[$id], $id . ' has no docs of its own');
		}

		foreach ($urls as $id => $url) {
			$this->assertStringNotContainsString('nldesign.app', $url, $id . ': nldesign.app no longer resolves');
		}
	}//end testShippedDesignSystemsLinkToTheirOwnDocumentation()

	/**
	 * A link that is not https falls back to the app's docs, so the manifest
	 * cannot put a javascript: or http: URL in the settings page header.
	 *
	 * @return void
	 */
	public function testANonHttpsLinkFallsBackToTheAppDocumentation(): void {
		$appDir = sys_get_temp_dir() . '/thematiq-ds-docs-' . uniqid();
		mkdir($appDir);
		file_put_contents(
			$appDir . '/design-systems.json',
			(string)json_encode(
				[
					['id' => 'a', 'documentation_url' => 'javascript:alert(1)'],
					['id' => 'b', 'documentation_url' => 'http://example.org'],
					['id' => 'c', 'documentation_url' => 42],
					['id' => 'd', 'documentation_url' => 'https://example.org/docs'],
				]
			)
		);

		try {
			$urls = $this->service(appDir: $appDir)->getDocumentationUrls();
		} finally {
			unlink($appDir . '/design-systems.json');
			rmdir($appDir);
		}

		$this->assertSame(
			[
				'a' => DesignSystemService::DEFAULT_DOCUMENTATION_URL,
				'b' => DesignSystemService::DEFAULT_DOCUMENTATION_URL,
				'c' => DesignSystemService::DEFAULT_DOCUMENTATION_URL,
				'd' => 'https://example.org/docs',
			],
			$urls
		);
	}//end testANonHttpsLinkFallsBackToTheAppDocumentation()
}//end class
