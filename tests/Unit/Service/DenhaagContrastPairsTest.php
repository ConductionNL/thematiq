<?php

/**
 * Tests for the Den Haag component contrast pairs.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/denhaag-component-tokens/specs/token-set-contrast-audit/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DenhaagContrastPairs;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\TestCase;

/**
 * The pairs are measured on the real bridge and the real sets, through the
 * real audit service: a pair that is not wired into the audit fails here.
 */
class DenhaagContrastPairsTest extends TestCase {

	/**
	 * The repository root.
	 *
	 * @return string
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 3);
	}

	/**
	 * The audit service as the app builds it.
	 *
	 * @return ShippedTokenSetAuditService
	 */
	private function audit(): ShippedTokenSetAuditService {
		return new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());
	}

	/**
	 * A var() whose fallback holds parentheses resolves through to a literal.
	 *
	 * @return void
	 */
	public function testAFallbackWithParenthesesResolves(): void {
		$pairs = new DenhaagContrastPairs();
		$declarations = [
			'--a' => 'var(--missing, rgba(0, 0, 0, 0.5))',
			'--b' => 'rgba(var(--rgb, 57, 135, 12), 0.12)',
			'--c' => 'var(--d)',
			'--d' => 'var(--e, #123456)',
		];

		$this->assertSame(expected: 'rgba(0, 0, 0, 0.5)', actual: $pairs->resolve(declarations: $declarations, name: '--a'));
		$this->assertSame(expected: 'rgba(57, 135, 12, 0.12)', actual: $pairs->resolve(declarations: $declarations, name: '--b'));
		$this->assertSame(expected: '#123456', actual: $pairs->resolve(declarations: $declarations, name: '--c'));
		$this->assertNull(actual: $pairs->resolve(declarations: ['--x' => 'var(--nowhere)'], name: '--x'));
	}

	/**
	 * A set whose current step is unreadable fails that pair, with its ratio.
	 *
	 * @return void
	 */
	public function testAnUnreadableCurrentStepFails(): void {
		$result = (new DenhaagContrastPairs())->pairs(declarations: [
			'--nldesign-color-background' => '#ffffff',
			'--denhaag-step-marker-current-color' => '#ffffff',
			'--denhaag-step-marker-current-background-color' => 'var(--brand, #ffd23f)',
		]);

		$this->assertSame(expected: 'fail', actual: $result['step-current']['verdict']);
		$this->assertLessThan(expected: 4.5, actual: $result['step-current']['ratio']);
		$this->assertSame(expected: 'unevaluated', actual: $result['case-title']['verdict'], message: 'A pair with no value never passes.');
	}

	/**
	 * The audit reports every pair for every set, through the real bridge.
	 *
	 * @return void
	 */
	public function testTheAuditCarriesTheDenhaagPairs(): void {
		$result = $this->audit()->auditSet(appPath: $this->repoRoot(), id: 'example-gemeente', theming: []);

		$this->assertSame(expected: array_keys(DenhaagContrastPairs::PAIRS), actual: array_keys($result['denhaag']));
		foreach ($result['denhaag'] as $pair => $measured) {
			$this->assertSame(expected: 'pass', actual: $measured['verdict'], message: "example-gemeente {$pair}");
		}
	}

	/**
	 * A set without its own Den Haag values is judged through the bridge, and
	 * its warning text is its own warning hue, darkened until it reads.
	 *
	 * @return void
	 */
	public function testABridgedSetReadsItsWarningInADarkerShadeOfItsOwnHue(): void {
		$audit = $this->audit();
		$result = $audit->auditSet(appPath: $this->repoRoot(), id: 'tilburg', theming: []);
		$cascade = $audit->portalCascade(appPath: $this->repoRoot(), id: 'tilburg');
		$pairs = new DenhaagContrastPairs();

		$this->assertSame(expected: 'pass', actual: $result['denhaag']['action-date-warning']['verdict']);
		$this->assertStringContainsString(needle: '--thematiq-status-warning-text', haystack: $cascade['--denhaag-action-date-warning-color']);
		$this->assertNotSame(
			expected: $pairs->resolve(declarations: $cascade, name: '--nldesign-color-warning'),
			actual: $pairs->resolve(declarations: $cascade, name: '--denhaag-action-date-warning-color'),
			message: 'The text is derived from the warning colour, not the warning colour itself.'
		);
	}

	/**
	 * The portal page is the bridge's background, not Nextcloud's login colour.
	 *
	 * @return void
	 */
	public function testThePortalPageIsNotTheLoginBackground(): void {
		$cascade = $this->audit()->portalCascade(appPath: $this->repoRoot(), id: 'vng');

		$this->assertSame(expected: '#ffffff', actual: $cascade['--nldesign-color-background']);
		$this->assertStringContainsString(needle: '--nldesign-color-text', haystack: $cascade['--denhaag-case-card-title-color']);
	}

	/**
	 * The report lists the Den Haag pairs for every audited set.
	 *
	 * @return void
	 */
	public function testTheReportListsTheDenhaagPairs(): void {
		$report = $this->audit()->renderReport(appPath: $this->repoRoot());

		$this->assertStringContainsString(needle: '## Den Haag component pairs', haystack: $report);
		$this->assertMatchesRegularExpression(pattern: '/^\| example-gemeente \| 15 \| 0 \| 0 \|/m', string: $report);
	}

	/**
	 * A malformed or cyclic chain resolves to nothing, never to a guess.
	 *
	 * @return void
	 */
	public function testABrokenChainResolvesToNothing(): void {
		$pairs = new DenhaagContrastPairs();

		$this->assertNull(actual: $pairs->resolve(declarations: ['--a' => 'var(--b'], name: '--a'), message: 'An unclosed var() is malformed.');
		$this->assertNull(actual: $pairs->resolve(declarations: ['--a' => 'var(--a)'], name: '--a'), message: 'A self-reference ends at the depth limit.');
		$this->assertNull(actual: $pairs->resolve(declarations: [], name: '--a'), message: 'An undeclared property has no value.');
		$this->assertSame(expected: '#927739', actual: $pairs->resolve(declarations: ['--a' => 'var(--gone, #927739)'], name: '--a'));
		$this->assertSame(expected: '#917036', actual: $pairs->resolve(declarations: ['--a' => 'hsl(38deg 46% 39%)'], name: '--a'), message: 'An hsl() colour is measured, not left unevaluated.');
	}

	/**
	 * The report line names every pair that is not a pass, with its ratio or a dash.
	 *
	 * @return void
	 */
	public function testTheReportLineNamesFailingAndUnevaluatedPairs(): void {
		$pairs = new DenhaagContrastPairs();
		$measured = $pairs->pairs(declarations: [
			'--nldesign-color-background' => '#ffffff',
			'--denhaag-step-marker-current-color' => '#ffffff',
			'--denhaag-step-marker-current-background-color' => '#ffd23f',
		]);

		$lines = $pairs->reportLines(rows: [['id' => 'fixture', 'denhaag' => $measured]]);
		$rows = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '| fixture ')));
		$this->assertCount(expectedCount: 1, haystack: $rows);
		$row = $rows[0];

		$this->assertStringStartsWith(prefix: '| fixture | 0 | 1 | 14 |', string: $row);
		$this->assertStringContainsString(needle: 'step-current 1.', haystack: $row);
		$this->assertStringContainsString(needle: 'case-title —', haystack: $row);
	}

	/**
	 * A page without a declared background is white, as the bridge paints it.
	 *
	 * @return void
	 */
	public function testAPageWithoutABackgroundIsWhite(): void {
		$result = (new DenhaagContrastPairs())->pairs(declarations: [
			'--denhaag-file-link-color' => '#767676',
		]);

		$this->assertSame(expected: 'pass', actual: $result['file-link']['verdict'], message: '#767676 on white is 4.54:1.');
	}

	/**
	 * Every shipped set passes every Den Haag pair.
	 *
	 * A guard, not the verdict: the report keeps these pairs out of the
	 * pass/fail column, but a set or a mapping change that drops a pair under
	 * 4.5:1 fails here, naming the set, the pair and the ratio.
	 *
	 * @return void
	 */
	public function testEveryShippedSetPassesEveryDenhaagPair(): void {
		$failing = [];
		foreach ($this->audit()->auditAll(appPath: $this->repoRoot()) as $row) {
			foreach ($row['denhaag'] as $pair => $measured) {
				if ($measured['verdict'] !== 'pass') {
					$failing[] = $row['id'] . ' ' . $pair . ' ' . var_export($measured['ratio'], true);
				}
			}
		}

		$this->assertSame(expected: [], actual: $failing);
	}

	/**
	 * An unknown set still gets a portal cascade: defaults, bridge, white page.
	 *
	 * @return void
	 */
	public function testAnUnknownSetGetsTheBridgeAndAWhitePage(): void {
		$cascade = $this->audit()->portalCascade(appPath: $this->repoRoot(), id: 'no-such-set');

		$this->assertSame(expected: '#ffffff', actual: $cascade['--nldesign-color-background']);
		$this->assertArrayHasKey(key: '--thematiq-status-warning-text', array: $cascade);
	}

	/**
	 * The site title reads on the site header for a set with its own Tilburg header.
	 *
	 * example-basisschool paints the site header white and its logo dark, and
	 * aims its header text (#FFFFFF) at Nextcloud's orange header. The title
	 * must follow the logo, not the Nextcloud header text.
	 *
	 * @return void
	 */
	public function testTheSiteTitleFollowsTheLogoNotTheNextcloudHeaderText(): void {
		$cascade = $this->audit()->portalCascade(appPath: $this->repoRoot(), id: 'example-basisschool');
		$pairs = new DenhaagContrastPairs();

		$this->assertSame(
			expected: $pairs->resolve(declarations: $cascade, name: '--tilburg-logo-color'),
			actual: $pairs->resolve(declarations: $cascade, name: '--tilburg-header-logo-text-color')
		);
		$this->assertNotSame(
			expected: strtolower((string)$pairs->resolve(declarations: $cascade, name: '--nldesign-color-header-text')),
			actual: strtolower((string)$pairs->resolve(declarations: $cascade, name: '--tilburg-header-logo-text-color')),
			message: 'The title must not take the Nextcloud header text, which is white here.'
		);
		$this->assertSame(expected: 'pass', actual: $pairs->pairs(declarations: $cascade)['header-title']['verdict']);
	}
}
