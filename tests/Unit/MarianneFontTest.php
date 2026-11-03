<?php

/**
 * Static inventory regression test for the Marianne (French State typeface)
 * bundling and gating change.
 *
 * Guards the marianne-font capability contract: the bundled woff2 files, the
 * gated stylesheet's app-relative url() references, the build-only
 * devDependency invariant, the licence/agreement artifacts, the
 * .license-overrides.json SPDX mapping, and the translated restriction
 * notice. Mirrors the tests/Unit/IconAssetsTest.php and
 * tests/Unit/ClaimAccuracyTest.php static-inventory pattern (no Nextcloud
 * runtime required).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/marianne-font/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static-asset + governance-file inventory regression test (no Nextcloud
 * runtime required).
 */
class MarianneFontTest extends TestCase {

	/**
	 * The 8 Marianne woff2 filenames DSFR 1.15.1 ships (Light, Regular,
	 * Medium, Bold, each with an italic variant).
	 *
	 * @var array<string>
	 */
	private const MARIANNE_WOFF2_FILES = [
		'Marianne-Light.woff2',
		'Marianne-Light_Italic.woff2',
		'Marianne-Regular.woff2',
		'Marianne-Regular_Italic.woff2',
		'Marianne-Medium.woff2',
		'Marianne-Medium_Italic.woff2',
		'Marianne-Bold.woff2',
		'Marianne-Bold_Italic.woff2',
	];

	/**
	 * Repository root, derived from this test file's location.
	 *
	 * @return string
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}//end repoRoot()

	/**
	 * Read a file from the repository root, asserting it exists.
	 *
	 * @return string
	 */
	private function readFile(string $relativePath): string {
		$path = $this->repoRoot() . '/' . $relativePath;
		$this->assertFileExists(filename: $path, message: "Expected file to exist: {$relativePath}");
		$contents = file_get_contents($path);
		$this->assertIsString(actual: $contents, message: "Could not read {$relativePath}");
		return $contents;
	}//end readFile()

	/**
	 * Every bundled Marianne woff2 file exists on disk under the sanctioned
	 * directory.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testAllEightMarianneWoff2FilesAreBundled(): void {
		$dir = $this->repoRoot() . '/css/systems/lasuite/fonts/marianne';
		$this->assertDirectoryExists(directory: $dir, message: 'css/systems/lasuite/fonts/marianne/ must exist.');

		foreach (self::MARIANNE_WOFF2_FILES as $file) {
			$this->assertFileExists(filename: $dir . '/' . $file, message: "Expected bundled Marianne file missing: {$file}");
		}

		// No .woff fallback — woff2-only per tasks.md #1.3 (CSP-clean self-hosting).
		$woffFallbacks = glob($dir . '/*.woff') ?: [];
		$this->assertSame(
			expected: [],
			actual: $woffFallbacks,
			message: 'css/systems/lasuite/fonts/marianne/ must not carry .woff fallback files, only .woff2: ' . implode(', ', $woffFallbacks)
		);
	}//end testAllEightMarianneWoff2FilesAreBundled()

	/**
	 * `css/systems/lasuite/marianne.css` exists, every `url()` inside it is
	 * app-relative (no `http(s)://` scheme), and every referenced file
	 * resolves under css/systems/lasuite/fonts/marianne/ on disk. Also
	 * asserts every bundled weight is actually referenced (no orphaned
	 * bundled file, no @font-face without a backing file).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testGatedStylesheetUsesOnlyAppRelativeUrlsToBundledFiles(): void {
		$css = $this->readFile(relativePath: 'css/systems/lasuite/marianne.css');

		preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $matches);
		$this->assertNotEmpty(actual: $matches[1], message: 'css/systems/lasuite/marianne.css must contain at least one url() reference.');

		$referenced = [];
		foreach ($matches[1] as $ref) {
			$ref = trim($ref);

			$this->assertDoesNotMatchRegularExpression(
				pattern: '#^https?://#i',
				string: $ref,
				message: "css/systems/lasuite/marianne.css must not use an http(s):// url(): {$ref}"
			);

			$path = $this->repoRoot() . '/css/systems/lasuite/' . $ref;
			$this->assertFileExists(
				filename: $path,
				message: "css/systems/lasuite/marianne.css references a missing font file: css/systems/lasuite/{$ref}"
			);

			$this->assertStringStartsWith(
				prefix: 'fonts/marianne/',
				string: $ref,
				message: "css/systems/lasuite/marianne.css must only reference files under fonts/marianne/: {$ref}"
			);

			$referenced[] = basename($ref);
		}

		// Every bundled weight/style must be referenced — no dead file, no
		// @font-face declared without a backing bundled file.
		sort($referenced);
		$expected = self::MARIANNE_WOFF2_FILES;
		sort($expected);
		$this->assertSame(expected: $expected, actual: array_values(array_unique($referenced)));
	}//end testGatedStylesheetUsesOnlyAppRelativeUrlsToBundledFiles()

	/**
	 * The gated stylesheet declares a real `@font-face Marianne` per bundled
	 * weight/style, with the DSFR weight mapping (Light 300, Regular 400,
	 * Medium 500, Bold 700).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testGatedStylesheetDeclaresCorrectFontWeights(): void {
		$css = $this->readFile(relativePath: 'css/systems/lasuite/marianne.css');

		$expectedWeights = [
			'Marianne-Light.woff2' => '300',
			'Marianne-Light_Italic.woff2' => '300',
			'Marianne-Regular.woff2' => '400',
			'Marianne-Regular_Italic.woff2' => '400',
			'Marianne-Medium.woff2' => '500',
			'Marianne-Medium_Italic.woff2' => '500',
			'Marianne-Bold.woff2' => '700',
			'Marianne-Bold_Italic.woff2' => '700',
		];

		// Split into individual @font-face blocks and check each carries the
		// right font-weight for the file it references.
		preg_match_all('/@font-face\s*\{([^}]*)\}/s', $css, $blocks);
		$this->assertCount(
			expectedCount: 8,
			haystack: $blocks[1],
			message: 'css/systems/lasuite/marianne.css must declare exactly 8 @font-face rules.'
		);

		foreach ($blocks[1] as $block) {
			$this->assertMatchesRegularExpression(
				pattern: "/font-family:\\s*'Marianne'/",
				string: $block,
				message: 'Every rule must declare font-family: \'Marianne\'.'
			);
			$this->assertMatchesRegularExpression(
				pattern: '/font-display:\s*swap/',
				string: $block,
				message: 'Every rule must declare font-display: swap.'
			);

			preg_match('/fonts\/marianne\/([^\')]+)/', $block, $fileMatch);
			$this->assertNotEmpty(actual: $fileMatch, message: 'Could not find a referenced file in @font-face block.');
			$file = $fileMatch[1];

			$this->assertArrayHasKey(key: $file, array: $expectedWeights, message: "Unexpected file referenced: {$file}");

			preg_match('/font-weight:\s*(\d+)/', $block, $weightMatch);
			$this->assertNotEmpty(actual: $weightMatch, message: "No font-weight declared for {$file}.");
			$this->assertSame(expected: $expectedWeights[$file], actual: $weightMatch[1], message: "Wrong font-weight for {$file}.");

			$expectedStyle = str_contains($file, '_Italic') ? 'italic' : 'normal';
			$this->assertMatchesRegularExpression(
				pattern: '/font-style:\s*' . $expectedStyle . '/',
				string: $block,
				message: "Wrong font-style for {$file} (expected {$expectedStyle})."
			);
		}
	}//end testGatedStylesheetDeclaresCorrectFontWeights()

	/**
	 * `@gouvfr/dsfr` is not a dependency at all, and no runtime PHP or JS
	 * source imports or requires it.
	 *
	 * It used to be an optional dependency, but its preinstall script exits 1
	 * until the DSFR terms of use are accepted, so npm never installed it. It
	 * was dropped on 2 Oct 2026; scripts/build-icons.js reads the pre-fetched
	 * .dsfr-src/ scratch source and the Marianne files are bundled separately.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testDsfrIsNotADependency(): void {
		$packageJson = json_decode($this->readFile(relativePath: 'package.json'), true);
		$this->assertIsArray(actual: $packageJson, message: 'package.json must decode to an array.');

		// Dropped on 2 Oct 2026: its preinstall script refuses to run without the
		// DSFR terms of use, so npm never installed it. The Marianne files are
		// bundled separately, gated (marianne-font-restricted).
		foreach (['dependencies', 'devDependencies', 'optionalDependencies'] as $section) {
			$this->assertArrayNotHasKey(
				key: '@gouvfr/dsfr',
				array: $packageJson[$section] ?? [],
				message: '@gouvfr/dsfr must not be declared in package.json ' . $section . '.'
			);
		}

		// No lib/ or js/ (excluding the build script itself) source references it.
		foreach (['lib', 'js'] as $dir) {
			$absDir = $this->repoRoot() . '/' . $dir;
			if (is_dir($absDir) === false) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($iterator as $file) {
				/** @var \SplFileInfo $file */
				$ext = $file->getExtension();
				if ($ext !== 'php' && $ext !== 'js') {
					continue;
				}

				$contents = (string)file_get_contents($file->getPathname());
				$this->assertStringNotContainsString(
					needle: '@gouvfr/dsfr',
					haystack: $contents,
					message: str_replace($this->repoRoot() . '/', '', $file->getPathname()) . ' must not import/require @gouvfr/dsfr at runtime.'
				);
			}
		}

		// The build script itself is the one sanctioned build-time consumer.
		$buildScript = $this->readFile(relativePath: 'scripts/build-fonts-marianne.js');
		$this->assertStringContainsString(needle: '@gouvfr/dsfr', haystack: $buildScript);
	}//end testDsfrIsNotADependency()

	/**
	 * The three legal/governance artifacts exist and carry the required
	 * content: the restriction wording + source URL, the eligibility
	 * condition, and the full Etalab-2.0 licence text.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testLegalArtifactsExistAndCiteTheSource(): void {
		$licence = $this->readFile(relativePath: 'MARIANNE-LICENCE.md');
		$this->assertMatchesRegularExpression(
			pattern: "/r[ée]serv[ée]e?\\s+aux\\s+administrations\\s+de\\s+l'[ÉE]tat/iu",
			string: $licence,
			message: 'MARIANNE-LICENCE.md must reproduce the Marianne restriction wording verbatim.'
		);
		$this->assertStringContainsString(needle: 'Etalab-2.0', haystack: $licence);
		$this->assertMatchesRegularExpression(
			pattern: '#https?://(www\.)?(systeme-de-design\.gouv\.fr|etalab\.gouv\.fr|npmjs\.com/package/@gouvfr/dsfr)#i',
			string: $licence,
			message: 'MARIANNE-LICENCE.md must cite a source URL for the DSFR package or official design system site.'
		);

		$agreement = $this->readFile(relativePath: 'AGREEMENT-MARIANNE.md');
		$this->assertMatchesRegularExpression(
			pattern: '/French State agency/i',
			string: $agreement,
			message: 'AGREEMENT-MARIANNE.md must state the French-State-agency eligibility condition.'
		);

		$etalabText = $this->readFile(relativePath: 'LICENSES/etalab-2.0.txt');
		// REUSE-IgnoreStart -- asserted string, not this file's own licence tag.
		$this->assertStringContainsString(needle: 'SPDX-License-Identifier: etalab-2.0', haystack: $etalabText);
		// REUSE-IgnoreEnd
		$this->assertMatchesRegularExpression(
			pattern: '/LICENCE OUVERTE 2\.0/i',
			string: $etalabText,
			message: 'LICENSES/etalab-2.0.txt must carry the Etalab Open Licence 2.0 text.'
		);
	}//end testLegalArtifactsExistAndCiteTheSource()

	/**
	 * `.license-overrides.json` maps every bundled Marianne woff2 path to
	 * `Etalab-2.0`, and `LICENSES/etalab-2.0.txt` exists so the identifier
	 * resolves to licence text.
	 *
	 * The two spellings are deliberate and belong to different gates: the
	 * override value is the licence string the dependency SBOM reports for
	 * `@gouvfr/dsfr`, while the file name is the SPDX licence list's own
	 * identifier, which is lower-case and is the only spelling `reuse lint`
	 * accepts under `LICENSES/`.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testLicenseOverridesMapsMarianneFilesToEtalab(): void {
		$overrides = json_decode($this->readFile(relativePath: '.license-overrides.json'), true);
		$this->assertIsArray(actual: $overrides, message: '.license-overrides.json must decode to an array.');

		foreach (self::MARIANNE_WOFF2_FILES as $file) {
			$key = 'css/systems/lasuite/fonts/marianne/' . $file;
			$this->assertArrayHasKey(key: $key, array: $overrides, message: ".license-overrides.json must map {$key}.");
			$this->assertSame(expected: 'Etalab-2.0', actual: $overrides[$key], message: "{$key} must map to Etalab-2.0.");
		}

		$this->assertFileExists(
			filename: $this->repoRoot() . '/LICENSES/etalab-2.0.txt',
			message: 'LICENSES/etalab-2.0.txt must exist so the Etalab-2.0 identifier resolves to licence text.'
		);
	}//end testLicenseOverridesMapsMarianneFilesToEtalab()

	/**
	 * The English restriction-notice source keys exist in l10n/en.json, with
	 * genuine (non-English) translations in l10n/nl.json and l10n/fr.json.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 *
	 * @return void
	 */
	public function testRestrictionNoticeIsTranslatedInNlAndFr(): void {
		$keys = [
			"Our organisation is a French State agency (administration de l'État)",
			'Marianne is the official typeface of the French State and is reserved for French State administrations. Enable it only if your organisation is a French State agency. Otherwise Inter is used.',
		];

		$en = json_decode($this->readFile(relativePath: 'l10n/en.json'), true);
		$nl = json_decode($this->readFile(relativePath: 'l10n/nl.json'), true);
		$fr = json_decode($this->readFile(relativePath: 'l10n/fr.json'), true);

		foreach ($keys as $key) {
			$this->assertArrayHasKey(key: $key, array: $en['translations'] ?? [], message: "l10n/en.json must contain the key: {$key}");
			$this->assertArrayHasKey(key: $key, array: $nl['translations'] ?? [], message: "l10n/nl.json must contain the key: {$key}");
			$this->assertArrayHasKey(key: $key, array: $fr['translations'] ?? [], message: "l10n/fr.json must contain the key: {$key}");

			$this->assertNotSame(
				expected: $key,
				actual: $nl['translations'][$key],
				message: "l10n/nl.json value for '{$key}' must be a genuine Dutch translation, not the untranslated English source."
			);
			$this->assertNotSame(
				expected: $key,
				actual: $fr['translations'][$key],
				message: "l10n/fr.json value for '{$key}' must be a genuine French translation, not the untranslated English source."
			);
		}
	}//end testRestrictionNoticeIsTranslatedInNlAndFr()

	/**
	 * `token-sets.json`'s `lasuite` entry still carries no `logo` key — the
	 * Marianne font gate must not have reintroduced a bundled French-state
	 * logo (lasuite-stack's own asset-license-compliance requirement).
	 *
	 * @spec openspec/specs/lasuite-stack/spec.md
	 *
	 * @return void
	 */
	public function testLasuiteTokenSetStillHasNoLogoKey(): void {
		$json = json_decode($this->readFile(relativePath: 'token-sets.json'), true);
		$this->assertIsArray(actual: $json, message: 'token-sets.json must decode to an array.');

		$byId = array_column($json, null, 'id');
		$this->assertArrayHasKey(key: 'lasuite', array: $byId, message: 'token-sets.json must contain a lasuite entry.');
		$this->assertArrayNotHasKey(
			key: 'logo',
			array: $byId['lasuite']['theming'] ?? [],
			message: 'The lasuite theming block must not declare a logo key.'
		);
	}//end testLasuiteTokenSetStillHasNoLogoKey()
}//end class
