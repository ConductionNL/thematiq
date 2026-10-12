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
 * @spec openspec/changes/shell-catalogue/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 * @spec openspec/changes/shell-catalogue/specs/shell-catalogue/spec.md#requirement-the-catalogue-is-reproducible
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShellCatalogueService;
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
 * @spec openspec/changes/shell-catalogue/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
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
			new ShippedTokenSetAuditService(new ContrastService(), new CssParserService()),
			new ShellRoleMapper(new ContrastService())
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
			$this->service()->render(catalogue: $this->catalogue()),
			(string)file_get_contents($this->root() . '/docs/reference/shell-catalogue.json'),
			'docs/reference/shell-catalogue.json is stale. Run: composer docs:shell-catalogue'
		);
	}//end testTheCommittedCatalogueIsCurrent()

	/**
	 * Two runs over the same tree give the same catalogue, and it carries no date.
	 *
	 * @return void
	 */
	public function testTheCatalogueIsReproducible(): void {
		$first = $this->service()->render(catalogue: $this->catalogue());

		$this->assertSame($first, $this->service()->render(catalogue: $this->catalogue()));
		$this->assertDoesNotMatchRegularExpression('/20\d\d-\d\d-\d\dT/', $first, 'a timestamp would make every run differ');
		$this->assertMatchesRegularExpression('/^sets:[0-9a-f]{16}$/', $this->catalogue()['generated_from']);
		$this->assertSame('thematiq 1.2.3', $this->service()->build(appPath: $this->root(), generatedFrom: 'thematiq 1.2.3')['generated_from']);
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

		$this->assertSame(['version', 'generated_from', 'sets', 'fonts'], array_keys($catalogue));
		$this->assertSame(1, $catalogue['version']);
		$this->assertGreaterThan(60, count($shipped));
		$this->assertSame($shipped, array_column($catalogue['sets'], 'id'));

		foreach ($catalogue['sets'] as $set) {
			$this->assertSame(['id', 'name', 'tokens', 'logo'], array_keys($set), $set['id']);
			$this->assertNotSame('', $set['name'], $set['id']);
			$this->assertSame(['file', 'inverse_file', 'licence', 'redistributable', 'basis'], array_keys($set['logo']), $set['id']);
		}

		$sets = $this->setsById(catalogue: $catalogue);
		$this->assertSame('Gemeente Zuiddrecht', $sets['zuiddrecht']['name']);
		$this->assertSame('Conduction', $sets['conduction']['name'], 'a set without a manifest entry is named after its id');
	}//end testEveryShippedSetIsInTheCatalogueOnce()

	/**
	 * Every set has all eighteen roles, each with a value, and they are the endpoint's roles.
	 *
	 * @return void
	 */
	public function testEverySetHasAllEighteenRoles(): void {
		$catalogue = $this->catalogue();
		foreach ($catalogue['sets'] as $set) {
			$this->assertSame(ShellRoleMapper::ROLES, array_keys($set['tokens']), $set['id']);
			foreach ($set['tokens'] as $role => $value) {
				$this->assertIsString($value, $set['id'] . ' ' . $role);
				$this->assertNotSame('', trim($value), $set['id'] . ' ' . $role);
			}
		}

		// The same call the shell tokens endpoint makes for a set without an administrator's values.
		$resolver = new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());
		$this->assertSame(
			(new ShellRoleMapper(new ContrastService()))->map(tokens: $resolver->resolveDeclarations(appPath: $this->root(), id: 'zuiddrecht', theming: [])),
			$this->setsById(catalogue: $catalogue)['zuiddrecht']['tokens']
		);
	}//end testEverySetHasAllEighteenRoles()

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
					$this->assertFileExists($this->root() . '/' . $set['logo'][$key], $id);
				}
			}

			if ($set['logo']['file'] === null) {
				$this->assertNull($set['logo']['redistributable'], $id);
				$this->assertNull($set['logo']['licence'], $id);
			}
		}

		$this->assertSame('img/logos/zuiddrecht.svg', $sets['zuiddrecht']['logo']['file']);
		$this->assertSame('img/logos/zuiddrecht-dark.svg', $sets['zuiddrecht']['logo']['inverse_file']);
		$this->assertNull($sets['utrecht']['logo']['inverse_file']);
		$this->assertNull($sets['nextcloud']['logo']['file']);
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
				(preg_match("/does not exist|Not a real organisation|Conduction's own brand/i", $description) === 1
					|| preg_match('/Fictional example logo|logo placeholder/i', $file) === 1),
				$id . ' is marked redistributable and nothing in the repository says the logo is ours'
			);
			$this->assertSame('EUPL-1.2', $set['logo']['licence'], $id);
			$this->assertNotSame('', (string)$set['logo']['basis'], $id);
		}

		$this->assertGreaterThan(0, $true);

		// A made-up municipality, a stand-in we drew, and our own brand.
		$this->assertTrue($sets['zuiddrecht']['logo']['redistributable']);
		$this->assertTrue($sets['example-gemeente']['logo']['redistributable']);
		$this->assertTrue($sets['dinkelland']['logo']['redistributable']);
		$this->assertTrue($sets['frankendesk']['logo']['redistributable']);
		$this->assertTrue($sets['opencatalogi']['logo']['redistributable'], 'OpenCatalogi is a Conduction product, and token-sets.json says so');
	}//end testALogoIsRedistributableOnlyOnEvidence()

	/**
	 * A real organisation's logo is never marked redistributable on the repository default alone.
	 *
	 * @return void
	 */
	public function testARealOrganisationsLogoIsNotMarkedRedistributable(): void {
		$sets = $this->setsById(catalogue: $this->catalogue());

		foreach (['amsterdam', 'denhaag', 'rotterdam', 'utrecht', 'vng', 'nijmegen', 'provincie-zuid-holland', 'xxllnc'] as $id) {
			$this->assertNull($sets[$id]['logo']['redistributable'], $id . ': the repository does not say, so the catalogue does not either');
			$this->assertStringContainsString('holder of the artwork is not stated', (string)$sets[$id]['logo']['licence'], $id);
		}

		// The Rijksoverheid mark: the repository's own asset page quotes the prohibition.
		$this->assertFalse($sets['rijkshuisstijl']['logo']['redistributable']);
		$this->assertStringContainsString('docs/reference/assets.md', (string)$sets['rijkshuisstijl']['logo']['basis']);
		$this->assertStringContainsString(
			'Gebruik hiervan is strikt verboden',
			(string)file_get_contents($this->root() . '/docs/reference/assets.md'),
			'the statement the catalogue cites is gone'
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

		$this->assertSame($expected, $files);
		$this->assertContains('img/logos/zuiddrecht-dark.svg', $files);
		$this->assertNotContains('img/logos/rijkshuisstijl.svg', $files);
		$this->assertNotContains('img/logos/utrecht.svg', $files);
		$this->assertSame(count($files), count(array_unique(array_map('basename', $files))), 'two logos would overwrite each other in one directory');
	}//end testTheCopyListHoldsOnlyRedistributableLogos()

	/**
	 * A font family is redistributable only with files, a licence REUSE.toml names, and the notice that must travel with them.
	 *
	 * @return void
	 */
	public function testAFontIsRedistributableOnlyUnderAStatedLicence(): void {
		$fonts = array_column($this->catalogue()['fonts'], null, 'family');
		$named = array_merge(...array_values(array_map(static fn (array $font): array => $font['sets'], $fonts)));
		$this->assertContains('zuiddrecht', $named);

		foreach ($fonts as $family => $font) {
			$this->assertSame(['family', 'sets', 'shipped', 'files', 'licence', 'licence_files', 'redistributable', 'basis'], array_keys($font), $family);
			$this->assertNotSame([], $font['sets'], $family);
			$this->assertSame(($font['files'] !== []), $font['shipped'], $family);
			foreach (array_merge($font['files'], $font['licence_files']) as $file) {
				$this->assertFileExists($this->root() . '/' . $file, $family);
			}

			if ($font['redistributable'] === true) {
				$this->assertContains($font['licence'], ['OFL-1.1', 'Apache-2.0'], $family);
				$this->assertNotSame([], $font['files'], $family);
				$this->assertNotSame([], $font['licence_files'], $family . ': the licence asks for its notice to travel with the files');
				$this->assertFileExists($this->root() . '/LICENSES/' . $font['licence'] . '.txt', $family);
			}
		}

		$this->assertTrue($fonts['Fira Sans']['redistributable']);
		$this->assertSame('OFL-1.1', $fonts['Fira Sans']['licence']);
		$this->assertTrue($fonts['Inter']['redistributable']);
		$this->assertSame('Apache-2.0', $fonts['Clear Sans']['licence']);

		// Shipped, and still not ours to pass on.
		$this->assertTrue($fonts['Marianne']['shipped']);
		$this->assertFalse($fonts['Marianne']['redistributable']);
		$this->assertStringContainsString('MARIANNE-LICENCE.md', $fonts['Marianne']['basis']);

		// Named by a set, not shipped, and the set says why.
		foreach (['Avenir', 'DIN', 'RijksoverheidSans', 'Calibri', 'TheSans'] as $family) {
			$this->assertFalse($fonts[$family]['shipped'], $family);
			$this->assertFalse($fonts[$family]['redistributable'], $family);
		}

		// A font the operating system brings: nothing shipped, nothing claimed.
		$this->assertFalse($fonts['Ubuntu']['shipped']);
		$this->assertNull($fonts['Ubuntu']['redistributable']);
	}//end testAFontIsRedistributableOnlyUnderAStatedLicence()
}//end class
