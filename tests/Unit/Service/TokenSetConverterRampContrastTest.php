<?php

/**
 * Unit tests for the contrast floor on the brand-ramp fallback.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Issue #933: a set whose only grey is white converted every text, border and
 * hover role to #ffffff, because the "darkest" grey of a one-grey ramp is that
 * grey, however light it is. The ramp may only supply a role when the pick
 * still stands apart from the page background; otherwise the role is left to
 * Nextcloud's defaults, exactly like a set that declares no greys at all.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
class TokenSetConverterRampContrastTest extends TestCase {

	/**
	 * The converter under test, with the real mapping table.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Build the converter against the app's own mapping table.
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Convert a stylesheet and index the report by target.
	 *
	 * @param string $css The uploaded stylesheet.
	 *
	 * @return array<string, array<string, mixed>> Report entries keyed by target token.
	 */
	private function reportFor(string $css): array {
		$result = $this->converter->convert(content: $css, slug: 'ramp-floor', displayName: 'Ramp floor');

		$byTarget = [];
		foreach ($result['report'] as $entry) {
			$byTarget[(string)($entry['target'] ?? '')] = $entry;
		}

		return $byTarget;
	}//end reportFor()

	/**
	 * The exact upload from the issue: the only grey is white, so no role may
	 * take a value from the ramp. Each one falls back to the defaults instead.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAWhiteOnlyRampSuppliesNoRole(): void {
		$report = $this->reportFor(':root{--nldesign-color-primary:#8a2be2;--nldesign-color-primary-text:#ffffff}');

		$roles = [
			'--nldesign-color-text',
			'--nldesign-color-text-muted',
			'--nldesign-color-border',
			'--nldesign-color-border-dark',
			'--nldesign-color-background-hover',
			'--nldesign-color-background-dark',
			'--nldesign-color-background-darker',
		];
		foreach ($roles as $role) {
			$entry = ($report[$role] ?? []);
			$this->assertNotSame('(brand ramp)', $entry['source'] ?? null, $role . ' was taken from a ramp of white only');
			$this->assertNotSame('#ffffff', strtolower((string)($entry['value'] ?? '')), $role . ' became white on white');
		}
	}//end testAWhiteOnlyRampSuppliesNoRole()

	/**
	 * Control from the issue: a set with only the primary colour has no ramp
	 * at all and leaves the roles to the defaults. It must stay that way.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAPrimaryOnlySetStillFallsBackToTheDefaults(): void {
		$report = $this->reportFor(':root{--nldesign-color-primary:#8a2be2}');

		$this->assertArrayNotHasKey('--nldesign-color-text', $report);
		$this->assertArrayNotHasKey('--nldesign-color-border', $report);
	}//end testAPrimaryOnlySetStillFallsBackToTheDefaults()

	/**
	 * A ramp with a real dark grey keeps supplying the roles: the floor
	 * refuses a pick that blends into the background, nothing else.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testARampWithAReadableGreyStillSuppliesTheRoles(): void {
		$report = $this->reportFor(
			':root{--nldesign-color-primary:#8a2be2;--nldesign-color-primary-text:#ffffff;'
			. '--brand-grey-20:#333333;--brand-grey-80:#cccccc;--brand-grey-95:#f2f2f2}'
		);

		$this->assertSame('(brand ramp)', $report['--nldesign-color-text']['source'] ?? null);
		$this->assertSame('#333333', strtolower((string)($report['--nldesign-color-text']['value'] ?? '')));
		$this->assertSame('#cccccc', strtolower((string)($report['--nldesign-color-border']['value'] ?? '')));
		$this->assertSame('#f2f2f2', strtolower((string)($report['--nldesign-color-background-hover']['value'] ?? '')));
	}//end testARampWithAReadableGreyStillSuppliesTheRoles()
}//end class
