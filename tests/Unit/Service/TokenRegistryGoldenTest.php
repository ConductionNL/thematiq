<?php

/**
 * Golden test for TokenRegistry's file-backed token lists.
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
 * @spec openspec/specs/component-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Reads the settable, component and internal token lists, the theme stock, and everything built
 * on them, from the shipped mapping files with cold caches, in three read orders (the later two
 * recorded as a SHA-256 of the same record). Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\TokenRegistry
 */
class TokenRegistryGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/token-registry.golden.json';

	/**
	 * Every list reads as recorded.
	 *
	 * @return void
	 */
	public function testListsMatchTheGoldenRecording(): void {
		$actual = [];
		foreach (['settable', 'component', 'internal'] as $first) {
			$this->resetCaches();
			$lists = [];
			if ($first === 'settable') {
				$lists['settable'] = TokenRegistry::getSettableTokens();
			}

			if ($first === 'component') {
				$lists['component'] = TokenRegistry::getComponentTokens();
			}

			$lists['internal'] = TokenRegistry::getInternalTokens();
			$lists['settable'] = TokenRegistry::getSettableTokens();
			$lists['component'] = TokenRegistry::getComponentTokens();
			$lists['stock'] = (new ReflectionProperty(TokenRegistry::class, 'themeStock'))->getValue();
			$lists['tokens'] = TokenRegistry::getTokens();
			$lists['byTab'] = TokenRegistry::getTokensByTab();
			$lists['editable'] = TokenRegistry::countEditable();
			$json = (string)json_encode($lists, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
			if ($first !== 'settable') {
				$json = hash(algo: 'sha256', data: $json);
			}

			$actual['first:' . $first] = $json;
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testListsMatchTheGoldenRecording()

	/**
	 * Clear the registry's static caches so each case reads the files again.
	 *
	 * @return void
	 */
	private function resetCaches(): void {
		foreach (['componentTokens', 'settableTokens', 'internalTokens'] as $cache) {
			(new ReflectionProperty(TokenRegistry::class, $cache))->setValue(null, null);
		}

		(new ReflectionProperty(TokenRegistry::class, 'themeStock'))->setValue(null, []);
	}//end resetCaches()

	/**
	 * Leave the caches cold for the tests that follow.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->resetCaches();
	}//end tearDown()
}//end class
