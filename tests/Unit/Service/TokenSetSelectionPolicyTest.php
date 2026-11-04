<?php

/**
 * Unit tests for TokenSetSelectionPolicy: which token sets an administrator may
 * choose, and the inputs that must not be able to widen or break the picker.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * `TokenSetServiceSelectableTest` drives this policy over a real catalogue, so
 * what is left here is the policy's own edges: a malformed manifest, a corrupt
 * group mapping, an entry whose `warnings` is not a list. Each of those arrives
 * from appconfig or from disk, so each is a value nobody validated before it
 * reached this code.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenSetSelectionPolicy;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * The policy decides from data, and degrades safely when the data is wrong.
 */
class TokenSetSelectionPolicyTest extends TestCase {

	/**
	 * The policy under test.
	 *
	 * @return TokenSetSelectionPolicy The policy.
	 */
	private function policy(): TokenSetSelectionPolicy {
		return new TokenSetSelectionPolicy();
	}//end policy()

	/**
	 * A catalogue entry.
	 *
	 * @param string $id The set id.
	 * @param array<int, array<string, mixed>> $warnings The entry's warnings.
	 *
	 * @return array<string, mixed> The entry.
	 */
	private function entry(string $id, array $warnings = []): array {
		$set = ['id' => $id, 'name' => ucfirst($id)];
		if ($warnings !== []) {
			$set['warnings'] = $warnings;
		}

		return $set;
	}//end entry()

	/**
	 * The ids of a filtered result.
	 *
	 * @param array<int, array<string, mixed>> $entries The entries.
	 *
	 * @return array<int, string> The ids.
	 */
	private function ids(array $entries): array {
		return array_map(static fn (array $e): string => (string)$e['id'], $entries);
	}//end ids()

	/**
	 * A named set with no incomplete warning is offered; an unnamed one and a
	 * vocabulary-incomplete one are not. This is the whole rule in one test.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testTheTwoConditions(): void {
		$entries = [
			$this->entry(id: 'good'),
			$this->entry(id: 'unnamed'),
			$this->entry(id: 'broken', warnings: [['kind' => 'incomplete', 'missing' => ['--nldesign-color-text']]]),
		];

		$selectable = $this->policy()->filter($entries, ['good', 'broken'], '', []);

		$this->assertSame(expected: ['good'], actual: $this->ids(entries: $selectable));
	}//end testTheTwoConditions()

	/**
	 * A contrast warning is not a vocabulary warning: a set below AA is still
	 * offered, with the apply dialog's non-blocking warning doing its job. Only
	 * `kind: incomplete` withholds a set.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testOnlyTheIncompleteKindWithholdsASet(): void {
		$entries = [
			$this->entry(id: 'contrast', warnings: [['kind' => 'contrast', 'pair' => 'primary/bg', 'ratio' => '2.5']]),
			$this->entry(id: 'font', warnings: [['kind' => 'font', 'family' => 'Avenir']]),
			$this->entry(id: 'vocab', warnings: [['kind' => 'incomplete']]),
		];

		$selectable = $this->ids(entries: $this->policy()->filter($entries, ['contrast', 'font', 'vocab'], '', []));

		$this->assertSame(expected: ['contrast', 'font'], actual: $selectable);
	}//end testOnlyTheIncompleteKindWithholdsASet()

	/**
	 * The three survivors outrank both conditions: the active set, a set a
	 * group mapping points at, and every `custom-*` import.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testTheThreeSurvivorsOutrankBothConditions(): void {
		$entries = [
			$this->entry(id: 'active', warnings: [['kind' => 'incomplete']]),
			$this->entry(id: 'mapped'),
			$this->entry(id: 'custom-upload', warnings: [['kind' => 'incomplete']]),
			$this->entry(id: 'dropped', warnings: [['kind' => 'incomplete']]),
		];

		// None of them is named, so only the survival rules can keep them.
		$selectable = $this->ids(entries: $this->policy()->filter($entries, [], 'active', ['mapped']));

		$this->assertSame(expected: ['active', 'mapped', 'custom-upload'], actual: $selectable);
		$this->assertNotContains(needle: 'dropped', haystack: $selectable);
	}//end testTheThreeSurvivorsOutrankBothConditions()

	/**
	 * An empty active set adds nothing. Keying the survival rule on `''` would
	 * make every entry with no id match, which is how a filter silently stops
	 * filtering.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnEmptyActiveSetAddsNothing(): void {
		$entries = [['name' => 'no id at all'], $this->entry(id: 'named')];

		$this->assertSame(expected: ['named'], actual: $this->ids(entries: $this->policy()->filter($entries, ['named'], '', [])));
	}//end testAnEmptyActiveSetAddsNothing()

	/**
	 * `warnings` that is not a list is treated as no warnings rather than
	 * throwing: it arrives from the same appconfig an uploader writes, so it is
	 * not a value this code may assume the shape of.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testUnusableWarningsAreTreatedAsNone(): void {
		$policy = $this->policy();

		$this->assertFalse(condition: $policy->isVocabularyIncomplete(['id' => 'a', 'warnings' => 'oops']));
		$this->assertFalse(condition: $policy->isVocabularyIncomplete(['id' => 'a', 'warnings' => []]));
		$this->assertFalse(condition: $policy->isVocabularyIncomplete(['id' => 'a']));
		$this->assertFalse(condition: $policy->isVocabularyIncomplete(['id' => 'a', 'warnings' => ['not an array']]));
		$this->assertFalse(condition: $policy->isVocabularyIncomplete(['id' => 'a', 'warnings' => [['kind' => 'other']]]));

		// ...and the one shape that does withhold a set, so the above is not
		// passing on a check that answers false to everything.
		$this->assertTrue(condition: $policy->isVocabularyIncomplete(['id' => 'a', 'warnings' => [['kind' => 'incomplete']]]));
	}//end testUnusableWarningsAreTreatedAsNone()

	/**
	 * A group mapping that is not usable JSON, or whose entries are the wrong
	 * shape, yields no ids rather than throwing — and must not widen the picker
	 * either.
	 *
	 * @param string $mapping The stored appconfig value.
	 *
	 * @return void
	 *
	 * @dataProvider unusableMappingProvider
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnUnusableGroupMappingYieldsNoIds(string $mapping): void {
		$this->assertSame(expected: [], actual: $this->policy()->mappedIds($mapping));
	}//end testAnUnusableGroupMappingYieldsNoIds()

	/**
	 * Group-mapping values that carry no usable token set id.
	 *
	 * @return array<string, array{0: string}> The provider rows.
	 */
	public static function unusableMappingProvider(): array {
		return [
			'not json at all' => ['{not json'],
			'empty string' => [''],
			'json scalar' => ['"amsterdam"'],
			'json null' => ['null'],
			'entries are not arrays' => ['["amsterdam", "utrecht"]'],
			'entries carry no tokenSet' => ['[{"group": "gemeente"}]'],
			'tokenSet is not a string' => ['[{"group": "gemeente", "tokenSet": 42}]'],
			'empty list' => ['[]'],
		];
	}//end unusableMappingProvider()

	/**
	 * A usable mapping yields its ids, including duplicates across groups —
	 * the caller turns them into a lookup, so de-duplicating here would be
	 * work nobody reads.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAUsableGroupMappingYieldsItsIds(): void {
		$mapping = json_encode(
			[
				['group' => 'a', 'tokenSet' => 'amsterdam'],
				['group' => 'b', 'tokenSet' => 'utrecht'],
				['group' => 'c', 'tokenSet' => 'amsterdam'],
				'not an entry',
			]
		);

		$this->assertSame(
			expected: ['amsterdam', 'utrecht', 'amsterdam'],
			actual: $this->policy()->mappedIds((string)$mapping)
		);
	}//end testAUsableGroupMappingYieldsItsIds()

	/**
	 * A missing or malformed `token-sets.json` names no set, so the picker
	 * offers only the survivors.
	 *
	 * That is the safe direction: withholding a set an administrator could have
	 * picked is recoverable, offering an unnamed one is a theme with a generated
	 * name. The active set still survives, so the instance keeps working.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnUnusableManifestOffersOnlyTheSurvivors(): void {
		$probe = sys_get_temp_dir() . '/thematiq-policy-' . getmypid();
		@mkdir($probe, 0777, true);

		$service = $this->createMock(originalClassName: TokenSetService::class);
		$service->method('getAvailableTokenSets')->willReturn(
			[$this->entry(id: 'amsterdam'), $this->entry(id: 'utrecht')]
		);

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => (match ($key === 'token_set') {
				true => 'utrecht', default => $default
			})
		);

		// No token-sets.json at all.
		$this->assertSame(
			expected: ['utrecht'],
			actual: $this->ids(entries: $this->policy()->selectable($service, $config, $probe))
		);

		// Present but not a JSON list.
		file_put_contents($probe . '/token-sets.json', '{"id":"amsterdam"}');
		$this->assertSame(
			expected: ['utrecht'],
			actual: $this->ids(entries: $this->policy()->selectable($service, $config, $probe))
		);

		// Present, a list, with one usable entry and two unusable ones.
		file_put_contents(
			$probe . '/token-sets.json',
			json_encode([['id' => 'amsterdam'], ['name' => 'no id'], 'not an entry', ['id' => 42]])
		);
		$this->assertSame(
			expected: ['amsterdam', 'utrecht'],
			actual: $this->ids(entries: $this->policy()->selectable($service, $config, $probe))
		);

		unlink($probe . '/token-sets.json');
		rmdir($probe);
	}//end testAnUnusableManifestOffersOnlyTheSurvivors()

	/**
	 * An appconfig key that comes back null is treated as unset rather than
	 * throwing: `IConfig::getAppValue()` declares a string return, but a key
	 * never written reaches this code as null on some paths, and null would make
	 * the mapping parse throw.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testNullAppconfigValuesDoNotThrow(): void {
		$probe = sys_get_temp_dir() . '/thematiq-policy-null-' . getmypid();
		@mkdir($probe, 0777, true);
		file_put_contents($probe . '/token-sets.json', json_encode([['id' => 'amsterdam']]));

		$service = $this->createMock(originalClassName: TokenSetService::class);
		$service->method('getAvailableTokenSets')->willReturn([$this->entry(id: 'amsterdam')]);

		// A bare mock answers null for every key.
		$config = $this->createMock(originalClassName: IConfig::class);

		$this->assertSame(
			expected: ['amsterdam'],
			actual: $this->ids(entries: $this->policy()->selectable($service, $config, $probe))
		);

		unlink($probe . '/token-sets.json');
		rmdir($probe);
	}//end testNullAppconfigValuesDoNotThrow()

	/**
	 * The filter preserves the catalogue's order and the entries themselves: it
	 * filters, it does not re-shape or re-sort, because the dropdown and the
	 * apply dialog read the same fields off both lists.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testTheFilterPreservesOrderAndEntries(): void {
		$entries = [
			$this->entry(id: 'zebra'),
			$this->entry(id: 'dropped', warnings: [['kind' => 'incomplete']]),
			$this->entry(id: 'alpha'),
		];

		$selectable = $this->policy()->filter($entries, ['zebra', 'dropped', 'alpha'], '', []);

		$this->assertSame(expected: ['zebra', 'alpha'], actual: $this->ids(entries: $selectable));
		$this->assertSame(expected: $entries[0], actual: $selectable[0]);
		$this->assertSame(expected: $entries[2], actual: $selectable[1]);
	}//end testTheFilterPreservesOrderAndEntries()
}//end class
