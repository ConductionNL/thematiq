<?php

/**
 * The shell catalogue: every shipped set, all eighteen roles, and the committed file is current.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-the-catalogue-is-reproducible
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShellCatalogueService;
use OCA\Thematiq\Service\ShellCatalogueVocabulary;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real service over this repository's own files. No Nextcloud runtime is needed.
 *
 * The licence assertions are written from the side of the evidence: a logo
 * or a font may only be marked redistributable when the statement that makes
 * it so is still in the repository.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */
class ShellCatalogueTest extends TestCase {

	/**
	 * The repository root.
	 *
	 * @return string The path.
	 */
	private function root(): string {
		return dirname(__DIR__, 2);
	}//end root()

	/**
	 * The service, built from its real collaborators.
	 *
	 * @return ShellCatalogueService The service.
	 */
	private function service(): ShellCatalogueService {
		return new ShellCatalogueService(
			declarations: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService()),
			mapper: new ShellRoleMapper(colours: new ContrastService())
		);
	}//end service()

	/**
	 * A fresh catalogue of this repository.
	 *
	 * @return array<string, mixed> The catalogue.
	 */
	private function catalogue(): array {
		return $this->service()->build(appPath: $this->root());
	}//end catalogue()

	/**
	 * The sets of a catalogue, by id.
	 *
	 * @param array<string, mixed> $catalogue The catalogue.
	 *
	 * @return array<string, array<string, mixed>> The sets.
	 */
	private function setsById(array $catalogue): array {
		return array_column($catalogue['sets'], null, 'id');
	}//end setsById()

	/**
	 * The committed catalogue is what a fresh run writes, byte for byte.
	 *
	 * @return void
	 */
	public function testTheCommittedCatalogueIsCurrent(): void {
		$this->assertSame(
			expected: $this->service()->render(catalogue: $this->catalogue()),
			actual: (string)file_get_contents($this->root() . '/docs/reference/shell-catalogue.json'),
			message: 'docs/reference/shell-catalogue.json is stale. Run: composer docs:shell-catalogue'
		);
	}//end testTheCommittedCatalogueIsCurrent()

	/**
	 * Two runs over the same tree give the same catalogue, and it carries no date.
	 *
	 * @return void
	 */
	public function testTheCatalogueIsReproducible(): void {
		$first = $this->service()->render(catalogue: $this->catalogue());

		$this->assertSame(expected: $first, actual: $this->service()->render(catalogue: $this->catalogue()));
		$this->assertDoesNotMatchRegularExpression(pattern: '/20\d\d-\d\d-\d\dT/', string: $first, message: 'a timestamp would make every run differ');
		$this->assertMatchesRegularExpression(pattern: '/^sets:[0-9a-f]{16}$/', string: $this->catalogue()['generated_from']);
		$this->assertSame(expected: 'thematiq 1.2.3', actual: $this->service()->build(appPath: $this->root(), generatedFrom: 'thematiq 1.2.3')['generated_from']);
	}//end testTheCatalogueIsReproducible()

	/**
	 * Every shipped set is in the catalogue once, in id order, with the contract's fields.
	 *
	 * @return void
	 */
	public function testEveryShippedSetIsInTheCatalogueOnce(): void {
		$catalogue = $this->catalogue();

		$shipped = [];
		foreach ((array)glob($this->root() . '/css/tokens/*.css') as $file) {
			$shipped[] = basename((string)$file, '.css');
		}

		sort($shipped, SORT_STRING);

		$this->assertSame(expected: ['version', 'generated_from', 'sets', 'fonts'], actual: array_keys($catalogue));
		$this->assertSame(expected: 1, actual: $catalogue['version']);
		$this->assertGreaterThan(expected: 60, actual: count($shipped));
		$this->assertSame(expected: $shipped, actual: array_column($catalogue['sets'], 'id'));

		foreach ($catalogue['sets'] as $set) {
			$this->assertSame(expected: ['id', 'name', 'tokens', 'logo'], actual: array_slice(array_keys($set), 0, 4), message: $set['id']);
			$this->assertSame(expected: [], actual: array_diff(array_keys($set), ['id', 'name', 'tokens', 'logo', 'vocabulary', 'dark']), message: $set['id']);
			$this->assertNotSame(expected: '', actual: $set['name'], message: $set['id']);
			$this->assertSame(expected: ['file', 'inverse_file', 'licence', 'redistributable', 'basis'], actual: array_keys($set['logo']), message: $set['id']);
		}

		$sets = $this->setsById(catalogue: $catalogue);
		$this->assertSame(expected: 'Gemeente Zuiddrecht', actual: $sets['zuiddrecht']['name']);
		$this->assertSame(expected: 'Conduction', actual: $sets['conduction']['name'], message: 'a set without a manifest entry is named after its id');
	}//end testEveryShippedSetIsInTheCatalogueOnce()

	/**
	 * Every set has all eighteen roles, each with a value, and they are the endpoint's roles.
	 *
	 * @return void
	 */
	public function testEverySetHasAllEighteenRoles(): void {
		$catalogue = $this->catalogue();
		foreach ($catalogue['sets'] as $set) {
			$this->assertSame(expected: ShellRoleMapper::ROLES, actual: array_keys($set['tokens']), message: $set['id']);
			foreach ($set['tokens'] as $role => $value) {
				$this->assertIsString(actual: $value, message: $set['id'] . ' ' . $role);
				$this->assertNotSame(expected: '', actual: trim($value), message: $set['id'] . ' ' . $role);
			}
		}

		// The same call the shell tokens endpoint makes for a set without an administrator's values.
		$resolver = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());
		$this->assertSame(
			expected: (new ShellRoleMapper(colours: new ContrastService()))->map(tokens: $resolver->resolveDeclarations(appPath: $this->root(), id: 'zuiddrecht', theming: [])),
			actual: $this->setsById(catalogue: $catalogue)['zuiddrecht']['tokens']
		);
	}//end testEverySetHasAllEighteenRoles()

	/**
	 * A set that ships a dark variant has dark roles, read from that variant; a set without one has no dark block.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-set-with-a-dark-variant-has-dark-roles
	 */
	public function testASetWithADarkVariantHasDarkRoles(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());

		$with = 0;
		foreach ($sets as $id => $set) {
			$hasVariant = is_file($this->root() . '/css/tokens/dark/' . $id . '.css');
			$this->assertSame(expected: $hasVariant, actual: isset($set['dark']), message: $id);
			if ($hasVariant === false) {
				continue;
			}

			$with++;
			$this->assertSame(expected: [], actual: array_diff(array_keys($set['dark']), ['tokens', 'vocabulary']), message: $id);
			$this->assertSame(expected: 'tokens', actual: array_key_first($set['dark']), message: $id);
			$this->assertSame(expected: ShellRoleMapper::ROLES, actual: array_keys($set['dark']['tokens']), message: $id);
			foreach ($set['dark']['tokens'] as $role => $value) {
				$this->assertNotSame(expected: '', actual: trim($value), message: $id . ' dark ' . $role);
				$this->assertStringNotContainsString(needle: 'var(', haystack: $value, message: $id . ' dark ' . $role);
			}

			// What the dark variant does not restate keeps its light value.
			$this->assertSame(expected: $set['tokens']['radius'], actual: $set['dark']['tokens']['radius'], message: $id);
			$this->assertSame(expected: $set['tokens']['font-family'], actual: $set['dark']['tokens']['font-family'], message: $id);
		}

		$this->assertGreaterThan(expected: 60, actual: $with);
		$this->assertArrayNotHasKey(key: 'dark', array: $sets['nextcloud'], message: 'the stock set ships no dark variant: Nextcloud brings its own');

		// The values are the committed dark file's own.
		$dark = (string)file_get_contents($this->root() . '/css/tokens/dark/zuiddrecht.css');
		$this->assertStringContainsString(needle: '--nldesign-color-text: ' . $sets['zuiddrecht']['dark']['tokens']['ink'] . ';', haystack: $dark);
		$this->assertStringContainsString(needle: '--nldesign-color-primary: ' . $sets['zuiddrecht']['dark']['tokens']['primary'] . ';', haystack: $dark);
		$this->assertNotSame(expected: $sets['zuiddrecht']['tokens']['ground'], actual: $sets['zuiddrecht']['dark']['tokens']['ground']);
	}//end testASetWithADarkVariantHasDarkRoles()

	/**
	 * In every dark block the text reads on what it is written on.
	 *
	 * Main text, body text and muted text on the page, and the selected
	 * item's text on its tint, at 4.5:1. A set that names no page background
	 * stands on Nextcloud's dark page, not on the white of its light page:
	 * that mistake gave seven sets main text at 1.2 to 1.6:1.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-the-dark-roles-read-on-each-other
	 */
	public function testTheDarkRolesReadOnEachOther(): void {
		$contrast = new ContrastService();
		$pairs = [['ink', 'ground'], ['text', 'ground'], ['muted', 'ground'], ['link', 'ground'], ['primary-deep', 'primary-light']];

		$failures = [];
		$checked = 0;
		foreach ($this->catalogue()['sets'] as $set) {
			if (isset($set['dark']) === false) {
				continue;
			}

			$tokens = $set['dark']['tokens'];
			foreach ($pairs as [$foreground, $background]) {
				$checked++;
				$ratio = $contrast->measure(foreground: $tokens[$foreground], background: $tokens[$background], page: $tokens['ground']);
				if ($ratio === null || $ratio < 4.5) {
					$failures[] = sprintf('%s: %s %s on %s %s is %s', $set['id'], $foreground, $tokens[$foreground], $background, $tokens[$background], var_export($ratio, true));
				}
			}
		}

		$this->assertGreaterThan(expected: 300, actual: $checked);
		$this->assertSame(expected: [], actual: $failures);

		// A set without a page background of its own: Nextcloud's dark page.
		$sets = $this->setsById(catalogue: $this->catalogue());
		$this->assertSame(expected: '#171717', actual: $sets['enschede']['dark']['tokens']['ground']);
		$this->assertSame(expected: '#ffffff', actual: $sets['enschede']['tokens']['ground']);
	}//end testTheDarkRolesReadOnEachOther()

	/**
	 * A logo file named in the catalogue exists, and a set without a logo says nothing about one.
	 *
	 * @return void
	 */
	public function testLogoFilesExist(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());
		foreach ($sets as $id => $set) {
			foreach (['file', 'inverse_file'] as $key) {
				if ($set['logo'][$key] !== null) {
					$this->assertFileExists(filename: $this->root() . '/' . $set['logo'][$key], message: $id);
				}
			}

			if ($set['logo']['file'] === null) {
				$this->assertNull(actual: $set['logo']['redistributable'], message: $id);
				$this->assertNull(actual: $set['logo']['licence'], message: $id);
			}
		}

		$this->assertSame(expected: 'img/logos/zuiddrecht.svg', actual: $sets['zuiddrecht']['logo']['file']);
		$this->assertSame(expected: 'img/logos/zuiddrecht-dark.svg', actual: $sets['zuiddrecht']['logo']['inverse_file']);
		$this->assertNull(actual: $sets['utrecht']['logo']['inverse_file']);
		$this->assertNull(actual: $sets['nextcloud']['logo']['file']);
	}//end testLogoFilesExist()

	/**
	 * A logo is marked redistributable only when the repository still says it is ours.
	 *
	 * @return void
	 */
	public function testALogoIsRedistributableOnlyOnEvidence(): void {
		$manifest = array_column((array)json_decode((string)file_get_contents($this->root() . '/token-sets.json'), true), null, 'id');
		$sets = $this->setsById(catalogue: $this->catalogue());

		$true = 0;
		foreach ($sets as $id => $set) {
			if ($set['logo']['redistributable'] !== true) {
				continue;
			}

			$true++;
			$description = (string)($manifest[$id]['description'] ?? '');
			$file = (string)file_get_contents($this->root() . '/' . $set['logo']['file']);
			$this->assertTrue(
				condition: (preg_match("/does not exist|Not a real organisation|Conduction's own brand/i", $description) === 1
					|| preg_match('/Fictional example logo|logo placeholder/i', $file) === 1),
				message: $id . ' is marked redistributable and nothing in the repository says the logo is ours'
			);
			$this->assertSame(expected: 'EUPL-1.2', actual: $set['logo']['licence'], message: $id);
			$this->assertNotSame(expected: '', actual: (string)$set['logo']['basis'], message: $id);
		}

		$this->assertGreaterThan(expected: 0, actual: $true);

		// A made-up municipality, a stand-in we drew, and our own brand.
		$this->assertTrue(condition: $sets['zuiddrecht']['logo']['redistributable']);
		$this->assertTrue(condition: $sets['example-gemeente']['logo']['redistributable']);
		$this->assertTrue(condition: $sets['dinkelland']['logo']['redistributable']);
		$this->assertTrue(condition: $sets['frankendesk']['logo']['redistributable']);
		$this->assertTrue(condition: $sets['opencatalogi']['logo']['redistributable'], message: 'OpenCatalogi is a Conduction product, and token-sets.json says so');
	}//end testALogoIsRedistributableOnlyOnEvidence()

	/**
	 * A real organisation's logo is never marked redistributable on the repository default alone.
	 *
	 * @return void
	 */
	public function testARealOrganisationsLogoIsNotMarkedRedistributable(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());

		foreach (['amsterdam', 'denhaag', 'rotterdam', 'utrecht', 'vng', 'nijmegen', 'provincie-zuid-holland', 'xxllnc'] as $id) {
			$this->assertNull(actual: $sets[$id]['logo']['redistributable'], message: $id . ': the repository does not say, so the catalogue does not either');
			$this->assertStringContainsString(needle: 'holder of the artwork is not stated', haystack: (string)$sets[$id]['logo']['licence'], message: $id);
		}

		// The Rijksoverheid mark: the repository's own asset page quotes the prohibition.
		$this->assertFalse(condition: $sets['rijkshuisstijl']['logo']['redistributable']);
		$this->assertStringContainsString(needle: 'docs/reference/assets.md', haystack: (string)$sets['rijkshuisstijl']['logo']['basis']);
		$this->assertStringContainsString(
			needle: 'Gebruik hiervan is strikt verboden',
			haystack: (string)file_get_contents($this->root() . '/docs/reference/assets.md'),
			message: 'the statement the catalogue cites is gone'
		);
	}//end testARealOrganisationsLogoIsNotMarkedRedistributable()

	/**
	 * The files another product may copy are exactly the logos marked redistributable.
	 *
	 * @return void
	 */
	public function testTheCopyListHoldsOnlyRedistributableLogos(): void {
		$catalogue = $this->catalogue();
		$files = $this->service()->redistributableLogos(catalogue: $catalogue);

		$expected = [];
		foreach ($catalogue['sets'] as $set) {
			if ($set['logo']['redistributable'] === true) {
				$expected = array_merge($expected, array_filter([$set['logo']['file'], $set['logo']['inverse_file']]));
			}
		}

		sort($expected, SORT_STRING);

		$this->assertSame(expected: $expected, actual: $files);
		$this->assertContains(needle: 'img/logos/zuiddrecht-dark.svg', haystack: $files);
		$this->assertNotContains(needle: 'img/logos/rijkshuisstijl.svg', haystack: $files);
		$this->assertNotContains(needle: 'img/logos/utrecht.svg', haystack: $files);
		$this->assertSame(expected: count($files), actual: count(array_unique(array_map('basename', $files))), message: 'two logos would overwrite each other in one directory');
	}//end testTheCopyListHoldsOnlyRedistributableLogos()

	/**
	 * A font family is redistributable only with files, a licence REUSE.toml names, and the notice that must travel with them.
	 *
	 * @return void
	 */
	public function testAFontIsRedistributableOnlyUnderAStatedLicence(): void {
		$fonts = array_column($this->catalogue()['fonts'], null, 'family');
		$named = array_merge(...array_values(array_map(static fn (array $font): array => $font['sets'], $fonts)));
		$this->assertContains(needle: 'zuiddrecht', haystack: $named);

		foreach ($fonts as $family => $font) {
			$this->assertSame(expected: ['family', 'sets', 'shipped', 'files', 'licence', 'licence_files', 'redistributable', 'basis'], actual: array_keys($font), message: $family);
			$this->assertNotSame(expected: [], actual: $font['sets'], message: $family);
			$this->assertSame(expected: ($font['files'] !== []), actual: $font['shipped'], message: $family);
			foreach (array_merge($font['files'], $font['licence_files']) as $file) {
				$this->assertFileExists(filename: $this->root() . '/' . $file, message: $family);
			}

			if ($font['redistributable'] === true) {
				$this->assertContains(needle: $font['licence'], haystack: ['OFL-1.1', 'Apache-2.0'], message: $family);
				$this->assertNotSame(expected: [], actual: $font['files'], message: $family);
				$this->assertNotSame(expected: [], actual: $font['licence_files'], message: $family . ': the licence asks for its notice to travel with the files');
				$this->assertFileExists(filename: $this->root() . '/LICENSES/' . $font['licence'] . '.txt', message: $family);
			}
		}

		$this->assertTrue(condition: $fonts['Fira Sans']['redistributable']);
		$this->assertSame(expected: 'OFL-1.1', actual: $fonts['Fira Sans']['licence']);
		$this->assertTrue(condition: $fonts['Inter']['redistributable']);
		$this->assertSame(expected: 'Apache-2.0', actual: $fonts['Clear Sans']['licence']);

		// Shipped, and still not ours to pass on.
		$this->assertTrue(condition: $fonts['Marianne']['shipped']);
		$this->assertFalse(condition: $fonts['Marianne']['redistributable']);
		$this->assertStringContainsString(needle: 'MARIANNE-LICENCE.md', haystack: $fonts['Marianne']['basis']);

		// Named by a set, not shipped, and the set says why.
		foreach (['Avenir', 'DIN', 'RijksoverheidSans', 'Calibri', 'TheSans'] as $family) {
			$this->assertFalse(condition: $fonts[$family]['shipped'], message: $family);
			$this->assertFalse(condition: $fonts[$family]['redistributable'], message: $family);
		}

		// A font the operating system brings: nothing shipped, nothing claimed.
		$this->assertFalse(condition: $fonts['Ubuntu']['shipped']);
		$this->assertNull(actual: $fonts['Ubuntu']['redistributable']);
	}//end testAFontIsRedistributableOnlyUnderAStatedLicence()

	/**
	 * The vocabulary is what a set declares itself beyond the roles, with resolved values that mean something elsewhere.
	 *
	 * @return void
	 */
	public function testTheVocabularyIsTheSetsOwnTokensBeyondTheRoles(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());
		$zuiddrecht = $sets['zuiddrecht']['vocabulary'];

		$this->assertSame(expected: '#CC0000', actual: $zuiddrecht['color-error']);
		$this->assertSame(expected: '#A30000', actual: $zuiddrecht['component-navigation-active-color']);
		$this->assertSame(expected: '5px', actual: $zuiddrecht['brand-stripe-height']);
		$this->assertArrayNotHasKey(key: 'color-primary', array: $zuiddrecht, message: 'a role name stays in tokens');
		$this->assertArrayNotHasKey(key: 'logo-url', array: $zuiddrecht, message: 'an address is a file in another product');
		$this->assertArrayNotHasKey(key: 'component-heading-1-color', array: $zuiddrecht, message: 'a default the set does not declare is not its own');
		$keys = array_keys($zuiddrecht);
		$sorted = $keys;
		sort($sorted, SORT_STRING);
		$this->assertSame(expected: $sorted, actual: $keys);

		// A set that declares nothing beyond the roles has no vocabulary at all.
		$this->assertArrayNotHasKey(key: 'vocabulary', array: $sets['summer-breeze']);

		$names = ShellCatalogueVocabulary::ROLE_NAMES;
		foreach ($sets as $id => $set) {
			foreach (($set['vocabulary'] ?? []) as $key => $value) {
				$this->assertNotContains(needle: '--nldesign-' . $key, haystack: $names, message: $id . ' ' . $key);
				$this->assertDoesNotMatchRegularExpression(pattern: '/-(url|image|image-inverse|clip-path)$/', string: $key, message: $id);
				$this->assertStringNotContainsString(needle: 'var(', haystack: $value, message: $id . ' ' . $key);
				$this->assertStringNotContainsString(needle: 'url(', haystack: $value, message: $id . ' ' . $key);
				$this->assertNotSame(expected: '', actual: $value, message: $id . ' ' . $key);
			}
		}
	}//end testTheVocabularyIsTheSetsOwnTokensBeyondTheRoles()

	/**
	 * The dark block carries the dark variant's own vocabulary, under the same rules as the light one.
	 *
	 * @return void
	 */
	public function testTheDarkBlockCarriesTheDarkVariantsVocabulary(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());
		$dark = $sets['zuiddrecht']['dark']['vocabulary'];

		$this->assertSame(expected: '#f43535', actual: $dark['color-error']);
		$this->assertSame(expected: '#ff5c5c', actual: $dark['component-navigation-active-color']);
		$this->assertNotSame(expected: $sets['zuiddrecht']['vocabulary']['color-error'], actual: $dark['color-error']);
		$this->assertArrayNotHasKey(key: 'color-background', array: $dark, message: 'a role name stays in the dark roles');

		foreach ($sets as $id => $set) {
			foreach (($set['dark']['vocabulary'] ?? []) as $key => $value) {
				$this->assertNotContains(needle: '--nldesign-' . $key, haystack: ShellCatalogueVocabulary::ROLE_NAMES, message: $id . ' ' . $key);
				$this->assertStringNotContainsString(needle: 'var(', haystack: $value, message: $id . ' ' . $key);
			}
		}
	}//end testTheDarkBlockCarriesTheDarkVariantsVocabulary()

	/**
	 * Each role name is a token some shipped set declares, so the list holds the names the roles really come from.
	 *
	 * @return void
	 */
	public function testEveryRoleNameIsAShippedToken(): void {
		$css = '';
		foreach ((array)glob($this->root() . '/css/tokens/*.css') as $file) {
			$css .= (string)file_get_contents((string)$file);
		}

		foreach (ShellCatalogueVocabulary::ROLE_NAMES as $name) {
			$this->assertStringContainsString(needle: $name . ':', haystack: $css, message: $name);
		}
	}//end testEveryRoleNameIsAShippedToken()
}//end class
