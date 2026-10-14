<?php

/**
 * The vocabulary of one set in the shell catalogue.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/shell-catalogue/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ShellCatalogueVocabulary;
use PHPUnit\Framework\TestCase;

/**
 * Picks what a set's own file declares, leaves out the rest.
 */
class ShellCatalogueVocabularyTest extends TestCase {

	/**
	 * What the file declares itself, with the resolved value, sorted; nothing else.
	 *
	 * @return void
	 */
	public function testItKeepsTheSetsOwnTokensWithTheirResolvedValues(): void {
		$css = ":root {\n\t--nldesign-color-error: var(--brand-red);\n\t--nldesign-color-warning: #7A4F00;\n"
			. "\t/* --nldesign-component-X: var(--utrecht-Y) */\n\t--zuiddrecht-color-red: #CC0000;\n}";
		$resolved = [
			'--nldesign-color-error' => '#CC0000',
			'--nldesign-color-warning' => ' #7A4F00 ',
			'--nldesign-component-heading-color' => '#111111',
		];

		$this->assertSame(
			['color-error' => '#CC0000', 'color-warning' => '#7A4F00'],
			(new ShellCatalogueVocabulary())->ofSet(css: $css, resolved: $resolved)
		);
	}//end testItKeepsTheSetsOwnTokensWithTheirResolvedValues()

	/**
	 * Role names, pictures, addresses, shapes and values without meaning elsewhere stay out.
	 *
	 * @return void
	 */
	public function testItLeavesOutWhatHasAnotherHomeOrNoMeaningElsewhere(): void {
		$names = ['--nldesign-color-primary', '--nldesign-logo-url', '--nldesign-brand-stripe-image', '--nldesign-brand-stripe-image-inverse',
			'--nldesign-hero-image-clip-path', '--nldesign-hero-title-color', '--nldesign-hero-body-color', '--nldesign-hero-action-color',
			'--nldesign-website-notice-color'];
		$css = ':root {' . implode('', array_map(static fn (string $name): string => $name . ': x;', $names)) . '}';
		$resolved = [
			'--nldesign-color-primary' => '#3669a5',
			'--nldesign-logo-url' => "url('x.svg')",
			'--nldesign-brand-stripe-image' => 'linear-gradient(red, blue)',
			'--nldesign-brand-stripe-image-inverse' => 'linear-gradient(red, blue)',
			'--nldesign-hero-image-clip-path' => 'polygon(0 0, 100% 0)',
			'--nldesign-hero-title-color' => 'var(--c-white)',
			'--nldesign-hero-body-color' => '  ',
			'--nldesign-hero-action-color' => "url('x')",
		];

		$this->assertSame([], (new ShellCatalogueVocabulary())->ofSet(css: $css, resolved: $resolved));
	}//end testItLeavesOutWhatHasAnotherHomeOrNoMeaningElsewhere()
}//end class
