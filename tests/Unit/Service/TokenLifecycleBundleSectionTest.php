<?php

/**
 * Unit tests for TokenLifecycleBundleSection.
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
 * @spec openspec/specs/own-tokens/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenLifecycleBundleSection;
use PHPUnit\Framework\TestCase;

/**
 * Golden of what the bundle's own-token and deprecation sections validate to and what applying them does.
 */
class TokenLifecycleBundleSectionTest extends TestCase {

	/**
	 * The calls made, in order.
	 *
	 * @var array<int, string>
	 */
	private array $calls = [];

	/**
	 * The section over recording stand-ins.
	 *
	 * @param bool $withOverrides Whether an overrides service is given.
	 *
	 * @return TokenLifecycleBundleSection
	 */
	private function section(bool $withOverrides = true): TokenLifecycleBundleSection {
		$own = $this->createMock(originalClassName: OwnTokenService::class);
		$own->method('checkAll')->willReturnCallback(
			function (array $tokens): array {
				$this->calls[] = 'own check ' . count($tokens);
				if (isset($tokens['bad']) === true) {
					throw new InvalidArgumentException('slug', 400);
				}

				return ['own' => $tokens];
			}
		);
		$own->method('replaceAll')->willReturnCallback(
			function (array $tokens): int {
				$this->calls[] = 'own replace';

				return 0;
			}
		);
		$deprecations = $this->createMock(originalClassName: TokenDeprecationService::class);
		$deprecations->method('checkAll')->willReturnCallback(
			function (array $records): array {
				$this->calls[] = 'deprecations check ' . count($records);
				if (isset($records['bad']) === true) {
					throw new InvalidArgumentException('record', 400);
				}

				return ['dep' => $records];
			}
		);
		$deprecations->method('replaceAll')->willReturnCallback(
			function (array $records): int {
				$this->calls[] = 'deprecations replace';

				return 0;
			}
		);
		$overrides = null;
		if ($withOverrides === true) {
			$overrides = $this->createMock(originalClassName: CustomOverridesService::class);
			$overrides->method('rewriteAll')->willReturnCallback(
				function (): array {
					$this->calls[] = 'rewrite';

					return [];
				}
			);
		}

		return new TokenLifecycleBundleSection(ownTokens: $own, deprecations: $deprecations, overrides: $overrides);
	}//end section()

	/**
	 * Each bundle validates to these values and errors, asking the checks in order.
	 *
	 * @return void
	 */
	public function testValidateGolden(): void {
		$cases = [
			[[], [['ownTokens' => null, 'tokenDeprecations' => null], [], []]],
			[['ownTokens' => ['a' => 1], 'tokenDeprecations' => ['b' => 2]], [['ownTokens' => ['own' => ['a' => 1]], 'tokenDeprecations' => ['dep' => ['b' => 2]]], [], ['own check 1', 'deprecations check 1']]],
			[['ownTokens' => 'x', 'tokenDeprecations' => ['bad' => 1]], [['ownTokens' => null, 'tokenDeprecations' => null], ['"ownTokens" holds an entry that fails its checks (shape).', '"tokenDeprecations" holds an entry that fails its checks (record).'], ['deprecations check 1']]],
			[['ownTokens' => ['bad' => 1], 'tokenDeprecations' => null], [['ownTokens' => null, 'tokenDeprecations' => null], ['"ownTokens" holds an entry that fails its checks (slug).', '"tokenDeprecations" holds an entry that fails its checks (shape).'], ['own check 1']]],
		];
		foreach ($cases as [$bundle, $expected]) {
			$this->calls = [];
			$errors = [];
			$resolved = $this->section()->validate(bundle: $bundle, errors: $errors);
			$this->assertSame(expected: $expected, actual: [$resolved, array_column($errors, 'message'), $this->calls], message: (string)json_encode($bundle));
			$this->assertSame(expected: array_column($errors, 'section'), actual: array_values(array_filter(['ownTokens', 'tokenDeprecations'], static fn (string $k): bool => in_array($k, array_column($errors, 'section'), true))));
		}
	}//end testValidateGolden()

	/**
	 * What applying each resolved section does, in order, and what it answers.
	 *
	 * @return void
	 */
	public function testApplyGolden(): void {
		$cases = [
			[null, true, [false, []]],
			[['ownTokens' => null, 'tokenDeprecations' => null], true, [false, []]],
			[['ownTokens' => ['a' => 1], 'tokenDeprecations' => null], true, [true, ['own replace', 'rewrite']]],
			[['ownTokens' => null, 'tokenDeprecations' => ['b' => 2]], true, [true, ['deprecations replace', 'rewrite']]],
			[['ownTokens' => [], 'tokenDeprecations' => []], true, [true, ['own replace', 'deprecations replace', 'rewrite']]],
			[['ownTokens' => ['a' => 1], 'tokenDeprecations' => null], false, [true, ['own replace']]],
		];
		foreach ($cases as [$resolved, $withOverrides, $expected]) {
			$this->calls = [];
			$applied = $this->section(withOverrides: $withOverrides)->apply(resolved: $resolved);
			$this->assertSame(expected: $expected, actual: [$applied, $this->calls], message: (string)json_encode($resolved));
		}
	}//end testApplyGolden()
}//end class
