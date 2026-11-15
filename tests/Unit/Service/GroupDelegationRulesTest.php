<?php

/**
 * Unit tests for GroupDelegationRules.
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
 * @spec openspec/specs/per-group-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\Exception\GroupThemingValidationException;
use OCA\Thematiq\Service\GroupDelegationRules;
use PHPUnit\Framework\TestCase;

/**
 * Golden of which delegations are stored and which are refused, with the reason.
 */
class GroupDelegationRulesTest extends TestCase {

	/**
	 * The validated group and set every case starts from.
	 *
	 * @var array{group: string, tokenSet: string}
	 */
	private const CLEAN = ['group' => 'editors', 'tokenSet' => 'utrecht'];

	/**
	 * Every submitted entry gives this stored entry or this refusal, and asks for these sets in this order.
	 *
	 * @return void
	 */
	public function testWhatIsStoredAndWhatIsRefused(): void {
		$cases = [
			'not an array' => ['x', self::CLEAN, []],
			'not delegated' => [['delegated' => false, 'allowedTokenSets' => 'ignored'], self::CLEAN, []],
			'flag absent' => [['allowedTokenSets' => ['utrecht']], self::CLEAN, []],
			'flag "yes"' => [['delegated' => 'yes', 'allowedTokenSets' => []], self::CLEAN, []],
			'flag "1"' => [['delegated' => '1', 'allowedTokenSets' => ['utrecht', 'leiden', 'utrecht']], self::CLEAN + ['delegated' => true, 'allowedTokenSets' => ['utrecht', 'leiden']], ['utrecht', 'leiden', 'utrecht']],
			'flag 1' => [['delegated' => 1, 'allowedTokenSets' => ['b' => 'leiden', 'a' => 'utrecht']], self::CLEAN + ['delegated' => true, 'allowedTokenSets' => ['leiden', 'utrecht']], ['leiden', 'utrecht']],
			'flag "true"' => [['delegated' => 'true', 'allowedTokenSets' => ['utrecht']], self::CLEAN + ['delegated' => true, 'allowedTokenSets' => ['utrecht']], ['utrecht']],
			'no list' => [['delegated' => true], 'A delegated group needs at least one allowed token set.', []],
			'empty list' => [['delegated' => true, 'allowedTokenSets' => []], 'A delegated group needs at least one allowed token set.', []],
			'list is a string' => [['delegated' => true, 'allowedTokenSets' => 'utrecht'], 'A delegated group needs at least one allowed token set.', []],
			'unavailable set' => [['delegated' => true, 'allowedTokenSets' => ['utrecht', 'gone', 'leiden']], 'Allowed token set "gone" is not available.', ['utrecht', 'gone']],
			'non-string set' => [['delegated' => true, 'allowedTokenSets' => ['utrecht', 7, 'gone']], 'Allowed token set 7 is not available.', ['utrecht']],
			'array set' => [['delegated' => true, 'allowedTokenSets' => [['x']]], 'Allowed token set ["x"] is not available.', []],
			'current set missing' => [['delegated' => true, 'allowedTokenSets' => ['leiden']], 'The allowed token sets of group "editors" must include its current set.', ['leiden']],
		];

		foreach ($cases as $name => [$entry, $expected, $asked]) {
			$seen = [];
			$isAvailable = static function (string $id) use (&$seen): bool {
				$seen[] = $id;

				return $id !== 'gone';
			};
			try {
				$actual = (new GroupDelegationRules())->validate(entry: $entry, clean: self::CLEAN, isAvailable: $isAvailable);
			} catch (GroupThemingValidationException $e) {
				$this->assertSame(expected: $entry, actual: $e->getEntry(), message: $name);
				$this->assertNull(actual: $e->getRestrictedSet(), message: $name);
				$actual = $e->getMessage();
			}

			$this->assertSame(expected: $expected, actual: $actual, message: $name);
			$this->assertSame(expected: $asked, actual: $seen, message: $name . ': sets asked');
		}
	}//end testWhatIsStoredAndWhatIsRefused()

	/**
	 * A stored entry reads as delegated only with the flag and a list holding a string.
	 *
	 * @return void
	 */
	public function testReadingAStoredEntry(): void {
		$cases = [
			[['delegated' => true, 'allowedTokenSets' => ['a', 3, 'b']], self::CLEAN + ['delegated' => true, 'allowedTokenSets' => ['a', 'b']]],
			[['delegated' => true, 'allowedTokenSets' => [3]], self::CLEAN],
			[['delegated' => '1', 'allowedTokenSets' => ['a']], self::CLEAN],
			[['delegated' => true, 'allowedTokenSets' => 'a'], self::CLEAN],
			[[], self::CLEAN],
		];
		foreach ($cases as [$entry, $expected]) {
			$this->assertSame(expected: $expected, actual: (new GroupDelegationRules())->read(entry: $entry, clean: self::CLEAN));
		}
	}//end testReadingAStoredEntry()
}//end class
