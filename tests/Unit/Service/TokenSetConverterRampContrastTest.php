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
 * still stands apart from the page background; otherwise the role takes the
 * default the mapping rule names, so the set keeps its full semantic layer.
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
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
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
	 * take a value from the ramp. Each one takes its default from the rule instead.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testAWhiteOnlyRampSuppliesNoRole(): void {
		$report = $this->reportFor(css: ':root{--nldesign-color-primary:#8a2be2;--nldesign-color-primary-text:#ffffff}');

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
			$this->assertNotSame(expected: '(brand ramp)', actual: $entry['source'] ?? null, message: $role . ' was taken from a ramp of white only');
			$this->assertNotSame(expected: '#ffffff', actual: strtolower((string)($entry['value'] ?? '')), message: $role . ' became white on white');
		}

		// The set still carries the full semantic layer (multi-brand task 5.5):
		// the roles take their defaults instead of the white.
		$this->assertSame(expected: '(Nextcloud default)', actual: $report['--nldesign-color-text']['source'] ?? null);
		$this->assertSame(expected: '#333333', actual: $report['--nldesign-color-text']['value'] ?? null);
		$this->assertSame(expected: '#b4b4b4', actual: $report['--nldesign-color-border']['value'] ?? null);
	}//end testAWhiteOnlyRampSuppliesNoRole()

	/**
	 * Control from #933: a set with only the primary colour has no ramp at all
	 * and its roles take the defaults. Since #1006 the converter writes those
	 * default values into the set (the same values defaults.css holds, so the
	 * rendering is unchanged) instead of leaving the tokens out, because a
	 * shipped set must declare the required vocabulary itself.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 *
	 * @return void
	 */
	public function testAPrimaryOnlySetStillFallsBackToTheDefaults(): void {
		$report = $this->reportFor(css: ':root{--nldesign-color-primary:#8a2be2}');

		$this->assertSame(expected: '(Nextcloud default)', actual: $report['--nldesign-color-text']['source'] ?? null);
		$this->assertSame(expected: '#333333', actual: $report['--nldesign-color-text']['value'] ?? null);
		$this->assertSame(expected: '#b4b4b4', actual: $report['--nldesign-color-border']['value'] ?? null);
	}//end testAPrimaryOnlySetStillFallsBackToTheDefaults()

	/**
	 * A ramp with a real dark grey keeps supplying the roles: the floor
	 * refuses a pick that blends into the background, nothing else.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testARampWithAReadableGreyStillSuppliesTheRoles(): void {
		$report = $this->reportFor(
			css: ':root{--nldesign-color-primary:#8a2be2;--nldesign-color-primary-text:#ffffff;'
			. '--brand-grey-20:#333333;--brand-grey-80:#cccccc;--brand-grey-95:#f2f2f2}'
		);

		$this->assertSame(expected: '(brand ramp)', actual: $report['--nldesign-color-text']['source'] ?? null);
		$this->assertSame(expected: '#333333', actual: strtolower((string)($report['--nldesign-color-text']['value'] ?? '')));
		$this->assertSame(expected: '#cccccc', actual: strtolower((string)($report['--nldesign-color-border']['value'] ?? '')));
		$this->assertSame(expected: '#f2f2f2', actual: strtolower((string)($report['--nldesign-color-background-hover']['value'] ?? '')));
	}//end testARampWithAReadableGreyStillSuppliesTheRoles()
}//end class
