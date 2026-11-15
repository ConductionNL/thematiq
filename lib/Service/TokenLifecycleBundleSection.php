<?php

/**
 * The own tokens and token deprecations in a configuration bundle.
 *
 * Export writes `ownTokens` and `tokenDeprecations`. Import validates both before
 * anything is written, and applies only the keys a bundle carries: a bundle
 * without them, such as one from an older release, leaves them as they are.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;

/**
 * Bundle section for own tokens and deprecations.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
 */
class TokenLifecycleBundleSection {

	/**
	 * Constructor.
	 *
	 * @param OwnTokenService $ownTokens The own tokens.
	 * @param TokenDeprecationService $deprecations The deprecations.
	 * @param CustomOverridesService|null $overrides Brought up to date after an apply.
	 */
	public function __construct(
		private readonly OwnTokenService $ownTokens,
		private readonly TokenDeprecationService $deprecations,
		private readonly ?CustomOverridesService $overrides = null,
	) {
	}//end __construct()

	/**
	 * The two bundle keys.
	 *
	 * @return array{ownTokens: array<string, mixed>, tokenDeprecations: array<string, mixed>}
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function export(): array {
		$deprecations = [];
		foreach ($this->deprecations->list() as $token => $record) {
			unset($record['due']);
			$deprecations[$token] = $record;
		}

		return ['ownTokens' => $this->ownTokens->list(), 'tokenDeprecations' => $deprecations];
	}//end export()

	/**
	 * Validate the keys a bundle carries.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array{ownTokens: array<string, mixed>|null, tokenDeprecations: array<string, mixed>|null} Null for a key the bundle lacks.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function validate(array $bundle, array &$errors): array {
		$resolved = ['ownTokens' => null, 'tokenDeprecations' => null];
		foreach (array_keys($resolved) as $key) {
			if (array_key_exists($key, $bundle) === false) {
				continue;
			}

			try {
				$resolved[$key] = $this->checked(key: $key, value: $bundle[$key]);
			} catch (InvalidArgumentException $e) {
				$errors[] = ['section' => $key, 'message' => '"' . $key . '" holds an entry that fails its checks (' . $e->getMessage() . ').'];
			}
		}

		return $resolved;
	}//end validate()

	/**
	 * One section checked by its own service.
	 *
	 * @param string $key `ownTokens` or `tokenDeprecations`.
	 * @param mixed $value The section.
	 *
	 * @return array<string, mixed> The checked section.
	 *
	 * @throws InvalidArgumentException When the section is not an object or holds an entry that fails.
	 */
	private function checked(string $key, mixed $value): array {
		if (is_array($value) === false) {
			throw new InvalidArgumentException('shape', 400);
		}

		if ($key === 'ownTokens') {
			return $this->ownTokens->checkAll(tokens: $value);
		}

		return $this->deprecations->checkAll(records: $value);
	}//end checked()

	/**
	 * Apply the validated keys.
	 *
	 * @param array{ownTokens: array<string, mixed>|null, tokenDeprecations: array<string, mixed>|null}|null $resolved From validate().
	 *
	 * @return bool Whether anything was applied.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function apply(?array $resolved): bool {
		if ($resolved === null) {
			return false;
		}

		$this->replaceSections(resolved: $resolved);
		$applied = $resolved['ownTokens'] !== null || $resolved['tokenDeprecations'] !== null;
		if ($applied === true) {
			// Every set's overrides file carries the own tokens and their notices.
			$this->overrides?->rewriteAll();
		}

		return $applied;
	}//end apply()

	/**
	 * Replace what each validated section carries: the own tokens, then the deprecations.
	 *
	 * @param array{ownTokens: array<string, mixed>|null, tokenDeprecations: array<string, mixed>|null} $resolved The validated sections.
	 *
	 * @return void
	 */
	private function replaceSections(array $resolved): void {
		if ($resolved['ownTokens'] !== null) {
			$this->ownTokens->replaceAll(tokens: $resolved['ownTokens']);
		}

		if ($resolved['tokenDeprecations'] !== null) {
			$this->deprecations->replaceAll(records: $resolved['tokenDeprecations']);
		}
	}//end replaceSections()

	/**
	 * The import summary for the two keys.
	 *
	 * @param array{ownTokens: array<string, mixed>|null, tokenDeprecations: array<string, mixed>|null}|null $resolved From validate().
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function summary(?array $resolved): array {
		if ($resolved === null) {
			return [];
		}

		return [
			'ownTokens' => ['applied' => $resolved['ownTokens'] !== null, 'count' => count(($resolved['ownTokens'] ?? []))],
			'tokenDeprecations' => ['applied' => $resolved['tokenDeprecations'] !== null, 'count' => count(($resolved['tokenDeprecations'] ?? []))],
		];
	}//end summary()
}//end class
