<?php

/**
 * NL Design W3C Design Tokens Alias Resolver.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Resolves W3C DTCG `{token.path}` alias references transitively against a
 * flattened leaf table, with cycle, dangling-target and depth-bound guards.
 *
 * Extracted from {@see DesignTokensMapper} as its own single-responsibility
 * collaborator — alias resolution is a self-contained graph-walk concern with
 * no dependency on type resolution or value serialization.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class DesignTokensAliasResolver {

	/**
	 * Maximum number of alias hops resolved before giving up (`alias-depth-exceeded`).
	 *
	 * @var int
	 */
	private const MAX_ALIAS_DEPTH = 10;

	/**
	 * Whether a raw `$value` string is a DTCG alias reference (`{token.path}`).
	 *
	 * @param string $value The raw value.
	 *
	 * @return bool True when the value is an alias reference.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function isAlias(string $value): bool {
		return preg_match('/^\{(.+)\}$/', trim($value)) === 1;
	}//end isAlias()

	/**
	 * Resolve a token's alias chain transitively to its terminal concrete value.
	 *
	 * Detects cycles (a chain that revisits an already-visited path), dangling
	 * targets (a referenced path absent from the document), and chains longer
	 * than {@see self::MAX_ALIAS_DEPTH} hops.
	 *
	 * @param string $startPath The aliasing token's own dotted path.
	 * @param array<string, array<string, mixed>> $leaves The full leaf table.
	 *
	 * @return array{ok: bool, value?: mixed, type?: string|null, reason?: string, detail?: string}
	 *                                                                                              The resolution outcome.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function resolve(string $startPath, array $leaves): array {
		$walk = new AliasWalk(chain: [$startPath]);
		$current = $leaves[$startPath];

		while (is_string($current['value']) === true && $this->isAlias(value: $current['value']) === true) {
			preg_match('/^\{(.+)\}$/', trim($current['value']), $matches);
			$targetPath = trim($matches[1]);

			$walk = new AliasWalk(chain: $walk->chain, hops: ($walk->hops + 1));
			$failure = $this->hopFailure(targetPath: $targetPath, walk: $walk, leaves: $leaves);
			if ($failure !== null) {
				return $failure;
			}

			$walk = new AliasWalk(chain: [...$walk->chain, $targetPath], hops: $walk->hops);
			$current = $leaves[$targetPath];
		}//end while

		return [
			'ok' => true,
			'value' => $current['value'],
			'type' => $current['type'],
		];
	}//end resolve()

	/**
	 * Why the next hop of an alias chain fails: too deep, a cycle, or a missing target.
	 *
	 * @param string $targetPath The path the current alias points at.
	 * @param AliasWalk $walk The chain so far, this hop counted.
	 * @param array<string, array<string, mixed>> $leaves The full leaf table.
	 *
	 * @return array{ok: bool, reason: string, detail: string}|null The failure, or null when the hop may be taken.
	 */
	private function hopFailure(string $targetPath, AliasWalk $walk, array $leaves): ?array {
		if ($walk->hops > self::MAX_ALIAS_DEPTH) {
			return ['ok' => false, 'reason' => 'alias-depth-exceeded', 'detail' => $walk->describe()];
		}

		if ($walk->passed(path: $targetPath) === true) {
			return ['ok' => false, 'reason' => 'alias-cycle', 'detail' => $walk->describe(next: $targetPath)];
		}

		if (isset($leaves[$targetPath]) === false) {
			return ['ok' => false, 'reason' => 'alias-target-missing', 'detail' => $targetPath];
		}

		return null;
	}//end hopFailure()
}//end class
