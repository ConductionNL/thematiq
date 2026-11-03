<?php

/**
 * Seeded golden test for DesignTokensAliasResolver::resolve().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokensAliasResolver;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Resolves every leaf of seeded leaf tables whose values are literals, aliases to other leaves,
 * aliases to missing paths, and chains long enough to pass the depth limit, with cycles.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\DesignTokensAliasResolver
 * @uses   \OCA\Thematiq\Service\AliasWalk
 */
class DesignTokensAliasResolverGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/design-tokens-alias-resolver.golden.json';

	/**
	 * Every leaf of every seeded table resolves as recorded.
	 *
	 * @return void
	 */
	public function testResolutionMatchesTheGoldenRecording(): void {
		$resolver = new DesignTokensAliasResolver();
		$actual = [];
		for ($seed = 1; $seed <= 120; $seed++) {
			$random = new Randomizer(engine: new Mt19937(seed: $seed));
			$count = $random->getInt(2, 16);
			$leaves = [];
			for ($i = 0; $i < $count; $i++) {
				$draw = $random->getInt(0, 9);
				$value = '#' . str_pad(dechex($i * 1000), 6, '0', STR_PAD_LEFT);
				if ($draw < 6) {
					$value = '{t.' . $random->getInt(0, ($count - 1)) . '}';
				} elseif ($draw === 6) {
					$value = '{ t.' . $random->getInt(0, ($count + 3)) . ' }';
				} elseif ($draw === 7) {
					$value = ['colorSpace' => 'srgb'];
				}

				$leaves['t.' . $i] = ['value' => $value, 'type' => [null, 'color', 'dimension'][$random->getInt(0, 2)]];
			}

			if ($random->getInt(0, 3) === 0) {
				for ($i = 0; $i < 13; $i++) {
					$leaves['chain.' . $i] = ['value' => '{chain.' . ($i + 1) . '}', 'type' => 'color'];
				}

				$leaves['chain.13'] = ['value' => '#123456', 'type' => 'color'];
			}

			$results = [];
			foreach (array_keys($leaves) as $path) {
				$results[$path] = $resolver->resolve(startPath: $path, leaves: $leaves);
			}

			$actual['seed:' . $seed] = (string)json_encode($results);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testResolutionMatchesTheGoldenRecording()
}//end class
