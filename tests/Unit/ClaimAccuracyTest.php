<?php

/**
 * Claim-accuracy inventory regression test.
 *
 * Guards the claim-accuracy capability contract: the app's public claim surfaces
 * (manifest metadata, README, government feature checklist, compliance and audit
 * docs) MUST agree with the shipped code. Each claim is pinned to a filesystem
 * source of truth — the LICENSE file, css/fonts.css, and token-sets.json — so a
 * future edit that re-introduces "AGPL", a CDN font URL, a wrong token-set count,
 * or an over-broad audit verdict fails this test rather than shipping misleading
 * metadata to procuring organizations. Mirrors the tests/Unit/IconAssetsTest.php
 * static-inventory pattern (no Nextcloud runtime required).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.1
 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.2
 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.3
 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.4
 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.5
 * @spec openspec/specs/marianne-font/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static claim-accuracy inventory regression test (no Nextcloud runtime required).
 */
class ClaimAccuracyTest extends TestCase {
	/**
	 * Repository root, derived from this test file's location.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}

	/**
	 * Read a file from the repository root.
	 */
	private function readFile(string $relativePath): string {
		$path = $this->repoRoot() . '/' . $relativePath;
		$this->assertFileExists($path, "Expected file to exist: {$relativePath}");
		$contents = file_get_contents($path);
		$this->assertIsString($contents, "Could not read {$relativePath}");
		return $contents;
	}

	/**
	 * The number of token sets shipped, derived from token-sets.json.
	 */
	private function tokenSetCount(): int {
		$json = json_decode($this->readFile('token-sets.json'), true);
		$this->assertIsArray($json, 'token-sets.json must decode to an array.');
		return \count($json);
	}

	/**
	 * The manifest licence equals the bundled licence (EUPL-1.2), never AGPL.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.1
	 */
	public function testManifestLicenceMatchesBundledLicence(): void {
		$info = $this->readFile('appinfo/info.xml');

		// The fleet declares the version explicitly ("EUPL-1.2"), which the
		// original bare-"eupl" pattern rejected — so this test failed against a
		// manifest that was in fact correct, and consistent with every other
		// Conduction app. Accept the version suffix; the point of the assertion
		// is EUPL rather than AGPL, which the check below still enforces.
		$this->assertMatchesRegularExpression(
			'#<licence>\s*eupl(-1\.2)?\s*</licence>#i',
			$info,
			'appinfo/info.xml <licence> must declare EUPL ("eupl" or "EUPL-1.2").'
		);
		$this->assertDoesNotMatchRegularExpression(
			'#<licence>\s*agpl\s*</licence>#i',
			$info,
			'appinfo/info.xml must not declare AGPL.'
		);

		// The manifest description must state the EUPL licence in prose.
		$this->assertMatchesRegularExpression(
			'/EUPL[\s-]?1\.2/i',
			$info,
			'appinfo/info.xml <description> must mention the EUPL-1.2 licence.'
		);

		// The bundled LICENSE file must be the European Union Public Licence.
		$license = $this->readFile('LICENSE');
		$firstLine = strtok($license, "\n");
		$this->assertIsString($firstLine, 'LICENSE must have a first line.');
		$this->assertStringContainsStringIgnoringCase(
			'EUROPEAN UNION PUBLIC LICENCE',
			$firstLine,
			'The first line of LICENSE must name the European Union Public Licence.'
		);
	}

	// REUSE-IgnoreStart -- from here to the end of testSpdxHeadersAgreeWithManifest()
	// the SPDX tags are the SUBJECT of the assertions (patterns matched against
	// lib/**/*.php), not a declaration about this file, which is EUPL-1.2 per its
	// own header above.

	/**
	 * Every PHP file under lib/ carries SPDX-License-Identifier: EUPL-1.2 and none declares AGPL.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.2
	 */
	public function testSpdxHeadersAgreeWithManifest(): void {
		$libDir = $this->repoRoot() . '/lib';
		$this->assertDirectoryExists($libDir);

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($libDir, \FilesystemIterator::SKIP_DOTS)
		);

		$checked = 0;
		foreach ($iterator as $file) {
			/** @var \SplFileInfo $file */
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$rel = str_replace($this->repoRoot() . '/', '', $file->getPathname());
			$contents = file_get_contents($file->getPathname());
			$this->assertIsString($contents, "Could not read {$rel}");

			$this->assertMatchesRegularExpression(
				'/SPDX-License-Identifier:\s*EUPL-1\.2/',
				$contents,
				"{$rel} must carry SPDX-License-Identifier: EUPL-1.2"
			);
			$this->assertDoesNotMatchRegularExpression(
				'/SPDX-License-Identifier:\s*AGPL/i',
				$contents,
				"{$rel} must not declare an AGPL SPDX identifier."
			);
			$checked++;
		}

		$this->assertGreaterThan(0, $checked, 'No PHP files were scanned under lib/.');
	}

	// REUSE-IgnoreEnd

	/**
	 * The government checklist states the real licence (EUPL-1.2) and host (GitHub).
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-1.4
	 */
	public function testGovernmentChecklistStatesRealLicenceAndHost(): void {
		$doc = $this->readFile('docs/GOVERNMENT-FEATURES.md');

		$this->assertMatchesRegularExpression(
			'/EUPL[\s-]?1\.2/i',
			$doc,
			'GOVERNMENT-FEATURES.md must state the EUPL-1.2 licence.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\bAGPL\b/i',
			$doc,
			'GOVERNMENT-FEATURES.md must not state AGPL.'
		);

		// The open-source technical row must reference GitHub, never Codeberg.
		//
		// This pair used to assert the exact opposite. Codeberg was the
		// canonical host when this checklist was written; it is now an
		// unmaintained mirror, and GitHub is the only host — including for
		// issues. The assertions are INVERTED rather than deleted: this
		// document is a Programma van Eisen checklist handed to procuring
		// organisations, so naming the wrong host is a false claim to a
		// customer, which is exactly what this test class exists to prevent.
		$this->assertStringContainsString(
			'GitHub',
			$doc,
			'GOVERNMENT-FEATURES.md must reference the GitHub source host.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\bCodeberg\b/i',
			$doc,
			'GOVERNMENT-FEATURES.md must not reference Codeberg as the canonical source host.'
		);
	}

	/**
	 * css/fonts.css uses only bundled, self-hosted fonts — no external CDN URL — and
	 * every referenced woff2/woff file exists on disk.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.3
	 */
	public function testStylesheetUsesOnlyBundledFonts(): void {
		$css = $this->readFile('css/fonts.css');

		// No url() reference may use an http(s):// scheme.
		preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $matches);
		$this->assertNotEmpty($matches[1], 'css/fonts.css must contain at least one url() reference.');

		foreach ($matches[1] as $ref) {
			$ref = trim($ref);
			$this->assertDoesNotMatchRegularExpression(
				'#^https?://#i',
				$ref,
				"css/fonts.css must not load fonts from an external URL: {$ref}"
			);

			// Every font-file reference must resolve on disk (relative to css/).
			if (preg_match('/\.(woff2?|ttf|otf)$/i', $ref) === 1) {
				$path = $this->repoRoot() . '/css/' . $ref;
				$this->assertFileExists(
					$path,
					"css/fonts.css references a missing font file: css/{$ref}"
				);
			}
		}

		$this->assertStringNotContainsStringIgnoringCase(
			'jsdelivr',
			$css,
			'css/fonts.css must not reference the jsdelivr CDN.'
		);
	}

	/**
	 * The README and compliance docs describe the real self-hosted delivery and make
	 * no false CDN / "not loaded" claim.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-2.1
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-2.3
	 */
	public function testFontDocumentationMatchesBundledDelivery(): void {
		$readme = $this->readFile('README.md');

		$this->assertStringNotContainsStringIgnoringCase(
			'jsdelivr',
			$readme,
			'README.md must not claim fonts load from the jsdelivr CDN.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/Loaded via CDN|CDN-based font/i',
			$readme,
			'README.md must not describe CDN-based font delivery.'
		);
		$this->assertMatchesRegularExpression(
			'/self-hosted|bundled/i',
			$readme,
			'README.md must describe the self-hosted, bundled font delivery.'
		);

		$compliance = $this->readFile('docs/reference/compliance.md');
		$this->assertDoesNotMatchRegularExpression(
			'/Font declared but not loaded|files not loaded/i',
			$compliance,
			'compliance.md must not claim the bundled font is "not loaded".'
		);
	}

	/**
	 * The token-set count stated in README.md equals the token-sets.json inventory,
	 * and project.md does not state a different total.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.4
	 */
	public function testReadmeCountEqualsInventory(): void {
		$count = $this->tokenSetCount();
		$readme = $this->readFile('README.md');

		// Canonical bold count in the Features section: "**41 token sets**".
		$this->assertMatchesRegularExpression(
			'/\*\*(\d+) token sets\*\*/',
			$readme,
			'README.md must state a bold canonical token-set count (e.g. "**41 token sets**").'
		);
		preg_match('/\*\*(\d+) token sets\*\*/', $readme, $m);
		$this->assertSame(
			$count,
			(int)$m[1],
			'The token-set count stated in README.md must equal the token-sets.json inventory.'
		);

		// project.md must state the same total, both in prose and in the section heading.
		$project = $this->readFile('project.md');
		$this->assertMatchesRegularExpression(
			'/provides ' . $count . ' token sets/',
			$project,
			'project.md must state the real token-set total in its overview.'
		);
		$this->assertMatchesRegularExpression(
			'/## Token Sets \(' . $count . '\)/',
			$project,
			'project.md "Token Sets" heading must state the real total.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/provides 39 token sets|## Token Sets \(39\)/',
			$project,
			'project.md must not retain the stale "39 token sets" total.'
		);
	}

	/**
	 * token-audit.md scopes its verdict to the reviewed sets and never asserts a
	 * blanket production verdict over the unaudited community sets.
	 *
	 * @spec openspec/changes/fix-readiness-claims/tasks.md#task-5.5
	 */
	public function testTokenAuditScopeStatedHonestly(): void {
		$doc = $this->readFile('docs/reference/token-audit.md');

		// Must name the five manually-reviewed sets.
		foreach (['Rijkshuisstijl', 'Utrecht', 'Amsterdam', 'Den Haag', 'Rotterdam'] as $name) {
			$this->assertStringContainsString(
				$name,
				$doc,
				"token-audit.md must name the reviewed set: {$name}"
			);
		}

		// Must mark the remaining sets as not individually audited.
		$this->assertMatchesRegularExpression(
			'/not (been )?individually audited|not individually reviewed/i',
			$doc,
			'token-audit.md must mark the remaining sets as not individually audited.'
		);

		// Must not carry a blanket "APPROVED FOR PRODUCTION" verdict covering all sets.
		$this->assertDoesNotMatchRegularExpression(
			'/^\**Status\**:.*APPROVED FOR PRODUCTION\s*$/im',
			$doc,
			'token-audit.md must not carry a blanket APPROVED FOR PRODUCTION verdict.'
		);

		// Any surviving "APPROVED FOR PRODUCTION" mention must sit in a five-sets-scoped line.
		if (preg_match('/APPROVED FOR PRODUCTION/i', $doc) === 1) {
			$this->assertMatchesRegularExpression(
				'/(five|5) .{0,80}APPROVED FOR PRODUCTION|APPROVED FOR PRODUCTION.{0,120}(five|5)/is',
				$doc,
				'Any remaining APPROVED FOR PRODUCTION mention must be scoped to the five reviewed sets.'
			);
		}
	}

	/**
	 * README.md and the font-delivery compliance doc describe Marianne's
	 * real, legally-restricted situation honestly: bundled self-hosted under
	 * Etalab-2.0, restricted to French State agencies, off by default until
	 * an admin acknowledges eligibility, Inter used otherwise — and never
	 * claim it is unconditionally free/open, nor that no Marianne file ships.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testMarianneDocumentationDescribesTheGatedRestrictedSituationHonestly(): void {
		$readme = $this->readFile('README.md');
		$compliance = $this->readFile('docs/reference/compliance.md');

		foreach (['README.md' => $readme, 'docs/reference/compliance.md' => $compliance] as $label => $doc) {
			$this->assertMatchesRegularExpression(
				'/Etalab.{0,10}2\.0/i',
				$doc,
				"{$label} must state Marianne is bundled under the Etalab Open Licence 2.0."
			);
			$this->assertMatchesRegularExpression(
				"/French State agenc(y|ies)|administration de l'[ÉE]tat/i",
				$doc,
				"{$label} must state Marianne is restricted to French State agencies."
			);
			$this->assertMatchesRegularExpression(
				'/off by default|default[- ]off|inert by default/i',
				$doc,
				"{$label} must state Marianne is off by default."
			);

			// Must not claim Marianne is unconditionally free/open.
			$this->assertDoesNotMatchRegularExpression(
				'/Marianne is (a |an )?(unconditionally )?(free|open)[- ]?(source|font)?\b(?!.{0,20}(French State|reserved|restricted))/i',
				$doc,
				"{$label} must not describe Marianne as an unconditionally free/open font."
			);

			// Must not claim (pre-change wording) that no Marianne file exists.
			$this->assertDoesNotMatchRegularExpression(
				'/no (such )?Marianne file exists|no Marianne file (anywhere )?ships/i',
				$doc,
				"{$label} must not claim that no Marianne file ships in the app."
			);

			// Must not claim CDN / external-host delivery for Marianne (mirrors
			// testFontDocumentationMatchesBundledDelivery's Fira Sans guard).
			$this->assertDoesNotMatchRegularExpression(
				'/Marianne is (loaded|served|fetched) (via|from) (a )?(CDN|external)/i',
				$doc,
				"{$label} must not claim Marianne loads from a CDN or external host."
			);
		}
	}

	/**
	 * `css/systems/lasuite/marianne.css` never uses an `http://`/`https://`
	 * scheme — the gated Marianne layer is self-hosted, app-relative only.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testGatedMarianneStylesheetNeverUsesAnExternalScheme(): void {
		$css = $this->readFile('css/systems/lasuite/marianne.css');

		$this->assertDoesNotMatchRegularExpression(
			'#url\(\s*[\'"]?https?://#i',
			$css,
			'css/systems/lasuite/marianne.css must not load Marianne from an external http(s):// URL.'
		);
	}

	/**
	 * The Nextcloud refs CI actually provisions, read out of the workflow.
	 *
	 * `nextcloud-test-refs` is a JSON array embedded in YAML, e.g.
	 * `nextcloud-test-refs: '["stable32"]'`. Returned as integers so the
	 * comparison against `min-version` is numeric, not lexical — "stable9"
	 * sorts above "stable32" as a string and below it as a number, and only
	 * the second is the meaning anyone intends.
	 *
	 * @return int[] the major versions provisioned by CI, ascending
	 */
	private function testedNextcloudMajors(): array {
		$workflow = $this->readFile('.github/workflows/code-quality.yml');

		// Anchored to the start of a (possibly indented) line so the many
		// COMMENTS in this file that quote the key — including one quoting the
		// shared workflow's own default of ["stable31","stable32"] — cannot be
		// mistaken for the live setting. A comment line starts with '#'.
		$matched = preg_match(
			'/^[ \t]*nextcloud-test-refs:[ \t]*[\'"](?P<json>\[[^\]]*\])[\'"]/m',
			$workflow,
			$m
		);
		$this->assertSame(
			1,
			$matched,
			'.github/workflows/code-quality.yml must pin `nextcloud-test-refs` explicitly. '
			. 'Inheriting the shared default silently widens the tested matrix, which is how '
			. 'the floor and the matrix drifted apart in #241/#242.'
		);

		$refs = json_decode($m['json'], true);
		$this->assertIsArray($refs, 'nextcloud-test-refs must be a JSON array.');
		$this->assertNotEmpty($refs, 'nextcloud-test-refs must not be empty.');

		$majors = [];
		foreach ($refs as $ref) {
			$this->assertIsString($ref, 'Every nextcloud-test-refs entry must be a string.');
			$this->assertSame(
				1,
				preg_match('/^stable(?P<major>\d+)$/', $ref, $r),
				"nextcloud-test-refs entry '{$ref}' must be of the form stableNN."
			);
			$majors[] = (int)$r['major'];
		}

		sort($majors);
		return $majors;
	}

	/**
	 * The declared Nextcloud range and the CI matrix say the same thing.
	 *
	 * `min-version` is enforced at install time and decides which servers the
	 * app store offers this app to. A floor ABOVE the matrix makes
	 * `occ app:enable` refuse on a tested leg; a floor BELOW it advertises
	 * versions that no leg has ever exercised. This repo shipped each defect
	 * once — #241 then #242 — because nothing compared the two files.
	 *
	 * @spec openspec/specs/claim-accuracy/spec.md
	 */
	public function testDeclaredNextcloudRangeMatchesTheTestedMatrix(): void {
		$info = simplexml_load_string($this->readFile('appinfo/info.xml'));
		$this->assertNotFalse($info, 'appinfo/info.xml must be well-formed XML.');

		$min = (int)$info->dependencies->nextcloud['min-version'];
		$max = (int)$info->dependencies->nextcloud['max-version'];
		$this->assertGreaterThan(0, $min, 'appinfo/info.xml must declare <nextcloud min-version>.');
		$this->assertGreaterThan(0, $max, 'appinfo/info.xml must declare <nextcloud max-version>.');

		$tested = $this->testedNextcloudMajors();
		$lowest = $tested[0];
		$highest = $tested[\count($tested) - 1];
		$list = implode(', ', $tested);

		$this->assertGreaterThanOrEqual(
			$min,
			$lowest,
			"appinfo/info.xml declares min-version={$min}, but CI provisions stable{$lowest}. "
			. 'min-version is enforced at install time, so `occ app:enable` will REFUSE on that '
			. 'leg and the run fails later with an unrelated "app is not installed or enabled".'
		);

		$this->assertSame(
			$min,
			$lowest,
			"appinfo/info.xml declares min-version={$min}, but the lowest ref CI tests is "
			. "stable{$lowest} (matrix: {$list}). Every version between {$min} and {$lowest} is "
			. 'advertised to the app store and exercised by nothing.'
		);

		$this->assertGreaterThanOrEqual(
			$highest,
			$max,
			"appinfo/info.xml declares max-version={$max}, but CI tests stable{$highest}."
		);
	}

	/**
	 * The declared PHP floor is satisfiable on the declared Nextcloud floor.
	 *
	 * Nextcloud 32 is the first release whose own PHP floor is 8.3. Declaring
	 * `<php min-version="8.3"/>` alongside a Nextcloud floor below 32 is not a
	 * wider support range — it is two declarations that contradict each other,
	 * and the app store resolves the contradiction by offering the app to
	 * instances whose PHP it cannot run on.
	 *
	 * @spec openspec/specs/claim-accuracy/spec.md
	 */
	public function testDeclaredPhpFloorIsSatisfiableOnTheDeclaredNextcloudFloor(): void {
		$info = simplexml_load_string($this->readFile('appinfo/info.xml'));
		$this->assertNotFalse($info, 'appinfo/info.xml must be well-formed XML.');

		$php = (string)$info->dependencies->php['min-version'];
		$nc = (int)$info->dependencies->nextcloud['min-version'];

		if (version_compare($php, '8.3', '>=') === false) {
			$this->markTestSkipped('PHP floor is below 8.3 — this invariant does not apply.');
		}

		$this->assertGreaterThanOrEqual(
			32,
			$nc,
			"appinfo/info.xml declares php min-version={$php} with nextcloud min-version={$nc}. "
			. 'Nextcloud 32 is the first release whose own PHP floor is 8.3; below it the two '
			. 'declarations contradict each other.'
		);
	}

	/**
	 * Every `@e2e exclude ... covered by <Class>::<method>` in openspec/specs/
	 * names a test method that actually exists.
	 *
	 * An `@e2e exclude` is the one thing that makes gate-19 stop asking for
	 * coverage of a scenario, so it is the exact shape a blind spot takes. The
	 * gate itself only requires a non-empty REASON — any prose satisfies it.
	 * Whenever a reason claims a named PHPUnit method covers the scenario, that
	 * claim must be checkable, or the exclusion is prose standing in for a test.
	 *
	 * Deliberately narrow: exclusions whose reason does NOT name a method (e.g.
	 * "documentation invariant", "API-layer assertion") are untouched. Widening
	 * this to demand a named method everywhere would be a different decision.
	 *
	 * @spec openspec/specs/claim-accuracy/spec.md
	 */
	public function testEveryNamedE2eExclusionPointsAtATestThatExists(): void {
		$root = $this->repoRoot();
		$specs = glob($root . '/openspec/specs/*/spec.md');
		$this->assertNotEmpty($specs, 'openspec/specs/ must contain specs to scan.');

		$claims = [];
		foreach ($specs as $spec) {
			$text = file_get_contents($spec);
			$this->assertIsString($text);
			preg_match_all(
				'/covered by\s+(?P<class>[A-Za-z_][A-Za-z0-9_]*)::(?P<method>[A-Za-z_][A-Za-z0-9_]*)/',
				$text,
				$matches,
				PREG_SET_ORDER
			);
			foreach ($matches as $match) {
				$claims[] = [
					'spec' => str_replace($root . '/', '', $spec),
					'class' => $match['class'],
					'method' => $match['method'],
				];
			}
		}

		// A scan that matched nothing and a scan that is broken are the same
		// output. This repo HAS such claims, so an empty result means the regex
		// stopped working, not that the claims became true.
		$this->assertNotEmpty(
			$claims,
			'No "covered by Class::method" claims found in openspec/specs/. Either every one was '
			. 'removed, or this scanner no longer matches the format it is supposed to check.'
		);

		$index = [];
		$rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/tests'));
		foreach ($rii as $file) {
			if ($file->isFile() === false || str_ends_with($file->getFilename(), 'Test.php') === false) {
				continue;
			}

			$body = file_get_contents($file->getPathname());
			if ($body === false) {
				continue;
			}

			$index[basename($file->getFilename(), '.php')] = $body;
		}

		foreach ($claims as $claim) {
			$this->assertArrayHasKey(
				$claim['class'],
				$index,
				"{$claim['spec']} claims coverage by {$claim['class']}::{$claim['method']}, "
				. "but no tests/**/{$claim['class']}.php exists."
			);
			$this->assertStringContainsString(
				"function {$claim['method']}(",
				$index[$claim['class']],
				"{$claim['spec']} claims coverage by {$claim['class']}::{$claim['method']}, "
				. 'but that class has no such method — the exclusion is prose standing in for a test.'
			);
		}
	}
}
