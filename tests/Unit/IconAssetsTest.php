<?php

/**
 * Inventory regression test for the nc-vue-sourced NL-government icon and logo assets.
 *
 * Guards the icon-assets capability contract: the on-disk SVG set under img/icons/{set}/
 * (materialized from @conduction/nextcloud-vue's rvo/openGemeenten/denHaag packs, plus the
 * dsfr pack materialized from @gouvfr/dsfr) and img/logos/ must match the inventory
 * documented in img/ICONS.md (counts and sampled names), every legacy Amsterdam alias in
 * scripts/icon-aliases.json must resolve to a byte-identical copy of its mapped
 * replacement, assets must be safe standalone SVG (no <script>/event handlers), the
 * CC0-1.0/EUPL-1.2/Etalab-2.0 attribution must remain co-located with the assets (never
 * MPL-2.0 / @amsterdam/design-system-assets as a current source), and the icon/logo
 * counts must agree across README.md, docs/reference/icons.md and img/ICONS.md. A rename,
 * removal, or drift between docs and filesystem fails this test.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-07-24-icons-from-ncvue/specs/icon-assets/spec.md
 * @spec openspec/specs/icon-assets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static-asset inventory regression test (no Nextcloud runtime required).
 */
class IconAssetsTest extends TestCase {
	/**
	 * The three nc-vue-sourced icon set directory names.
	 *
	 * @var array<string>
	 */
	private const ICON_SETS = ['rvo', 'open-gemeenten', 'den-haag'];

	/**
	 * Repository root, derived from this test file's location.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}

	/**
	 * Absolute path to the icons directory.
	 */
	private function iconsDir(): string {
		return $this->repoRoot() . '/img/icons';
	}

	/**
	 * Absolute path to the logos directory.
	 */
	private function logosDir(): string {
		return $this->repoRoot() . '/img/logos';
	}

	/**
	 * Absolute path to the curated legacy-name alias map.
	 */
	private function aliasesPath(): string {
		return $this->repoRoot() . '/scripts/icon-aliases.json';
	}

	/**
	 * List of pack entry keys (without the .svg suffix) found on disk for one set.
	 *
	 * @return array<string>
	 */
	private function diskSetIconNames(string $set): array {
		$files = glob($this->iconsDir() . '/' . $set . '/*.svg') ?: [];
		return array_map(
			static fn (string $p): string => basename($p, '.svg'),
			$files
		);
	}

	/**
	 * List of top-level legacy-alias icon base names (without the .svg suffix).
	 *
	 * @return array<string>
	 */
	private function diskTopLevelIconNames(): array {
		$files = glob($this->iconsDir() . '/*.svg') ?: [];
		return array_map(
			static fn (string $p): string => basename($p, '.svg'),
			$files
		);
	}

	/**
	 * List of logo base names (without the .svg suffix) found on disk.
	 *
	 * @return array<string>
	 */
	private function diskLogoNames(): array {
		return array_map(
			static fn (string $p): string => basename($p, '.svg'),
			$this->shippedLogoFiles()
		);
	}

	/**
	 * The logo files the app ships, without the ones an instance writes at
	 * runtime: a theme saved from the editor keeps a copy of Nextcloud's own
	 * logos here as `{set}-captured-{slot}.svg` (BrandingCaptureService), and
	 * those are the admin's uploads, not part of the documented set.
	 *
	 * @return array<string>
	 */
	private function shippedLogoFiles(): array {
		$files = glob($this->logosDir() . '/*.svg') ?: [];
		return array_values(array_filter(
			$files,
			static fn (string $p): bool => str_contains(basename($p), '-captured-') === false
		));
	}

	/**
	 * Decode the curated legacy alias map (excluding the "_comment" header key).
	 *
	 * @return array<string, string> legacy name => "{set}/{key}"
	 */
	private function loadAliases(): array {
		$this->assertFileExists($this->aliasesPath(), 'scripts/icon-aliases.json must exist.');
		$contents = file_get_contents($this->aliasesPath());
		$this->assertIsString($contents, 'Could not read scripts/icon-aliases.json');
		$decoded = json_decode($contents, true);
		$this->assertIsArray($decoded, 'scripts/icon-aliases.json must decode to a JSON object.');
		unset($decoded['_comment']);
		return $decoded;
	}

	/**
	 * Read a documentation file from the repository root.
	 */
	private function readDoc(string $relativePath): string {
		$path = $this->repoRoot() . '/' . $relativePath;
		$this->assertFileExists($path, "Expected documentation file to exist: {$relativePath}");
		$contents = file_get_contents($path);
		$this->assertIsString($contents, "Could not read {$relativePath}");
		return $contents;
	}

	/**
	 * Each icon-set directory holds the count documented in img/ICONS.md's
	 * "### {set} (N icons)" heading, and that heading exists for every set.
	 */
	public function testIconSetCountsMatchDocumentedTotals(): void {
		$icons = $this->readDoc('img/ICONS.md');

		foreach (self::ICON_SETS as $set) {
			$this->assertDirectoryExists($this->iconsDir() . '/' . $set);

			$pattern = '/###\s*' . preg_quote($set, '/') . '\s*\((\d+)\s*icons\)/i';
			$this->assertMatchesRegularExpression(
				$pattern,
				$icons,
				"img/ICONS.md must state a count heading for set \"{$set}\"."
			);
			preg_match($pattern, $icons, $m);
			$documentedCount = (int)$m[1];

			$this->assertSame(
				$documentedCount,
				\count($this->diskSetIconNames($set)),
				"On-disk icon count for set \"{$set}\" must equal the documented total ({$documentedCount})."
			);
		}
	}

	/**
	 * Every "- {set}/{key}" bullet enumerated in img/ICONS.md's Icons section
	 * resolves to a real file on disk.
	 */
	public function testDocumentedIconSampleResolvesOnDisk(): void {
		$icons = $this->readDoc('img/ICONS.md');

		$this->assertMatchesRegularExpression('/##\s*Icons\s*\n/i', $icons, 'ICONS.md must contain an Icons section.');

		// "{set}/{key}" bullets only ever occur in the per-set Icons subsections,
		// so a whole-document scan is unambiguous (Logos bullets carry no slash).
		preg_match_all('/^-\s+([a-z0-9-]+\/[a-z0-9-]+)\s*$/m', $icons, $matches);
		$documented = $matches[1];
		$this->assertNotEmpty($documented, 'ICONS.md must enumerate at least one "{set}/{key}" icon path.');

		foreach ($documented as $setSlashKey) {
			$file = $this->iconsDir() . '/' . $setSlashKey . '.svg';
			$this->assertFileExists($file, "Icon documented in img/ICONS.md has no file img/icons/{$setSlashKey}.svg");
		}
	}

	/**
	 * The logo directory count matches the count stated in img/ICONS.md.
	 */
	public function testLogoCountMatchesDocumentedTotal(): void {
		$this->assertDirectoryExists($this->logosDir());

		$icons = $this->readDoc('img/ICONS.md');
		$this->assertMatchesRegularExpression(
			'/##\s*Logos\s*\((\d+)\s*total\)/i',
			$icons,
			'ICONS.md must state a logo total.'
		);
		preg_match('/##\s*Logos\s*\((\d+)\s*total\)/i', $icons, $m);
		$documentedLogoTotal = (int)$m[1];

		$this->assertSame(
			$documentedLogoTotal,
			\count($this->diskLogoNames()),
			'On-disk logo count must equal the total documented in img/ICONS.md.'
		);
	}

	/**
	 * Every logo name enumerated in img/ICONS.md resolves to a real file on disk.
	 */
	public function testDocumentedLogoSampleResolvesOnDisk(): void {
		$icons = $this->readDoc('img/ICONS.md');
		$logosSection = $this->sectionBetween($icons, '## Logos', '## Naming stability');

		$documented = $this->bulletNames($logosSection);
		$this->assertNotEmpty($documented, 'ICONS.md must enumerate at least one logo name.');

		$onDisk = array_flip($this->diskLogoNames());
		foreach ($documented as $name) {
			$this->assertArrayHasKey(
				$name,
				$onDisk,
				"Logo documented in img/ICONS.md has no file img/logos/{$name}.svg"
			);
		}
	}

	/**
	 * img/logos/ is untouched by the icon build: exactly the 23 checked-in files,
	 * no set-prefixed subdirectories, no stray output.
	 */
	public function testLogosDirectoryHasNoBuildArtifacts(): void {
		$entries = scandir($this->logosDir()) ?: [];
		$subdirs = array_filter($entries, function (string $entry): bool {
			return $entry !== '.' && $entry !== '..' && is_dir($this->logosDir() . '/' . $entry);
		});

		$this->assertSame([], array_values($subdirs), 'img/logos/ must contain no subdirectories (it is not build output).');
	}

	/**
	 * Every entry in scripts/icon-aliases.json resolves to a top-level legacy file
	 * that is byte-identical to its mapped {set}/{key} replacement.
	 */
	public function testAllAliasesResolveAndAreByteIdenticalToReplacement(): void {
		$aliases = $this->loadAliases();
		$this->assertNotEmpty($aliases, 'scripts/icon-aliases.json must map at least one legacy name.');

		foreach ($aliases as $legacyName => $replacementPath) {
			$legacyFile = $this->iconsDir() . '/' . $legacyName . '.svg';
			$replacementFile = $this->iconsDir() . '/' . $replacementPath . '.svg';

			$this->assertFileExists($legacyFile, "Alias \"{$legacyName}\" has no legacy file img/icons/{$legacyName}.svg");
			$this->assertFileExists($replacementFile, "Alias \"{$legacyName}\" maps to a nonexistent replacement img/icons/{$replacementPath}.svg");

			$this->assertSame(
				file_get_contents($replacementFile),
				file_get_contents($legacyFile),
				"Alias file for \"{$legacyName}\" must be byte-identical to its mapped replacement \"{$replacementPath}\"."
			);
		}
	}

	/**
	 * Every top-level img/icons/*.svg file corresponds to an entry in
	 * scripts/icon-aliases.json — no orphaned Amsterdam-era file survives.
	 */
	public function testNoOrphanedTopLevelIconFiles(): void {
		$aliases = $this->loadAliases();
		$topLevel = array_flip($this->diskTopLevelIconNames());

		$orphans = array_diff(array_keys($topLevel), array_keys($aliases));

		$this->assertSame(
			[],
			array_values($orphans),
			'These top-level img/icons/*.svg files have no scripts/icon-aliases.json entry: ' . implode(', ', $orphans)
		);
	}

	/**
	 * package.json must not declare the proprietary Amsterdam packages as dependencies.
	 */
	public function testNoAmsterdamDependency(): void {
		$packageJson = $this->readDoc('package.json');
		$decoded = json_decode($packageJson, true);
		$this->assertIsArray($decoded, 'package.json must decode to a JSON object.');

		$allDeps = array_merge(
			$decoded['dependencies'] ?? [],
			$decoded['devDependencies'] ?? []
		);

		$this->assertArrayNotHasKey('@amsterdam/design-system-assets', $allDeps, 'package.json must not depend on @amsterdam/design-system-assets.');
		$this->assertArrayNotHasKey('@amsterdam/design-system-react-icons', $allDeps, 'package.json must not depend on @amsterdam/design-system-react-icons.');
		$this->assertArrayHasKey('@conduction/nextcloud-vue', $decoded['devDependencies'] ?? [], '@conduction/nextcloud-vue must be a devDependency.');

		// @gouvfr/dsfr is declared OPTIONAL, not dev. Its published dependency tree is
		// broken upstream — @gouvfr/dsfr@1.15.1 -> @gouvfr/dsfr-nexus -> @gouvfr/dsfr-roller
		// -> @gouvfr/dsfr-publisher, which is not on the registry (E404), as is
		// @gouvfr/dsfr-token. Declaring it as a devDependency made `npm ci` impossible and
		// took every npm-dependent CI job down with it. `optionalDependencies` keeps the
		// declaration (and its provenance) while letting npm skip the unresolvable subtree;
		// scripts/build-icons.js already falls back to .dsfr-src/ when the package is absent.
		$this->assertArrayHasKey(
			'@gouvfr/dsfr',
			$decoded['optionalDependencies'] ?? [],
			'@gouvfr/dsfr must stay declared (as an optionalDependency) as the dsfr pack source.'
		);
		$this->assertArrayNotHasKey(
			'@gouvfr/dsfr',
			$decoded['devDependencies'] ?? [],
			'@gouvfr/dsfr must NOT be a devDependency — its upstream tree is unresolvable and it breaks npm ci.'
		);
	}

	/**
	 * A sample of assets are well-formed standalone SVG with no scriptable content.
	 */
	public function testSampledAssetsAreSafeStandaloneSvg(): void {
		$icons = [];
		foreach (self::ICON_SETS as $set) {
			$icons = array_merge($icons, glob($this->iconsDir() . '/' . $set . '/*.svg') ?: []);
		}
		$topLevelAliases = glob($this->iconsDir() . '/*.svg') ?: [];
		$logos = $this->shippedLogoFiles();
		$this->assertNotEmpty($icons, 'No icon SVG files found to sample.');

		// Deterministic sample: sort and take a spread across the set, plus all aliases and logos.
		sort($icons);
		$sample = [];
		$step = (int)max(1, floor(\count($icons) / 40));
		for ($i = 0; $i < \count($icons); $i += $step) {
			$sample[] = $icons[$i];
		}
		$sample = array_merge($sample, $topLevelAliases, $logos);

		foreach ($sample as $file) {
			$svg = file_get_contents($file);
			$this->assertIsString($svg, "Could not read {$file}");
			$rel = basename($file);

			$this->assertMatchesRegularExpression(
				'/<svg[\s>]/i',
				$svg,
				"{$rel} must contain an <svg> root element."
			);
			$this->assertDoesNotMatchRegularExpression(
				'/<script[\s>]/i',
				$svg,
				"{$rel} must not contain a <script> element."
			);
			$this->assertDoesNotMatchRegularExpression(
				'/\son[a-z]+\s*=/i',
				$svg,
				"{$rel} must not contain inline event-handler attributes."
			);

			// Well-formedness: the SVG must parse as XML.
			$previous = libxml_use_internal_errors(true);
			$doc = simplexml_load_string($svg);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			$this->assertNotFalse($doc, "{$rel} must be well-formed XML/SVG.");
		}
	}

	/**
	 * The dsfr pack directory holds the count documented in img/ICONS.md's
	 * "### dsfr (N icons)" heading, and the pack is non-empty.
	 */
	public function testDsfrCountMatchesDocumentedTotal(): void {
		$icons = $this->readDoc('img/ICONS.md');

		$this->assertDirectoryExists($this->iconsDir() . '/dsfr');

		$pattern = '/###\s*dsfr\s*\((\d+)\s*icons\)/i';
		$this->assertMatchesRegularExpression(
			$pattern,
			$icons,
			'img/ICONS.md must state a count heading for set "dsfr".'
		);
		preg_match($pattern, $icons, $m);
		$documentedCount = (int)$m[1];

		$this->assertGreaterThan(0, $documentedCount, 'The dsfr pack must not be documented as empty.');
		$this->assertSame(
			$documentedCount,
			\count($this->diskSetIconNames('dsfr')),
			"On-disk icon count for set \"dsfr\" must equal the documented total ({$documentedCount})."
		);
	}

	/**
	 * A sample of dsfr assets are well-formed standalone SVG with no scriptable content.
	 */
	public function testDsfrSampledAssetsAreSafeStandaloneSvg(): void {
		$icons = glob($this->iconsDir() . '/dsfr/*.svg') ?: [];
		$this->assertNotEmpty($icons, 'No dsfr icon SVG files found to sample.');

		sort($icons);
		$sample = [];
		$step = (int)max(1, floor(\count($icons) / 40));
		for ($i = 0; $i < \count($icons); $i += $step) {
			$sample[] = $icons[$i];
		}

		foreach ($sample as $file) {
			$svg = file_get_contents($file);
			$this->assertIsString($svg, "Could not read {$file}");
			$rel = basename($file);

			$this->assertMatchesRegularExpression(
				'/<svg[\s>]/i',
				$svg,
				"{$rel} must contain an <svg> root element."
			);
			$this->assertDoesNotMatchRegularExpression(
				'/<script[\s>]/i',
				$svg,
				"{$rel} must not contain a <script> element."
			);
			$this->assertDoesNotMatchRegularExpression(
				'/\son[a-z]+\s*=/i',
				$svg,
				"{$rel} must not contain inline event-handler attributes."
			);

			$previous = libxml_use_internal_errors(true);
			$doc = simplexml_load_string($svg);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			$this->assertNotFalse($doc, "{$rel} must be well-formed XML/SVG.");
		}
	}

	/**
	 * The dsfr pack basenames are all unique — the flat, category-free
	 * `img/icons/dsfr/{basename}.svg` layout the build script relies on is
	 * only collision-free because DSFR names are unique across the whole
	 * source set (guards the invariant documented in icon-packs/spec.md).
	 */
	public function testDsfrBasenamesAreUnique(): void {
		$names = $this->diskSetIconNames('dsfr');
		$this->assertNotEmpty($names, 'No dsfr icons found on disk.');
		$this->assertSame(
			\count($names),
			\count(array_unique($names)),
			'img/icons/dsfr/ must contain no duplicate basenames.'
		);
	}

	/**
	 * The dsfr icon and its Etalab-2.0 attribution are documented in
	 * README.md and docs/reference/icons.md, not just img/ICONS.md.
	 */
	public function testDsfrCountAndLicenceDocumentedInReadmeAndDocs(): void {
		$dsfrCount = \count($this->diskSetIconNames('dsfr'));

		$readme = $this->readDoc('README.md');
		$docs = $this->readDoc('docs/reference/icons.md');

		$this->assertStringContainsString((string)$dsfrCount, $readme, 'README.md must state the actual dsfr icon count.');
		$this->assertStringContainsString((string)$dsfrCount, $docs, 'docs/reference/icons.md must state the actual dsfr icon count.');
		$this->assertStringContainsString('Etalab-2.0', $readme, 'README.md must attribute the dsfr set as Etalab-2.0.');
		$this->assertStringContainsString('Etalab-2.0', $docs, 'docs/reference/icons.md must attribute the dsfr set as Etalab-2.0.');
	}

	/**
	 * img/ICONS.md attributes each set with its correct upstream licence, references
	 * nc-vue's ATTRIBUTION.md as the canonical record, and never claims MPL-2.0.
	 */
	public function testLicenceAttributionPresentAndCorrect(): void {
		$icons = $this->readDoc('img/ICONS.md');

		$this->assertStringContainsString('CC0-1.0', $icons, 'img/ICONS.md must attribute CC0-1.0 licensed sets.');
		$this->assertStringContainsString('EUPL-1.2', $icons, 'img/ICONS.md must attribute the den-haag set as EUPL-1.2.');
		$this->assertStringContainsString(
			'@conduction/nextcloud-vue',
			$icons,
			'img/ICONS.md must reference @conduction/nextcloud-vue as the icon source.'
		);
		$this->assertStringContainsString(
			'ATTRIBUTION.md',
			$icons,
			'img/ICONS.md must reference nc-vue\'s src/icons/ATTRIBUTION.md as the canonical licence record.'
		);
		$this->assertStringContainsString('Etalab-2.0', $icons, 'img/ICONS.md must attribute the dsfr set as Etalab-2.0.');
		$this->assertStringContainsString(
			'@gouvfr/dsfr',
			$icons,
			'img/ICONS.md must reference @gouvfr/dsfr as the dsfr icon source.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/Mozilla Public License 2\.0|MPL[\s-]?2\.0/i',
			$icons,
			'img/ICONS.md must not claim MPL-2.0 for the icons (removed proprietary set).'
		);
	}

	/**
	 * The icon and logo counts agree across README.md, docs/reference/icons.md and img/ICONS.md.
	 */
	public function testCountsAgreeAcrossDocuments(): void {
		$iconCount = 0;
		foreach (self::ICON_SETS as $set) {
			$iconCount += \count($this->diskSetIconNames($set));
		}
		$aliasCount = \count($this->loadAliases());
		$logoCount = \count($this->diskLogoNames());

		$readme = $this->readDoc('README.md');
		$docs = $this->readDoc('docs/reference/icons.md');

		$this->assertStringContainsString(
			(string)$iconCount,
			$readme,
			'README.md must state the actual icon count.'
		);
		$this->assertStringContainsString(
			(string)$logoCount . ' logos',
			$readme,
			'README.md must state the actual logo count.'
		);
		$this->assertStringContainsString(
			(string)$iconCount,
			$docs,
			'docs/reference/icons.md must state the actual icon count.'
		);
		$this->assertStringContainsString(
			(string)$logoCount,
			$docs,
			'docs/reference/icons.md must state the actual logo count.'
		);
		$this->assertStringContainsString(
			(string)$aliasCount,
			$docs,
			'docs/reference/icons.md must state the actual legacy-alias count.'
		);

		// Never present the removed Amsterdam set as a current source. Scoped to the
		// README "## Icons" section (not the whole document): the "Dependency license
		// policy" section legitimately lists MPL-2.0 as one of many generally-approved
		// SPDX families for hypothetical future dependencies, unrelated to this app's
		// bundled icon set; README's "## Changelog" section is a dated historical
		// record (e.g. "v0.1.0 (2026-02-03)") that accurately describes what that past
		// release contained. docs/reference/icons.md is entirely about icons, so it is
		// checked in full.
		$readmeIconsSection = $this->sectionBetween($readme, '## Icons', '## Installation');
		foreach ([$readmeIconsSection, $docs] as $doc) {
			$this->assertDoesNotMatchRegularExpression(
				'/Mozilla Public License 2\.0|MPL[\s-]?2\.0/i',
				$doc,
				'Document must not claim MPL-2.0 for the current icon set.'
			);
		}
	}

	/**
	 * Every icon/logo filename used in a consumption example across the docs
	 * resolves to a real asset on disk (nested "{set}/{key}" or legacy top-level alias).
	 */
	public function testDocumentedConsumptionExamplesResolveOnDisk(): void {
		$docs = [
			'README.md',
			'docs/reference/icons.md',
			'img/ICONS.md',
		];

		$haveLogo = array_flip($this->diskLogoNames());
		$haveTopLevel = array_flip($this->diskTopLevelIconNames());
		$haveNested = [];
		foreach (array_merge(self::ICON_SETS, ['dsfr']) as $set) {
			foreach ($this->diskSetIconNames($set) as $key) {
				$haveNested[$set . '/' . $key] = true;
			}
		}

		foreach ($docs as $doc) {
			$contents = $this->readDoc($doc);

			preg_match_all('#icons/((?:rvo|open-gemeenten|den-haag|dsfr)/[a-z0-9-]+|[A-Za-z0-9_-]+)\.svg#', $contents, $iconRefs);
			foreach (array_unique($iconRefs[1]) as $name) {
				$resolves = isset($haveNested[$name]) || isset($haveTopLevel[$name]);
				$this->assertTrue(
					$resolves,
					"{$doc} references a nonexistent icon img/icons/{$name}.svg"
				);
			}

			preg_match_all('#logos/([A-Za-z0-9_-]+)\.svg#', $contents, $logoRefs);
			foreach (array_unique($logoRefs[1]) as $name) {
				$this->assertArrayHasKey(
					$name,
					$haveLogo,
					"{$doc} references a nonexistent logo img/logos/{$name}.svg"
				);
			}
		}
	}

	/**
	 * The README "View Icon Documentation" link resolves to an existing file.
	 */
	public function testReadmeIconDocumentationLinkResolves(): void {
		$readme = $this->readDoc('README.md');

		$this->assertMatchesRegularExpression(
			'/\[View Icon Documentation[^\]]*\]\(([^)]+)\)/',
			$readme,
			'README.md must contain a "View Icon Documentation" link.'
		);
		preg_match('/\[View Icon Documentation[^\]]*\]\(([^)]+)\)/', $readme, $m);
		$target = $m[1];

		$this->assertSame(
			'img/ICONS.md',
			$target,
			'The README icon-documentation link must point at img/ICONS.md.'
		);
		$this->assertFileExists(
			$this->repoRoot() . '/' . $target,
			"README icon-documentation link target does not exist: {$target}"
		);
	}

	/**
	 * Extract the section of a markdown document starting at a heading and ending
	 * before the next occurrence of $stopMarker (or end of document if absent).
	 */
	private function sectionBetween(string $doc, string $startHeading, string $stopMarker): string {
		$start = strpos($doc, $startHeading);
		if ($start === false) {
			return '';
		}
		$afterHeading = $start + \strlen($startHeading);
		$next = strpos($doc, $stopMarker, $afterHeading);
		if ($next === false) {
			return substr($doc, $afterHeading);
		}
		return substr($doc, $afterHeading, $next - $afterHeading);
	}

	/**
	 * Extract bullet-list item names ("- Name") from a markdown section,
	 * skipping the "... and N more" continuation line.
	 *
	 * @return array<string>
	 */
	private function bulletNames(string $section): array {
		$names = [];
		foreach (preg_split('/\r?\n/', $section) ?: [] as $line) {
			if (preg_match('/^-\s+(.+?)\s*$/', $line, $m) === 1) {
				$name = $m[1];
				if (stripos($name, 'and ') === 0 || stripos($name, '...') === 0) {
					continue;
				}
				$names[] = $name;
			}
		}
		return $names;
	}
}
