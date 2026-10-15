<?php

/**
 * A colour keeps the text it was written in through a download and an upload.
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
 * @spec openspec/changes/colour-notation-kept/specs/colour-notation-kept/spec.md#requirement-a-colour-keeps-the-text-it-was-written-in
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensWriter;
use PHPUnit\Framework\TestCase;

/**
 * The writer keeps the text, the mapper reads it back while it still names the colour.
 *
 * @spec openspec/changes/colour-notation-kept/specs/colour-notation-kept/spec.md#requirement-a-colour-keeps-the-text-it-was-written-in
 */
final class ColourNotationTest extends TestCase {

	/**
	 * The declarations, in several notations.
	 *
	 * @var array<string, string>
	 */
	private const DECLARATIONS = [
		'--nldesign-color-a' => 'rgb(54, 105, 165)',
		'--nldesign-color-b' => 'hsl(210 50% 40%)',
		'--nldesign-color-c' => 'rgba(0, 0, 0, 0.5)',
		'--nldesign-color-d' => '#ABC',
		'--nldesign-color-e' => '#3669a5',
		'--nldesign-color-f' => 'red',
	];

	/**
	 * The download of the declarations, decoded.
	 *
	 * @return array<string, mixed>
	 */
	private function download(): array {
		return (array)json_decode((string)json_encode((new DesignTokensWriter())->write(declarations: self::DECLARATIONS, setId: 'x', setName: 'X', appVersion: '1')), true);
	}//end download()

	/**
	 * Scenario: every notation comes back as it was written.
	 *
	 * @return void
	 */
	public function testEveryNotationComesBack(): void {
		$document = $this->download();

		$this->assertSame('rgb(54, 105, 165)', $document['nldesign']['color']['a']['$extensions']['nl.conduction.thematiq']['css']);
		$this->assertArrayNotHasKey('css', $document['nldesign']['color']['e']['$extensions']['nl.conduction.thematiq'], 'a hex needs no copy');
		$this->assertSame(self::DECLARATIONS, (new DesignTokensMapper())->map(document: $document)['declarations']);
	}//end testEveryNotationComesBack()

	/**
	 * Scenario: a tool changed the colour and left the text: the object wins.
	 *
	 * @return void
	 */
	public function testAChangedColourIsNotOverwrittenByItsOldText(): void {
		$document = $this->download();
		$document['nldesign']['color']['a']['$value'] = ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'hex' => '#000000'];
		$document['nldesign']['color']['b']['$extensions']['nl.conduction.thematiq']['css'] = 'not a colour';
		$document['nldesign']['color']['c']['$extensions']['nl.conduction.thematiq']['css'] = 7;

		$declarations = (new DesignTokensMapper())->map(document: $document)['declarations'];

		$this->assertSame('#000000', $declarations['--nldesign-color-a']);
		$this->assertSame('#336699', $declarations['--nldesign-color-b']);
		$this->assertSame('#00000080', $declarations['--nldesign-color-c']);
	}//end testAChangedColourIsNotOverwrittenByItsOldText()

	/**
	 * Scenario: the OpenProject plugin's own copy of the text is read too.
	 *
	 * @return void
	 */
	public function testThePluginsTextIsReadToo(): void {
		$document = [
			'nldesign' => [
				'color' => [
					'primary' => [
						'$type' => 'color',
						'$value' => ['colorSpace' => 'srgb', 'components' => [0.2118, 0.4118, 0.6471], 'hex' => '#3669a5'],
						'$extensions' => ['nl.conduction.openproject-thematiq' => ['css' => 'rgb(54 105 165)']],
					],
				],
			],
		];

		$this->assertSame(['--nldesign-color-primary' => 'rgb(54 105 165)'], (new DesignTokensMapper())->map(document: $document)['declarations']);
	}//end testThePluginsTextIsReadToo()
}//end class
