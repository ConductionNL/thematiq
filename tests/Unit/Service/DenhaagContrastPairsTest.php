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
	 * A set without its own Den Haag values is judged through the bridge, and a
	 * weak warning colour shows up as a failing pair, not as a pass.
	 *
	 * @return void
	 */
	public function testABridgedSetWithAWeakWarningFailsThatPair(): void {
		$result = $this->audit()->auditSet(appPath: $this->repoRoot(), id: 'tilburg', theming: []);

		$this->assertSame('pass', $result['denhaag']['case-title']['verdict']);
		$this->assertSame('fail', $result['denhaag']['action-date-warning']['verdict']);
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
		$this->assertMatchesRegularExpression('/^\| example-gemeente \| 13 \| 0 \| 0 \|/m', $report);
	}
}
