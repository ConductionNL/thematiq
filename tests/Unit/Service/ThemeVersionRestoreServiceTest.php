<?php

/**
 * Unit tests for ThemeVersionRestoreService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ThemeVersionRestoreService;
use OCA\Thematiq\Service\ThemeVersionService;
use OCA\Thematiq\Service\ThemingAuditService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Preview and restore go through the bundle import, a refused version writes
 * nothing, and a restore is audited with the version it replaced.
 */
class ThemeVersionRestoreServiceTest extends TestCase {

	/**
	 * @var ThemeVersionService&MockObject
	 */
	private $versions;

	/**
	 * @var ConfigBundleService&MockObject
	 */
	private $bundles;

	/**
	 * @var ThemingAuditService&MockObject
	 */
	private $audit;

	/**
	 * @var FontService&MockObject
	 */
	private $fonts;

	/**
	 * A kept version whose bundle has the rijkshuisstijl set and a heading font.
	 *
	 * @var array<string, mixed>
	 */
	private array $version = [
		'id' => '20260929164000-0001',
		'bundle' => [
			'config' => ['tokenSet' => 'rijkshuisstijl', 'hideSlogan' => false],
			'customTokenSets' => [],
			'customFonts' => ['manifest' => ['gone-font' => ['name' => 'Gone Sans', 'role' => 'heading']]],
		],
	];

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->versions = $this->createMock(ThemeVersionService::class);
		$this->bundles = $this->createMock(ConfigBundleService::class);
		$this->audit = $this->createMock(ThemingAuditService::class);
		$this->fonts = $this->createMock(FontService::class);

		$this->versions->method('get')->willReturnCallback(
			fn (string $id): ?array => $id === $this->version['id'] ? $this->version : null
		);
		$this->versions->method('list')->willReturn([['id' => '20260929170000-0001'], ['id' => $this->version['id']]]);
		$this->bundles->method('export')->willReturn(
			[
				'config' => ['tokenSet' => 'custom-bad', 'hideSlogan' => false],
				'customTokenSets' => [['id' => 'custom-bad']],
				'customFonts' => ['manifest' => []],
			]
		);
		$this->fonts->method('getEntry')->willReturn(null);
	}//end setUp()

	/**
	 * The service under test.
	 *
	 * @return ThemeVersionRestoreService The service.
	 */
	private function service(): ThemeVersionRestoreService {
		return new ThemeVersionRestoreService($this->versions, $this->bundles, $this->audit, $this->fonts);
	}//end service()

	/**
	 * The preview is a dry-run import plus the changes it will make.
	 */
	public function testThePreviewListsTheChangesAndWritesNothing(): void {
		$this->bundles->expects($this->once())->method('import')->with($this->version['bundle'], true)->willReturn(['valid' => true, 'dryRun' => true, 'applied' => false]);
		$this->audit->expects($this->never())->method('log');

		$preview = $this->service()->preview(id: $this->version['id']);

		$this->assertTrue($preview['valid']);
		$this->assertContains(['field' => 'tokenSet', 'from' => 'custom-bad', 'to' => 'rijkshuisstijl'], $preview['changes']);
		$this->assertSame(['custom-bad'], $preview['customTokenSets']['remove']);
		$this->assertSame([['id' => 'gone-font', 'name' => 'Gone Sans', 'role' => 'heading']], $preview['missingFonts']);
	}//end testThePreviewListsTheChangesAndWritesNothing()

	/**
	 * A restore imports the bundle and records version_restored with the
	 * version it replaced.
	 */
	public function testARestoreImportsAndIsAudited(): void {
		$this->bundles->expects($this->exactly(2))->method('import')->willReturnCallback(
			fn (array $bundle, bool $dryRun = false): array => ['valid' => true, 'dryRun' => $dryRun, 'applied' => !$dryRun]
		);
		$this->audit->expects($this->once())->method('log')->with(
			'version_restored',
			['old' => '20260929170000-0001', 'new' => $this->version['id']]
		);

		$result = $this->service()->restore(id: $this->version['id']);

		$this->assertTrue($result['applied']);
	}//end testARestoreImportsAndIsAudited()

	/**
	 * A version that no longer validates is refused whole: nothing applied, nothing audited.
	 */
	public function testAnInvalidVersionWritesNothing(): void {
		$this->bundles->expects($this->once())->method('import')->with($this->version['bundle'], true)->willReturn(
			['valid' => false, 'dryRun' => true, 'applied' => false, 'errors' => [['section' => 'customTokenSets', 'id' => 'custom-x']]]
		);
		$this->audit->expects($this->never())->method('log');

		$result = $this->service()->restore(id: $this->version['id']);

		$this->assertFalse($result['applied']);
		$this->assertSame('custom-x', $result['errors'][0]['id']);
	}//end testAnInvalidVersionWritesNothing()

	/**
	 * An unknown id is null, for a 404.
	 */
	public function testAnUnknownIdIsNull(): void {
		$this->bundles->expects($this->never())->method('import');

		$this->assertNull($this->service()->preview(id: '20200101000000-0001'));
		$this->assertNull($this->service()->restore(id: '20200101000000-0001'));
	}//end testAnUnknownIdIsNull()
}//end class
