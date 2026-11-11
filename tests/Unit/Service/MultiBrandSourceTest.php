<?php

/**
 * Unit tests for MultiBrandSource.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\MultiBrandSource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Golden of the brands a source holds and what each brand is cut to.
 */
class MultiBrandSourceTest extends TestCase {

	/**
	 * A Tokens Studio document with four themes, a set order that leaves sets out, and overlapping sets.
	 *
	 * @return string The document.
	 */
	private function document(): string {
		return (string)json_encode(
			[
				'core' => ['color' => ['a' => ['$value' => '#111'], 'b' => ['value' => '#222']], 'size' => ['$value' => '1px']],
				'zeta' => ['color' => ['a' => ['$value' => '#zz1']], 'extra' => ['value' => ['nested' => 1]]],
				'alpha' => ['color' => ['b' => ['$value' => '#aa2'], 'c' => ['$value' => '#aa3']]],
				'brand-x' => ['color' => ['a' => ['$value' => '#x01']], 'only-x' => ['$value' => 'x']],
				'ref' => ['ref' => ['r1' => ['$value' => 'r'], 'r2' => ['$value' => 'r']], 'color' => ['a' => ['$value' => '#ref']]],
				'$metadata' => ['tokenSetOrder' => ['ref', 'brand-x', 'core', 7]],
				'$themes' => [
					['id' => 'x', 'name' => 'Brand X', 'group' => 'Brands', 'selectedTokenSets' => ['core' => 'enabled', 'brand-x' => 'enabled', 'ref' => 'source', 'zeta' => 'disabled']],
					['id' => 'y', 'selectedTokenSets' => ['zeta' => 'enabled', 'alpha' => 'enabled', 'core' => 'source', 'missing' => 'enabled']],
					['name' => 'no id', 'selectedTokenSets' => ['core' => 'enabled']],
					'not a theme',
					['id' => 'z', 'name' => 'Zed', 'group' => 7, 'selectedTokenSets' => []],
				],
			]
		);
	}//end document()

	/**
	 * The brands found, with their keys, names, groups and token counts.
	 *
	 * @return void
	 */
	public function testTheBrandsFound(): void {
		$this->assertSame(
			expected: [
				['key' => 'x', 'name' => 'Brand X', 'tokenCount' => 4, 'group' => 'Brands'],
				['key' => 'y', 'name' => 'y', 'tokenCount' => 3],
				['key' => 'z', 'name' => 'Zed', 'tokenCount' => 0],
			],
			actual: (new MultiBrandSource())->detectBrands(content: $this->document())
		);

		$css = ".brand-one-theme { --a: 1; --b: 2; }\n.brand-two-theme { --a: 3; }\n:root { --c: 4; }";
		$this->assertSame(expected: [], actual: (new MultiBrandSource())->detectBrands(content: '{"$themes": [{"id": "only"}]}'));
		$this->assertIsArray(actual: (new MultiBrandSource())->detectBrands(content: $css));
	}//end testTheBrandsFound()

	/**
	 * Each brand cut in set order, later sets winning, and the paths only a source set defines.
	 *
	 * @return void
	 */
	public function testEachBrandCut(): void {
		$source = new MultiBrandSource();
		$this->assertSame(
			expected: [
				'content' => '{"ref":{"r1":{"$value":"r"},"r2":{"$value":"r"}},"color":{"a":{"$value":"#111"},"b":{"value":"#222"}},"only-x":{"$value":"x"},"size":{"$value":"1px"}}',
				'referenceOnlyPaths' => ['ref.r1', 'ref.r2'],
			],
			actual: $source->cut(content: $this->document(), key: 'x')
		);
		$this->assertSame(
			expected: [
				'content' => '{"color":{"a":{"$value":"#zz1"},"b":{"$value":"#aa2"},"c":{"$value":"#aa3"}},"size":{"$value":"1px"},"extra":{"value":{"nested":1}}}',
				'referenceOnlyPaths' => ['size'],
			],
			actual: $source->cut(content: $this->document(), key: 'y')
		);

		try {
			$source->cut(content: $this->document(), key: 'nope');
			$this->fail(message: 'an unknown brand was cut');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: ['unknown brand: nope', 422], actual: [$e->getMessage(), $e->getCode()]);
		}
	}//end testEachBrandCut()
}//end class
