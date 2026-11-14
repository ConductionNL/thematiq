<?php

/**
 * Two capability rows pinned to the shipped files: community brand sets beside
 * the government ones, and a selection colour Nextcloud derives from the brand.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * No Nextcloud runtime required: it reads token-sets.json and the nldesign
 * overrides stylesheet from the repository.
 *
 * @spec openspec/changes/rows-without-a-requirement/proposal.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * cat-community-sets and sur-selection-colour.
 *
 * @coversNothing
 */
class CapabilityRowsTest extends TestCase {

	/**
	 * The repository root.
	 *
	 * @return string
	 */
	private static function root(): string {
		return \dirname(__DIR__, 2);
	}//end root()

	/**
	 * The shipped catalogue lists brand sets for organisations that are not a
	 * municipality, a province or VNG, and each has its token file.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-community-brand-sets-sit-beside-the-government-ones
	 */
	public function testTheCatalogueHoldsCommunitySetsBesideTheGovernmentOnes(): void {
		$sets = json_decode((string)file_get_contents(self::root() . '/token-sets.json'), true, 512, JSON_THROW_ON_ERROR);
		$this->assertIsArray(actual: $sets);

		$community = array_values(
			array_filter(
				$sets,
				static fn (array $set): bool => preg_match('/^(Gemeente|Provincie)\b/', (string)($set['name'] ?? '')) !== 1
					&& in_array($set['id'] ?? '', ['vng', 'nextcloud', 'rijkshuisstijl'], true) === false
			)
		);
		$ids = array_column($community, 'id');

		$this->assertContains(needle: 'opencatalogi', haystack: $ids);
		$this->assertContains(needle: 'demodam', haystack: $ids);
		foreach (['opencatalogi', 'demodam'] as $id) {
			$this->assertFileExists(filename: self::root() . '/css/tokens/' . $id . '.css');
		}
	}//end testTheCatalogueHoldsCommunitySetsBesideTheGovernmentOnes()

	/**
	 * The nldesign overrides map Nextcloud's primary element colour to the
	 * set's primary and leave the selection colour alone, so Nextcloud derives
	 * a translucent selection from the brand instead of an opaque fill.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/nextcloud-variable-mapping/spec.md#requirement-selected-text-takes-the-brand-colour-in-a-readable-translucent-form
	 */
	public function testTheSelectionColourIsLeftToNextcloud(): void {
		// Declarations only: the file explains in comments which variables it leaves alone.
		$css = (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(self::root() . '/css/systems/nldesign/overrides.css'));

		$this->assertMatchesRegularExpression(
			pattern: '/--color-primary-element:\s*var\(--nldesign-color-primary\)/',
			string: $css
		);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '/--color-background-selection\s*:/',
			string: $css,
			message: 'the overrides must not set the selection colour'
		);
	}//end testTheSelectionColourIsLeftToNextcloud()
}//end class
