<?php

/**
 * DesignTokensWriter: paths, the thematiq extension, one case per row of the typing table,
 * aliases, the cssOnly map, colour objects, and deprecations as `$deprecated`.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensWriter;
use PHPUnit\Framework\TestCase;

/**
 * The document shape.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */
final class DesignTokensWriterTest extends TestCase {

	/**
	 * Write and decode, as a download would.
	 *
	 * @param array<string, string> $declarations The set.
	 * @param array<string, array<string, mixed>> $deprecations Deprecations.
	 *
	 * @return array<string, mixed>
	 */
	private function write(array $declarations, array $deprecations = []): array {
		$document = (new DesignTokensWriter())->write(
			declarations: $declarations,
			setId: 'voorbeeld',
			setName: 'Gemeente Voorbeeld',
			appVersion: '1.2.3',
			deprecations: $deprecations
		);

		return json_decode((string)json_encode($document), true);
	}//end write()

	/**
	 * Scenario: a semantic token gets a readable path and its CSS name; the root names the set.
	 *
	 * @return void
	 */
	public function testPathExtensionAndRoot(): void {
		$doc = $this->write(declarations: ['--nldesign-color-primary-hover' => '#0f3059', '--utrecht-button-background-color' => '#154273']);
		$token = $doc['nldesign']['color']['primary-hover'];

		$this->assertSame(expected: '--nldesign-color-primary-hover', actual: $token['$extensions']['nl.conduction.thematiq']['cssVariable']);
		$this->assertArrayHasKey(key: 'background-color', array: $doc['utrecht']['button']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $doc['$description']);
		$this->assertSame(expected: 'voorbeeld', actual: $doc['$extensions']['nl.conduction.thematiq']['setId']);
		$this->assertSame(expected: '1.2.3', actual: $doc['$extensions']['nl.conduction.thematiq']['appVersion']);
	}//end testPathExtensionAndRoot()

	/**
	 * One case per row of the typing table (design decision 4).
	 *
	 * @return void
	 */
	public function testTypingTable(): void {
		$doc = $this->write(
			declarations: [
				'--nldesign-animation-quick' => '100ms',
				'--nldesign-animation-easing' => 'cubic-bezier(0.2, 0, 0, 1)',
				'--nldesign-border-radius' => '8px',
				'--nldesign-spacing-base' => '1.5rem',
				'--nldesign-font-family' => "'Fira Sans', sans-serif",
				'--nldesign-font-weight-bold' => '700',
				'--nldesign-line-height' => '1.5',
				'--nldesign-color-primary' => '#154273',
			]
		);

		$this->assertSame(
			expected: ['$type' => 'duration', '$value' => ['value' => 100, 'unit' => 'ms']],
			actual: array_diff_key($doc['nldesign']['animation']['quick'], ['$extensions' => 1])
		);
		$this->assertSame(
			expected: ['$type' => 'cubicBezier', '$value' => [0.2, 0, 0, 1]],
			actual: array_diff_key($doc['nldesign']['animation']['easing'], ['$extensions' => 1])
		);
		$this->assertSame(expected: ['value' => 8, 'unit' => 'px'], actual: $doc['nldesign']['border']['radius']['$value']);
		$this->assertSame(expected: 'dimension', actual: $doc['nldesign']['spacing']['base']['$type']);
		$this->assertSame(
			expected: ['fontFamily', ['Fira Sans', 'sans-serif']],
			actual: [$doc['nldesign']['font']['family']['$type'], $doc['nldesign']['font']['family']['$value']]
		);
		$this->assertSame(
			expected: ['fontWeight', 700],
			actual: [$doc['nldesign']['font']['weight-bold']['$type'], $doc['nldesign']['font']['weight-bold']['$value']]
		);
		$this->assertSame(
			expected: ['number', 1.5],
			actual: [$doc['nldesign']['line']['height']['$type'], $doc['nldesign']['line']['height']['$value']]
		);
		$this->assertSame(expected: 'color', actual: $doc['nldesign']['color']['primary']['$type']);
	}//end testTypingTable()

	/**
	 * Scenario: an alias stays an alias, typed like its target.
	 *
	 * @return void
	 */
	public function testAliasStaysAnAlias(): void {
		$doc = $this->write(declarations: ['--nldesign-color-link' => 'var(--nldesign-color-primary)', '--nldesign-color-primary' => '#154273']);

		$this->assertSame(expected: '{nldesign.color.primary}', actual: $doc['nldesign']['color']['link']['$value']);
		$this->assertSame(expected: 'color', actual: $doc['nldesign']['color']['link']['$type']);
	}//end testAliasStaysAnAlias()

	/**
	 * Scenario: a gradient, a var() to a name outside the set and a 1.0rem are kept outside the tokens.
	 *
	 * @return void
	 */
	public function testUntypedValuesGoToCssOnly(): void {
		$gradient = 'linear-gradient(90deg, #154273, #01689b)';
		$doc = $this->write(declarations: ['--nldesign-header-background' => $gradient, '--nldesign-color-text' => 'var(--color-main-text)', '--nldesign-gap' => '1.0rem', '--nldesign-animation-bounce' => 'cubic-bezier(1.5, 0, 0, 1)']);
		$cssOnly = $doc['$extensions']['nl.conduction.thematiq']['cssOnly'];

		$this->assertSame(expected: $gradient, actual: $cssOnly['--nldesign-header-background']);
		$this->assertSame(expected: 'var(--color-main-text)', actual: $cssOnly['--nldesign-color-text']);
		$this->assertSame(expected: '1.0rem', actual: $cssOnly['--nldesign-gap'], message: 'written as 1rem it would not come back the same');
		$this->assertSame(
			expected: 'cubic-bezier(1.5, 0, 0, 1)',
			actual: $cssOnly['--nldesign-animation-bounce'],
			message: 'DTCG allows x only in 0..1'
		);
		$this->assertArrayNotHasKey(key: 'header', array: $doc['nldesign'] ?? []);
	}//end testUntypedValuesGoToCssOnly()

	/**
	 * Scenario: a hex brand colour becomes an sRGB object, rounded, without alpha.
	 *
	 * @return void
	 */
	public function testHexBecomesAnSrgbObject(): void {
		$doc = $this->write(declarations: ['--nldesign-color-primary' => '#154273']);

		$this->assertSame(
			expected: ['colorSpace' => 'srgb', 'components' => [0.0824, 0.2588, 0.451], 'hex' => '#154273'],
			actual: $doc['nldesign']['color']['primary']['$value']
		);
	}//end testHexBecomesAnSrgbObject()

	/**
	 * Scenario: an oklch colour keeps its colour space, with an sRGB hex fallback.
	 *
	 * @return void
	 */
	public function testOklchKeepsItsSpace(): void {
		$value = $this->write(declarations: ['--nldesign-color-primary' => 'oklch(0.5 0.1 250)'])['nldesign']['color']['primary']['$value'];

		$this->assertSame(expected: ['oklch', [0.5, 0.1, 250], '#32669a'], actual: [$value['colorSpace'], $value['components'], $value['hex']]);
	}//end testOklchKeepsItsSpace()

	/**
	 * Scenario: a translucent colour carries its alpha, the hex stays six digits.
	 *
	 * @return void
	 */
	public function testAlphaIsCarried(): void {
		$value = $this->write(declarations: ['--nldesign-color-primary-light' => '#15427380'])['nldesign']['color']['primary-light']['$value'];

		$this->assertSame(expected: 0.502, actual: $value['alpha']);
		$this->assertSame(expected: '#154273', actual: $value['hex']);
	}//end testAlphaIsCarried()

	/**
	 * Every colour object the writer makes is accepted by the importer.
	 *
	 * @return void
	 */
	public function testEveryColourObjectImports(): void {
		$colours = ['--nldesign-a-b' => 'navy', '--nldesign-a-c' => 'rgb(21 66 115 / 50%)', '--nldesign-a-d' => 'hsl(210, 50%, 40%)', '--nldesign-a-e' => 'lab(50 20 -30)', '--nldesign-a-f' => 'color(display-p3 0.5 0.3 0.7)', '--nldesign-a-g' => 'hwb(210 20% 30%)'];
		$result = (new DesignTokensMapper())->map(document: $this->write(declarations: $colours));

		$this->assertSame(expected: [], actual: $result['errors']);
		// The sRGB notations come back as they were written (colour-notation-kept); lab() and display-p3 as sRGB hex.
		$this->assertSame(
			expected: ['--nldesign-a-b' => 'navy', '--nldesign-a-c' => 'rgb(21 66 115 / 50%)', '--nldesign-a-d' => 'hsl(210, 50%, 40%)', '--nldesign-a-e' => '#856caa', '--nldesign-a-f' => '#8849b8', '--nldesign-a-g' => 'hwb(210 20% 30%)'],
			actual: $result['declarations']
		);
	}//end testEveryColourObjectImports()

	/**
	 * A name that would be a group and a token at once goes to cssOnly.
	 *
	 * @return void
	 */
	public function testGroupAndTokenClashGoesToCssOnly(): void {
		$doc = $this->write(declarations: ['--nldesign-color' => '#000000', '--nldesign-color-primary' => '#154273']);

		$this->assertSame(expected: '#000000', actual: $doc['$extensions']['nl.conduction.thematiq']['cssOnly']['--nldesign-color']);
		$this->assertSame(expected: '#154273', actual: $doc['nldesign']['color']['primary']['$value']['hex']);
	}//end testGroupAndTokenClashGoesToCssOnly()

	/**
	 * Lifecycle task 3.5: a deprecated token carries `$deprecated` and the extension fields.
	 *
	 * @return void
	 */
	public function testDeprecatedTokenCarriesNotice(): void {
		$doc = $this->write(
			declarations: ['--nldesign-color-primary-light' => '#e6ecf3', '--nldesign-color-primary' => '#154273'],
			deprecations: [
				'--nldesign-color-primary-light' => ['severity' => 'critical', 'replacement' => '--nldesign-color-primary', 'removalDate' => '2027-03-01', 'state' => 'active'],
				'--nldesign-color-primary' => ['severity' => 'info', 'state' => 'removed'],
			]
		);
		$token = $doc['nldesign']['color']['primary-light'];

		$this->assertSame(expected: 'Use --nldesign-color-primary instead; removal 2027-03-01', actual: $token['$deprecated']);
		$this->assertSame(
			expected: ['severity' => 'critical', 'replacement' => '--nldesign-color-primary', 'removalDate' => '2027-03-01'],
			actual: $token['$extensions']['nl.conduction.thematiq']['deprecation']
		);
		$this->assertArrayNotHasKey(
			key: '$deprecated',
			array: $doc['nldesign']['color']['primary'],
			message: 'a removed record is history, not a notice'
		);
	}//end testDeprecatedTokenCarriesNotice()
}//end class
