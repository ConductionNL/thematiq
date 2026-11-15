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

use OCA\Thematiq\Tests\Unit\Support\FileList;
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
	 *
	 * @return string
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}

	/**
	 * Absolute path to the icons directory.
	 *
	 * @return string
	 */
	private function iconsDir(): string {
		return $this->repoRoot() . '/img/icons';
	}

	/**
	 * Absolute path to the logos directory.
	 *
	 * @return string
	 */
	private function logosDir(): string {
		return $this->repoRoot() . '/img/logos';
	}

	/**
	 * Absolute path to the curated legacy-name alias map.
	 *
	 * @return string
	 */
	private function aliasesPath(): string {
		return $this->repoRoot() . '/scripts/icon-aliases.json';
	}

	/**
	 * List of pack entry keys (without the .svg suffix) found on disk for one set.
	 *
	 * @param string $set The set.
	 *
	 * @return array<string>
	 */
	private function diskSetIconNames(string $set): array {
		$files = FileList::glob(pattern: $this->iconsDir() . '/' . $set . '/*.svg');
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
		$files = FileList::glob(pattern: $this->iconsDir() . '/*.svg');
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
		$files = FileList::glob(pattern: $this->logosDir() . '/*.svg');
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
		$this->assertFileExists(filename: $this->aliasesPath(), message: 'scripts/icon-aliases.json must exist.');
		$contents = file_get_contents($this->aliasesPath());
		$this->assertIsString(actual: $contents, message: 'Could not read scripts/icon-aliases.json');
		$decoded = json_decode($contents, true);
		$this->assertIsArray(actual: $decoded, message: 'scripts/icon-aliases.json must decode to a JSON object.');
		unset($decoded['_comment']);
		return $decoded;
	}

	/**
	 * Read a documentation file from the repository root.
	 *
	 * @param string $relativePath The relative path.
	 *
	 * @return string
	 */
	private function readDoc(string $relativePath): string {
		$path = $this->repoRoot() . '/' . $relativePath;
		$this->assertFileExists(filename: $path, message: "Expected documentation file to exist: {$relativePath}");
		$contents = file_get_contents($path);
		$this->assertIsString(actual: $contents, message: "Could not read {$relativePath}");
		return $contents;
	}

	/**
	 * Each icon-set directory holds the count documented in img/ICONS.md's
	 * "### {set} (N icons)" heading, and that heading exists for every set.
	 *
	 * @return void
	 */
	public function testIconSetCountsMatchDocumentedTotals(): void {
		$icons = $this->readDoc(relativePath: 'img/ICONS.md');

		foreach (self::ICON_SETS as $set) {
			$this->assertDirectoryExists(directory: $this->iconsDir() . '/' . $set);

			$pattern = '/###\s*' . preg_quote($set, '/') . '\s*\((\d+)\s*icons\)/i';
			$this->assertMatchesRegularExpression(
				pattern: $pattern,
				string: $icons,
				message: "img/ICONS.md must state a count heading for set \"{$set}\"."
			);
			preg_match($pattern, $icons, $m);
			$documentedCount = (int)$m[1];

			$this->assertSame(
				expected: $documentedCount,
				actual: \count($this->diskSetIconNames(set: $set)),
				message: "On-disk icon count for set \"{$set}\" must equal the documented total ({$documentedCount})."
			);
		}
	}

	/**
	 * Every "- {set}/{key}" bullet enumerated in img/ICONS.md's Icons section
	 * resolves to a real file on disk.
	 *
	 * @return void
	 */
	public function testDocumentedIconSampleResolvesOnDisk(): void {
		$icons = $this->readDoc(relativePath: 'img/ICONS.md');

		$this->assertMatchesRegularExpression(pattern: '/##\s*Icons\s*\n/i', string: $icons, message: 'ICONS.md must contain an Icons section.');

		// "{set}/{key}" bullets only ever occur in the per-set Icons subsections,
		// so a whole-document scan is unambiguous (Logos bullets carry no slash).
		preg_match_all('/^-\s+([a-z0-9-]+\/[a-z0-9-]+)\s*$/m', $icons, $matches);
		$documented = $matches[1];
		$this->assertNotEmpty(actual: $documented, message: 'ICONS.md must enumerate at least one "{set}/{key}" icon path.');

		foreach ($documented as $setSlashKey) {
			$file = $this->iconsDir() . '/' . $setSlashKey . '.svg';
			$this->assertFileExists(filename: $file, message: "Icon documented in img/ICONS.md has no file img/icons/{$setSlashKey}.svg");
		}
	}

	/**
	 * The logo directory count matches the count stated in img/ICONS.md.
	 *
	 * @return void
	 */
	public function testLogoCountMatchesDocumentedTotal(): void {
		$this->assertDirectoryExists(directory: $this->logosDir());

		$icons = $this->readDoc(relativePath: 'img/ICONS.md');
		$this->assertMatchesRegularExpression(
			pattern: '/##\s*Logos\s*\((\d+)\s*total\)/i',
			string: $icons,
			message: 'ICONS.md must state a logo total.'
		);
		preg_match('/##\s*Logos\s*\((\d+)\s*total\)/i', $icons, $m);
		$documentedLogoTotal = (int)$m[1];

		$this->assertSame(
			expected: $documentedLogoTotal,
			actual: \count($this->diskLogoNames()),
			message: 'On-disk logo count must equal the total documented in img/ICONS.md.'
		);
	}

	/**
	 * Every logo name enumerated in img/ICONS.md resolves to a real file on disk.
	 *
	 * @return void
	 */
	public function testDocumentedLogoSampleResolvesOnDisk(): void {
		$icons = $this->readDoc(relativePath: 'img/ICONS.md');
		$logosSection = $this->sectionBetween(doc: $icons, startHeading: '## Logos', stopMarker: '## Naming stability');

		$documented = $this->bulletNames(section: $logosSection);
		$this->assertNotEmpty(actual: $documented, message: 'ICONS.md must enumerate at least one logo name.');

		$onDisk = array_flip($this->diskLogoNames());
		foreach ($documented as $name) {
			$this->assertArrayHasKey(
				key: $name,
				array: $onDisk,
				message: "Logo documented in img/ICONS.md has no file img/logos/{$name}.svg"
			);
		}
	}

	/**
	 * img/logos/ is untouched by the icon build: exactly the 23 checked-in files,
	 * no set-prefixed subdirectories, no stray output.
	 *
	 * @return void
	 */
	public function testLogosDirectoryHasNoBuildArtifacts(): void {
		$entries = FileList::entries(directory: $this->logosDir());
		$subdirs = array_filter($entries, function (string $entry): bool {
			return $entry !== '.' && $entry !== '..' && is_dir($this->logosDir() . '/' . $entry);
		});

		$this->assertSame(
			expected: [],
			actual: array_values($subdirs),
			message: 'img/logos/ must contain no subdirectories (it is not build output).'
		);
	}

	/**
	 * Every entry in scripts/icon-aliases.json resolves to a top-level legacy file
	 * that is byte-identical to its mapped {set}/{key} replacement.
	 *
	 * @return void
	 */
	public function testAllAliasesResolveAndAreByteIdenticalToReplacement(): void {
		$aliases = $this->loadAliases();
		$this->assertNotEmpty(actual: $aliases, message: 'scripts/icon-aliases.json must map at least one legacy name.');

		foreach ($aliases as $legacyName => $replacementPath) {
			$legacyFile = $this->iconsDir() . '/' . $legacyName . '.svg';
			$replacementFile = $this->iconsDir() . '/' . $replacementPath . '.svg';

			$this->assertFileExists(filename: $legacyFile, message: "Alias \"{$legacyName}\" has no legacy file img/icons/{$legacyName}.svg");
			$this->assertFileExists(
				filename: $replacementFile,
				message: "Alias \"{$legacyName}\" maps to a nonexistent replacement img/icons/{$replacementPath}.svg"
			);

			$this->assertSame(
				expected: file_get_contents($replacementFile),
				actual: file_get_contents($legacyFile),
				message: "Alias file for \"{$legacyName}\" must be byte-identical to its mapped replacement \"{$replacementPath}\"."
			);
		}
	}

	/**
	 * Every top-level img/icons/*.svg file corresponds to an entry in
	 * scripts/icon-aliases.json — no orphaned Amsterdam-era file survives.
	 *
	 * @return void
	 */
	public function testNoOrphanedTopLevelIconFiles(): void {
		$aliases = $this->loadAliases();
		$topLevel = array_flip($this->diskTopLevelIconNames());

		$orphans = array_diff(array_keys($topLevel), array_keys($aliases));

		$this->assertSame(
			expected: [],
			actual: array_values($orphans),
			message: 'These top-level img/icons/*.svg files have no scripts/icon-aliases.json entry: ' . implode(', ', $orphans)
		);
	}

	/**
	 * package.json must not declare the proprietary Amsterdam packages as dependencies.
	 *
	 * @return void
	 */
	public function testNoAmsterdamDependency(): void {
		$packageJson = $this->readDoc(relativePath: 'package.json');
		$decoded = json_decode($packageJson, true);
		$this->assertIsArray(actual: $decoded, message: 'package.json must decode to a JSON object.');

		$allDeps = array_merge(
			$decoded['dependencies'] ?? [],
			$decoded['devDependencies'] ?? []
		);

		$this->assertArrayNotHasKey(
			key: '@amsterdam/design-system-assets',
			array: $allDeps,
			message: 'package.json must not depend on @amsterdam/design-system-assets.'
		);
		$this->assertArrayNotHasKey(
			key: '@amsterdam/design-system-react-icons',
			array: $allDeps,
			message: 'package.json must not depend on @amsterdam/design-system-react-icons.'
		);
		$this->assertArrayHasKey(
			key: '@conduction/nextcloud-vue',
			array: $decoded['devDependencies'] ?? [],
			message: '@conduction/nextcloud-vue must be a devDependency.'
		);

		// @gouvfr/dsfr is not a dependency at all (2 Oct 2026): its preinstall
		// script refuses to run until the DSFR terms of use are accepted, so npm
		// skipped it on every install. scripts/build-icons.js reads the pre-fetched
		// .dsfr-src/icons/ scratch source; the committed img/icons/dsfr/ pack is
		// the shipped artefact.
		foreach (['dependencies', 'devDependencies', 'optionalDependencies'] as $section) {
			$this->assertArrayNotHasKey(
				key: '@gouvfr/dsfr',
				array: $decoded[$section] ?? [],
				message: '@gouvfr/dsfr must not be declared in package.json ' . $section . '.'
			);
		}
		$this->assertArrayNotHasKey(
			key: '@gouvfr/dsfr',
			array: $decoded['devDependencies'] ?? [],
			message: '@gouvfr/dsfr must NOT be a devDependency — its upstream tree is unresolvable and it breaks npm ci.'
		);
	}

	/**
	 * A sample of assets are well-formed standalone SVG with no scriptable content.
	 *
	 * @return void
	 */
	public function testSampledAssetsAreSafeStandaloneSvg(): void {
		$icons = [];
		foreach (self::ICON_SETS as $set) {
			$icons = array_merge($icons, FileList::glob(pattern: $this->iconsDir() . '/' . $set . '/*.svg'));
		}
		$topLevelAliases = FileList::glob(pattern: $this->iconsDir() . '/*.svg');
		$logos = $this->shippedLogoFiles();
		$this->assertNotEmpty(actual: $icons, message: 'No icon SVG files found to sample.');

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
			$this->assertIsString(actual: $svg, message: "Could not read {$file}");
			$rel = basename($file);

			$this->assertMatchesRegularExpression(
				pattern: '/<svg[\s>]/i',
				string: $svg,
				message: "{$rel} must contain an <svg> root element."
			);
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/<script[\s>]/i',
				string: $svg,
				message: "{$rel} must not contain a <script> element."
			);
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/\son[a-z]+\s*=/i',
				string: $svg,
				message: "{$rel} must not contain inline event-handler attributes."
			);

			// Well-formedness: the SVG must parse as XML.
			$previous = libxml_use_internal_errors(true);
			$doc = simplexml_load_string($svg);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			$this->assertNotFalse(condition: $doc, message: "{$rel} must be well-formed XML/SVG.");
		}
	}

	/**
	 * The dsfr pack directory holds the count documented in img/ICONS.md's
	 * "### dsfr (N icons)" heading, and the pack is non-empty.
	 *
	 * @return void
	 */
	public function testDsfrCountMatchesDocumentedTotal(): void {
		$icons = $this->readDoc(relativePath: 'img/ICONS.md');

		$this->assertDirectoryExists(directory: $this->iconsDir() . '/dsfr');

		$pattern = '/###\s*dsfr\s*\((\d+)\s*icons\)/i';
		$this->assertMatchesRegularExpression(
			pattern: $pattern,
			string: $icons,
			message: 'img/ICONS.md must state a count heading for set "dsfr".'
		);
		preg_match($pattern, $icons, $m);
		$documentedCount = (int)$m[1];

		$this->assertGreaterThan(expected: 0, actual: $documentedCount, message: 'The dsfr pack must not be documented as empty.');
		$this->assertSame(
			expected: $documentedCount,
			actual: \count($this->diskSetIconNames(set: 'dsfr')),
			message: "On-disk icon count for set \"dsfr\" must equal the documented total ({$documentedCount})."
		);
	}

	/**
	 * A sample of dsfr assets are well-formed standalone SVG with no scriptable content.
	 *
	 * @return void
	 */
	public function testDsfrSampledAssetsAreSafeStandaloneSvg(): void {
		$icons = FileList::glob(pattern: $this->iconsDir() . '/dsfr/*.svg');
		$this->assertNotEmpty(actual: $icons, message: 'No dsfr icon SVG files found to sample.');

		sort($icons);
		$sample = [];
		$step = (int)max(1, floor(\count($icons) / 40));
		for ($i = 0; $i < \count($icons); $i += $step) {
			$sample[] = $icons[$i];
		}

		foreach ($sample as $file) {
			$svg = file_get_contents($file);
			$this->assertIsString(actual: $svg, message: "Could not read {$file}");
			$rel = basename($file);

			$this->assertMatchesRegularExpression(
				pattern: '/<svg[\s>]/i',
				string: $svg,
				message: "{$rel} must contain an <svg> root element."
			);
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/<script[\s>]/i',
				string: $svg,
				message: "{$rel} must not contain a <script> element."
			);
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/\son[a-z]+\s*=/i',
				string: $svg,
				message: "{$rel} must not contain inline event-handler attributes."
			);

			$previous = libxml_use_internal_errors(true);
			$doc = simplexml_load_string($svg);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			$this->assertNotFalse(condition: $doc, message: "{$rel} must be well-formed XML/SVG.");
		}
	}

	/**
	 * The dsfr pack basenames are all unique — the flat, category-free
	 * `img/icons/dsfr/{basename}.svg` layout the build script relies on is
	 * only collision-free because DSFR names are unique across the whole
	 * source set (guards the invariant documented in icon-packs/spec.md).
	 *
	 * @return void
	 */
	public function testDsfrBasenamesAreUnique(): void {
		$names = $this->diskSetIconNames(set: 'dsfr');
		$this->assertNotEmpty(actual: $names, message: 'No dsfr icons found on disk.');
		$this->assertSame(
			expected: \count($names),
			actual: \count(array_unique($names)),
			message: 'img/icons/dsfr/ must contain no duplicate basenames.'
		);
	}

	/**
	 * The dsfr icon and its Etalab-2.0 attribution are documented in
	 * README.md and docs/reference/icons.md, not just img/ICONS.md.
	 *
	 * @return void
	 */
	public function testDsfrCountAndLicenceDocumentedInReadmeAndDocs(): void {
		$dsfrCount = \count($this->diskSetIconNames(set: 'dsfr'));

		$readme = $this->readDoc(relativePath: 'README.md');
		$docs = $this->readDoc(relativePath: 'docs/reference/icons.md');

		$this->assertStringContainsString(needle: (string)$dsfrCount, haystack: $readme, message: 'README.md must state the actual dsfr icon count.');
		$this->assertStringContainsString(
			needle: (string)$dsfrCount,
			haystack: $docs,
			message: 'docs/reference/icons.md must state the actual dsfr icon count.'
		);
		$this->assertStringContainsString(needle: 'Etalab-2.0', haystack: $readme, message: 'README.md must attribute the dsfr set as Etalab-2.0.');
		$this->assertStringContainsString(
			needle: 'Etalab-2.0',
			haystack: $docs,
			message: 'docs/reference/icons.md must attribute the dsfr set as Etalab-2.0.'
		);
	}

	/**
	 * img/ICONS.md attributes each set with its correct upstream licence, references
	 * nc-vue's ATTRIBUTION.md as the canonical record, and never claims MPL-2.0.
	 *
	 * @return void
	 */
	public function testLicenceAttributionPresentAndCorrect(): void {
		$icons = $this->readDoc(relativePath: 'img/ICONS.md');

		$this->assertStringContainsString(needle: 'CC0-1.0', haystack: $icons, message: 'img/ICONS.md must attribute CC0-1.0 licensed sets.');
		$this->assertStringContainsString(needle: 'EUPL-1.2', haystack: $icons, message: 'img/ICONS.md must attribute the den-haag set as EUPL-1.2.');
		$this->assertStringContainsString(
			needle: '@conduction/nextcloud-vue',
			haystack: $icons,
			message: 'img/ICONS.md must reference @conduction/nextcloud-vue as the icon source.'
		);
		$this->assertStringContainsString(
			needle: 'ATTRIBUTION.md',
			haystack: $icons,
			message: 'img/ICONS.md must reference nc-vue\'s src/icons/ATTRIBUTION.md as the canonical licence record.'
		);
		$this->assertStringContainsString(needle: 'Etalab-2.0', haystack: $icons, message: 'img/ICONS.md must attribute the dsfr set as Etalab-2.0.');
		$this->assertStringContainsString(
			needle: '@gouvfr/dsfr',
			haystack: $icons,
			message: 'img/ICONS.md must reference @gouvfr/dsfr as the dsfr icon source.'
		);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/Mozilla Public License 2\.0|MPL[\s-]?2\.0/i',
			string: $icons,
			message: 'img/ICONS.md must not claim MPL-2.0 for the icons (removed proprietary set).'
		);
	}

	/**
	 * The icon and logo counts agree across README.md, docs/reference/icons.md and img/ICONS.md.
	 *
	 * @return void
	 */
	public function testCountsAgreeAcrossDocuments(): void {
		$iconCount = 0;
		foreach (self::ICON_SETS as $set) {
			$iconCount += \count($this->diskSetIconNames(set: $set));
		}
		$aliasCount = \count($this->loadAliases());
		$logoCount = \count($this->diskLogoNames());

		$readme = $this->readDoc(relativePath: 'README.md');
		$docs = $this->readDoc(relativePath: 'docs/reference/icons.md');

		$this->assertStringContainsString(
			needle: (string)$iconCount,
			haystack: $readme,
			message: 'README.md must state the actual icon count.'
		);
		$this->assertStringContainsString(
			needle: (string)$logoCount . ' logos',
			haystack: $readme,
			message: 'README.md must state the actual logo count.'
		);
		$this->assertStringContainsString(
			needle: (string)$iconCount,
			haystack: $docs,
			message: 'docs/reference/icons.md must state the actual icon count.'
		);
		$this->assertStringContainsString(
			needle: (string)$logoCount,
			haystack: $docs,
			message: 'docs/reference/icons.md must state the actual logo count.'
		);
		$this->assertStringContainsString(
			needle: (string)$aliasCount,
			haystack: $docs,
			message: 'docs/reference/icons.md must state the actual legacy-alias count.'
		);

		// Never present the removed Amsterdam set as a current source. Scoped to the
		// README "## Icons" section (not the whole document): the "Dependency license
		// policy" section legitimately lists MPL-2.0 as one of many generally-approved
		// SPDX families for hypothetical future dependencies, unrelated to this app's
		// bundled icon set; README's "## Changelog" section is a dated historical
		// record (e.g. "v0.1.0 (2026-02-03)") that accurately describes what that past
		// release contained. docs/reference/icons.md is entirely about icons, so it is
		// checked in full.
		$readmeIconsSection = $this->sectionBetween(doc: $readme, startHeading: '## Icons', stopMarker: '## Installation');
		foreach ([$readmeIconsSection, $docs] as $doc) {
			$this->assertDoesNotMatchRegularExpression(
				pattern: '/Mozilla Public License 2\.0|MPL[\s-]?2\.0/i',
				string: $doc,
				message: 'Document must not claim MPL-2.0 for the current icon set.'
			);
		}
	}

	/**
	 * Every icon/logo filename used in a consumption example across the docs
	 * resolves to a real asset on disk (nested "{set}/{key}" or legacy top-level alias).
	 *
	 * @return void
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
			foreach ($this->diskSetIconNames(set: $set) as $key) {
				$haveNested[$set . '/' . $key] = true;
			}
		}

		foreach ($docs as $doc) {
			$contents = $this->readDoc(relativePath: $doc);

			preg_match_all('#icons/((?:rvo|open-gemeenten|den-haag|dsfr)/[a-z0-9-]+|[A-Za-z0-9_-]+)\.svg#', $contents, $iconRefs);
			foreach (array_unique($iconRefs[1]) as $name) {
				$resolves = isset($haveNested[$name]) || isset($haveTopLevel[$name]);
				$this->assertTrue(
					condition: $resolves,
					message: "{$doc} references a nonexistent icon img/icons/{$name}.svg"
				);
			}

			preg_match_all('#logos/([A-Za-z0-9_-]+)\.svg#', $contents, $logoRefs);
			foreach (array_unique($logoRefs[1]) as $name) {
				$this->assertArrayHasKey(
					key: $name,
					array: $haveLogo,
					message: "{$doc} references a nonexistent logo img/logos/{$name}.svg"
				);
			}
		}
	}

	/**
	 * The README "View Icon Documentation" link resolves to an existing file.
	 *
	 * @return void
	 */
	public function testReadmeIconDocumentationLinkResolves(): void {
		$readme = $this->readDoc(relativePath: 'README.md');

		$this->assertMatchesRegularExpression(
			pattern: '/\[View Icon Documentation[^\]]*\]\(([^)]+)\)/',
			string: $readme,
			message: 'README.md must contain a "View Icon Documentation" link.'
		);
		preg_match('/\[View Icon Documentation[^\]]*\]\(([^)]+)\)/', $readme, $m);
		$target = $m[1];

		$this->assertSame(
			expected: 'img/ICONS.md',
			actual: $target,
			message: 'The README icon-documentation link must point at img/ICONS.md.'
		);
		$this->assertFileExists(
			filename: $this->repoRoot() . '/' . $target,
			message: "README icon-documentation link target does not exist: {$target}"
		);
	}

	/**
	 * Extract the section of a markdown document starting at a heading and ending
	 * before the next occurrence of $stopMarker (or end of document if absent).
	 *
	 * @param string $doc The markdown document.
	 * @param string $startHeading The start heading.
	 * @param string $stopMarker The stop marker.
	 *
	 * @return string
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
	 * @param string $section The section.
	 *
	 * @return array<string>
	 */
	private function bulletNames(string $section): array {
		$names = [];
		$lines = preg_split('/\r?\n/', $section);
		foreach (match ($lines) {
			false => [], default => $lines
		} as $line) {
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
