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
		return new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());
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

		$this->assertSame('rgba(0, 0, 0, 0.5)', $pairs->resolve(declarations: $declarations, name: '--a'));
		$this->assertSame('rgba(57, 135, 12, 0.12)', $pairs->resolve(declarations: $declarations, name: '--b'));
		$this->assertSame('#123456', $pairs->resolve(declarations: $declarations, name: '--c'));
		$this->assertNull($pairs->resolve(declarations: ['--x' => 'var(--nowhere)'], name: '--x'));
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

		$this->assertSame('fail', $result['step-current']['verdict']);
		$this->assertLessThan(4.5, $result['step-current']['ratio']);
		$this->assertSame('unevaluated', $result['case-title']['verdict'], 'A pair with no value never passes.');
	}

	/**
	 * The audit reports every pair for every set, through the real bridge.
	 *
	 * @return void
	 */
	public function testTheAuditCarriesTheDenhaagPairs(): void {
		$result = $this->audit()->auditSet(appPath: $this->repoRoot(), id: 'example-gemeente', theming: []);

		$this->assertSame(array_keys(DenhaagContrastPairs::PAIRS), array_keys($result['denhaag']));
		foreach ($result['denhaag'] as $pair => $measured) {
			$this->assertSame('pass', $measured['verdict'], "example-gemeente {$pair}");
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

		$this->assertSame('pass', $result['denhaag']['action-date-warning']['verdict']);
		$this->assertStringContainsString('--thematiq-status-warning-text', $cascade['--denhaag-action-date-warning-color']);
		$this->assertNotSame(
			$pairs->resolve(declarations: $cascade, name: '--nldesign-color-warning'),
			$pairs->resolve(declarations: $cascade, name: '--denhaag-action-date-warning-color'),
			'The text is derived from the warning colour, not the warning colour itself.'
		);
	}

	/**
	 * The portal page is the bridge's background, not Nextcloud's login colour.
	 *
	 * @return void
	 */
	public function testThePortalPageIsNotTheLoginBackground(): void {
		$cascade = $this->audit()->portalCascade(appPath: $this->repoRoot(), id: 'vng');

		$this->assertSame('#ffffff', $cascade['--nldesign-color-background']);
		$this->assertStringContainsString('--nldesign-color-text', $cascade['--denhaag-case-card-title-color']);
	}

	/**
	 * The report lists the Den Haag pairs for every audited set.
	 *
	 * @return void
	 */
	public function testTheReportListsTheDenhaagPairs(): void {
		$report = $this->audit()->renderReport(appPath: $this->repoRoot());

		$this->assertStringContainsString('## Den Haag component pairs', $report);
		$this->assertMatchesRegularExpression('/^\| example-gemeente \| 15 \| 0 \| 0 \|/m', $report);
	}

	/**
	 * A malformed or cyclic chain resolves to nothing, never to a guess.
	 *
	 * @return void
	 */
	public function testABrokenChainResolvesToNothing(): void {
		$pairs = new DenhaagContrastPairs();

		$this->assertNull($pairs->resolve(declarations: ['--a' => 'var(--b'], name: '--a'), 'An unclosed var() is malformed.');
		$this->assertNull($pairs->resolve(declarations: ['--a' => 'var(--a)'], name: '--a'), 'A self-reference ends at the depth limit.');
		$this->assertNull($pairs->resolve(declarations: [], name: '--a'), 'An undeclared property has no value.');
		$this->assertSame('#927739', $pairs->resolve(declarations: ['--a' => 'var(--gone, #927739)'], name: '--a'));
		$this->assertSame('#917036', $pairs->resolve(declarations: ['--a' => 'hsl(38deg 46% 39%)'], name: '--a'), 'An hsl() colour is measured, not left unevaluated.');
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
		$this->assertCount(1, $rows);
		$row = $rows[0];

		$this->assertStringStartsWith('| fixture | 0 | 1 | 14 |', $row);
		$this->assertStringContainsString('step-current 1.', $row);
		$this->assertStringContainsString('case-title —', $row);
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

		$this->assertSame('pass', $result['file-link']['verdict'], '#767676 on white is 4.54:1.');
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

		$this->assertSame([], $failing);
	}

	/**
	 * An unknown set still gets a portal cascade: defaults, bridge, white page.
	 *
	 * @return void
	 */
	public function testAnUnknownSetGetsTheBridgeAndAWhitePage(): void {
		$cascade = $this->audit()->portalCascade(appPath: $this->repoRoot(), id: 'no-such-set');

		$this->assertSame('#ffffff', $cascade['--nldesign-color-background']);
		$this->assertArrayHasKey('--thematiq-status-warning-text', $cascade);
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
			$pairs->resolve(declarations: $cascade, name: '--tilburg-logo-color'),
			$pairs->resolve(declarations: $cascade, name: '--tilburg-header-logo-text-color')
		);
		$this->assertNotSame(
			strtolower((string)$pairs->resolve(declarations: $cascade, name: '--nldesign-color-header-text')),
			strtolower((string)$pairs->resolve(declarations: $cascade, name: '--tilburg-header-logo-text-color')),
			'The title must not take the Nextcloud header text, which is white here.'
		);
		$this->assertSame('pass', $pairs->pairs(declarations: $cascade)['header-title']['verdict']);
	}
}
