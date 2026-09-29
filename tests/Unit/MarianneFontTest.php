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
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 2);
	}//end repoRoot()

	/**
	 * Read a file from the repository root, asserting it exists.
	 */
	private function readFile(string $relativePath): string {
		$path = $this->repoRoot() . '/' . $relativePath;
		$this->assertFileExists($path, "Expected file to exist: {$relativePath}");
		$contents = file_get_contents($path);
		$this->assertIsString($contents, "Could not read {$relativePath}");
		return $contents;
	}//end readFile()

	/**
	 * Every bundled Marianne woff2 file exists on disk under the sanctioned
	 * directory.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testAllEightMarianneWoff2FilesAreBundled(): void {
		$dir = $this->repoRoot() . '/css/systems/lasuite/fonts/marianne';
		$this->assertDirectoryExists($dir, 'css/systems/lasuite/fonts/marianne/ must exist.');

		foreach (self::MARIANNE_WOFF2_FILES as $file) {
			$this->assertFileExists($dir . '/' . $file, "Expected bundled Marianne file missing: {$file}");
		}

		// No .woff fallback — woff2-only per tasks.md #1.3 (CSP-clean self-hosting).
		$woffFallbacks = glob($dir . '/*.woff') ?: [];
		$this->assertSame(
			[],
			$woffFallbacks,
			'css/systems/lasuite/fonts/marianne/ must not carry .woff fallback files, only .woff2: ' . implode(', ', $woffFallbacks)
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
	 */
	public function testGatedStylesheetUsesOnlyAppRelativeUrlsToBundledFiles(): void {
		$css = $this->readFile('css/systems/lasuite/marianne.css');

		preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $matches);
		$this->assertNotEmpty($matches[1], 'css/systems/lasuite/marianne.css must contain at least one url() reference.');

		$referenced = [];
		foreach ($matches[1] as $ref) {
			$ref = trim($ref);

			$this->assertDoesNotMatchRegularExpression(
				'#^https?://#i',
				$ref,
				"css/systems/lasuite/marianne.css must not use an http(s):// url(): {$ref}"
			);

			$path = $this->repoRoot() . '/css/systems/lasuite/' . $ref;
			$this->assertFileExists($path, "css/systems/lasuite/marianne.css references a missing font file: css/systems/lasuite/{$ref}");

			$this->assertStringStartsWith(
				'fonts/marianne/',
				$ref,
				"css/systems/lasuite/marianne.css must only reference files under fonts/marianne/: {$ref}"
			);

			$referenced[] = basename($ref);
		}

		// Every bundled weight/style must be referenced — no dead file, no
		// @font-face declared without a backing bundled file.
		sort($referenced);
		$expected = self::MARIANNE_WOFF2_FILES;
		sort($expected);
		$this->assertSame($expected, array_values(array_unique($referenced)));
	}//end testGatedStylesheetUsesOnlyAppRelativeUrlsToBundledFiles()

	/**
	 * The gated stylesheet declares a real `@font-face Marianne` per bundled
	 * weight/style, with the DSFR weight mapping (Light 300, Regular 400,
	 * Medium 500, Bold 700).
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testGatedStylesheetDeclaresCorrectFontWeights(): void {
		$css = $this->readFile('css/systems/lasuite/marianne.css');

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
		$this->assertCount(8, $blocks[1], 'css/systems/lasuite/marianne.css must declare exactly 8 @font-face rules.');

		foreach ($blocks[1] as $block) {
			$this->assertMatchesRegularExpression("/font-family:\\s*'Marianne'/", $block, 'Every rule must declare font-family: \'Marianne\'.');
			$this->assertMatchesRegularExpression('/font-display:\s*swap/', $block, 'Every rule must declare font-display: swap.');

			preg_match('/fonts\/marianne\/([^\')]+)/', $block, $fileMatch);
			$this->assertNotEmpty($fileMatch, 'Could not find a referenced file in @font-face block.');
			$file = $fileMatch[1];

			$this->assertArrayHasKey($file, $expectedWeights, "Unexpected file referenced: {$file}");

			preg_match('/font-weight:\s*(\d+)/', $block, $weightMatch);
			$this->assertNotEmpty($weightMatch, "No font-weight declared for {$file}.");
			$this->assertSame($expectedWeights[$file], $weightMatch[1], "Wrong font-weight for {$file}.");

			$expectedStyle = str_contains($file, '_Italic') ? 'italic' : 'normal';
			$this->assertMatchesRegularExpression(
				'/font-style:\s*' . $expectedStyle . '/',
				$block,
				"Wrong font-style for {$file} (expected {$expectedStyle})."
			);
		}
	}//end testGatedStylesheetDeclaresCorrectFontWeights()

	/**
	 * `@gouvfr/dsfr` is a build-only dependency and no runtime PHP or JS
	 * source imports/requires it.
	 *
	 * It is declared under `optionalDependencies`, not `devDependencies`. Its
	 * published tree is broken upstream — @gouvfr/dsfr@1.15.1 ->
	 * @gouvfr/dsfr-nexus -> @gouvfr/dsfr-roller -> @gouvfr/dsfr-publisher, which
	 * is not on the registry (E404), as is @gouvfr/dsfr-token — so declaring it
	 * as a devDependency made `npm ci` impossible and took every npm-dependent
	 * CI job down with it. `optionalDependencies` keeps the declaration while
	 * letting npm skip the unresolvable subtree.
	 *
	 * The build-only property this test exists to protect is unchanged and is
	 * still asserted: it must not be a RUNTIME dependency, and nothing under
	 * lib/ or js/ may reference it.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testDsfrIsABuildOnlyDevDependency(): void {
		$packageJson = json_decode($this->readFile('package.json'), true);
		$this->assertIsArray($packageJson, 'package.json must decode to an array.');

		$this->assertArrayHasKey(
			'@gouvfr/dsfr',
			$packageJson['optionalDependencies'] ?? [],
			'@gouvfr/dsfr must appear under package.json optionalDependencies.'
		);
		$this->assertArrayNotHasKey(
			'@gouvfr/dsfr',
			$packageJson['devDependencies'] ?? [],
			'@gouvfr/dsfr must NOT be a devDependency — its upstream tree is unresolvable and it breaks npm ci.'
		);
		$this->assertArrayNotHasKey(
			'@gouvfr/dsfr',
			$packageJson['dependencies'] ?? [],
			'@gouvfr/dsfr must NOT appear under package.json runtime dependencies.'
		);

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
					'@gouvfr/dsfr',
					$contents,
					str_replace($this->repoRoot() . '/', '', $file->getPathname()) . ' must not import/require @gouvfr/dsfr at runtime.'
				);
			}
		}

		// The build script itself is the one sanctioned build-time consumer.
		$buildScript = $this->readFile('scripts/build-fonts-marianne.js');
		$this->assertStringContainsString('@gouvfr/dsfr', $buildScript);
	}//end testDsfrIsABuildOnlyDevDependency()

	/**
	 * The three legal/governance artifacts exist and carry the required
	 * content: the restriction wording + source URL, the eligibility
	 * condition, and the full Etalab-2.0 licence text.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testLegalArtifactsExistAndCiteTheSource(): void {
		$licence = $this->readFile('MARIANNE-LICENCE.md');
		$this->assertMatchesRegularExpression(
			"/r[ée]serv[ée]e?\\s+aux\\s+administrations\\s+de\\s+l'[ÉE]tat/iu",
			$licence,
			'MARIANNE-LICENCE.md must reproduce the Marianne restriction wording verbatim.'
		);
		$this->assertStringContainsString('Etalab-2.0', $licence);
		$this->assertMatchesRegularExpression(
			'#https?://(www\.)?(systeme-de-design\.gouv\.fr|etalab\.gouv\.fr|npmjs\.com/package/@gouvfr/dsfr)#i',
			$licence,
			'MARIANNE-LICENCE.md must cite a source URL for the DSFR package or official design system site.'
		);

		$agreement = $this->readFile('AGREEMENT-MARIANNE.md');
		$this->assertMatchesRegularExpression(
			'/French State agency/i',
			$agreement,
			'AGREEMENT-MARIANNE.md must state the French-State-agency eligibility condition.'
		);

		$etalabText = $this->readFile('LICENSES/etalab-2.0.txt');
		// REUSE-IgnoreStart -- asserted string, not this file's own licence tag.
		$this->assertStringContainsString('SPDX-License-Identifier: etalab-2.0', $etalabText);
		// REUSE-IgnoreEnd
		$this->assertMatchesRegularExpression(
			'/LICENCE OUVERTE 2\.0/i',
			$etalabText,
			'LICENSES/etalab-2.0.txt must carry the Etalab Open Licence 2.0 text.'
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
	 */
	public function testLicenseOverridesMapsMarianneFilesToEtalab(): void {
		$overrides = json_decode($this->readFile('.license-overrides.json'), true);
		$this->assertIsArray($overrides, '.license-overrides.json must decode to an array.');

		foreach (self::MARIANNE_WOFF2_FILES as $file) {
			$key = 'css/systems/lasuite/fonts/marianne/' . $file;
			$this->assertArrayHasKey($key, $overrides, ".license-overrides.json must map {$key}.");
			$this->assertSame('Etalab-2.0', $overrides[$key], "{$key} must map to Etalab-2.0.");
		}

		$this->assertFileExists(
			$this->repoRoot() . '/LICENSES/etalab-2.0.txt',
			'LICENSES/etalab-2.0.txt must exist so the Etalab-2.0 identifier resolves to licence text.'
		);
	}//end testLicenseOverridesMapsMarianneFilesToEtalab()

	/**
	 * The English restriction-notice source keys exist in l10n/en.json, with
	 * genuine (non-English) translations in l10n/nl.json and l10n/fr.json.
	 *
	 * @spec openspec/specs/marianne-font/spec.md
	 */
	public function testRestrictionNoticeIsTranslatedInNlAndFr(): void {
		$keys = [
			"Our organisation is a French State agency (administration de l'État)",
			'Marianne is the official typeface of the French State and is reserved for French State administrations. Enable it only if your organisation is a French State agency. Otherwise Inter is used.',
		];

		$en = json_decode($this->readFile('l10n/en.json'), true);
		$nl = json_decode($this->readFile('l10n/nl.json'), true);
		$fr = json_decode($this->readFile('l10n/fr.json'), true);

		foreach ($keys as $key) {
			$this->assertArrayHasKey($key, $en['translations'] ?? [], "l10n/en.json must contain the key: {$key}");
			$this->assertArrayHasKey($key, $nl['translations'] ?? [], "l10n/nl.json must contain the key: {$key}");
			$this->assertArrayHasKey($key, $fr['translations'] ?? [], "l10n/fr.json must contain the key: {$key}");

			$this->assertNotSame(
				$key,
				$nl['translations'][$key],
				"l10n/nl.json value for '{$key}' must be a genuine Dutch translation, not the untranslated English source."
			);
			$this->assertNotSame(
				$key,
				$fr['translations'][$key],
				"l10n/fr.json value for '{$key}' must be a genuine French translation, not the untranslated English source."
			);
		}
	}//end testRestrictionNoticeIsTranslatedInNlAndFr()

	/**
	 * `token-sets.json`'s `lasuite` entry still carries no `logo` key — the
	 * Marianne font gate must not have reintroduced a bundled French-state
	 * logo (lasuite-stack's own asset-license-compliance requirement).
	 *
	 * @spec openspec/specs/lasuite-stack/spec.md
	 */
	public function testLasuiteTokenSetStillHasNoLogoKey(): void {
		$json = json_decode($this->readFile('token-sets.json'), true);
		$this->assertIsArray($json, 'token-sets.json must decode to an array.');

		$byId = array_column($json, null, 'id');
		$this->assertArrayHasKey('lasuite', $byId, 'token-sets.json must contain a lasuite entry.');
		$this->assertArrayNotHasKey(
			'logo',
			$byId['lasuite']['theming'] ?? [],
			'The lasuite theming block must not declare a logo key.'
		);
	}//end testLasuiteTokenSetStillHasNoLogoKey()
}//end class
