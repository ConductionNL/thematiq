<?php

/**
 * Thematiq DTCG writer.
 *
 * The mirror of {@see DesignTokensMapper}: turns the custom properties a token set
 * declares into a W3C Design Tokens (DTCG v2025.10) document. Each value is typed by
 * its shape; a value DTCG has no type for goes into the root
 * `$extensions["nl.conduction.thematiq"].cssOnly` map, which thematiq's import reads
 * back. Every token carries its CSS name in `$extensions["nl.conduction.thematiq"].cssVariable`,
 * so a thematiq round trip is exact.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Declarations to a DTCG document.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
 */
class DesignTokensWriter {

	/**
	 * The extension key, reverse domain as the DTCG format asks.
	 *
	 * @var string
	 */
	public const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * Constructor.
	 *
	 * @param DtcgValueTyper $typer Types each value by its shape.
	 */
	public function __construct(
		private readonly DtcgValueTyper $typer = new DtcgValueTyper(),
	) {
	}//end __construct()

	/**
	 * Write a document.
	 *
	 * @param array<string, string>               $declarations Custom property => value, as the set declares them.
	 * @param string                              $setId        The set id.
	 * @param string                              $setName      The set name, the root `$description`.
	 * @param string                              $appVersion   The app version, in the root extension.
	 * @param array<string, array<string, mixed>> $deprecations Token name => deprecation record, written as `$deprecated`.
	 *
	 * @return array<string, mixed> The DTCG document.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function write(array $declarations, string $setId, string $setName, string $appVersion, array $deprecations = []): array {
		$typed   = [];
		$cssOnly = [];
		foreach ($declarations as $name => $value) {
			$token = $this->typer->typed(name: (string)$name, value: trim((string)$value));
			if ($token === null) {
				$cssOnly[(string)$name] = trim((string)$value);
				continue;
			}

			$typed[(string)$name] = $token;
		}

		$paths = $this->paths(names: array_keys($typed), cssOnly: $cssOnly, declarations: $declarations);
		$typed = array_intersect_key($typed, $paths);
		$typed = $this->resolveAliases(typed: $typed, paths: $paths, cssOnly: $cssOnly, declarations: $declarations);

		$document = ['$description' => $setName];
		foreach ($typed as $name => $token) {
			$token['$extensions'] = [self::EXTENSION => ['cssVariable' => $name]];
			$token = $this->withDeprecation(token: $token, record: ($deprecations[$name] ?? null));
			$this->place(document: $document, path: $paths[$name], token: $token);
		}

		$document['$extensions'] = [
			self::EXTENSION => ['setId' => $setId, 'appVersion' => $appVersion, 'cssOnly' => (object)$cssOnly],
		];

		return $this->groupsAsObjects(node: $document);
	}//end write()

	/**
	 * Every group as an object, so a group whose keys happen to be 0, 1, 2 still encodes as
	 * a JSON object; a token's own members are left as they are.
	 *
	 * @param array<string, mixed> $node A group.
	 *
	 * @return array<string, mixed> The group, its child groups as objects.
	 */
	private function groupsAsObjects(array $node): array {
		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false && array_key_exists('$value', $child) === false) {
				$node[$key] = (object)$this->groupsAsObjects(node: $child);
			}
		}

		return $node;
	}//end groupsAsObjects()

	/**
	 * The DTCG path of a CSS name: its first segment, its second, then the rest joined by dashes.
	 *
	 * @param string $name The custom property.
	 *
	 * @return array<int, string> The path segments.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function pathOf(string $name): array {
		$parts = explode('-', ltrim($name, '-'), 3);

		return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
	}//end pathOf()


	/**
	 * The path of every typed token. A name whose path would sit inside another token's path
	 * (a group and a token at once) is written to cssOnly instead.
	 *
	 * @param array<int, string>    $names        The typed names.
	 * @param array<string, string> $cssOnly      The untyped map, extended in place.
	 * @param array<string, string> $declarations The declared values.
	 *
	 * @return array<string, array<int, string>> Name => path.
	 */
	private function paths(array $names, array &$cssOnly, array $declarations): array {
		$paths  = [];
		$joined = [];
		foreach ($names as $name) {
			$paths[$name]  = $this->pathOf(name: $name);
			$joined[$name] = implode('.', $paths[$name]);
		}

		foreach ($joined as $name => $path) {
			foreach ($joined as $other) {
				if (str_starts_with($other, $path . '.') === true) {
					$cssOnly[$name] = trim((string)$declarations[$name]);
					unset($paths[$name]);
					break;
				}
			}
		}

		return $paths;
	}//end paths()

	/**
	 * Turn each `var()` into a DTCG alias to its target's path, with the target's type. A
	 * `var()` whose target is not a token of this document goes to cssOnly.
	 *
	 * @param array<string, array<string, mixed>> $typed        Name => token.
	 * @param array<string, array<int, string>>   $paths        Name => path.
	 * @param array<string, string>               $cssOnly      The untyped map, extended in place.
	 * @param array<string, string>               $declarations The declared values.
	 *
	 * @return array<string, array<string, mixed>> The tokens, aliases resolved.
	 */
	private function resolveAliases(array $typed, array $paths, array &$cssOnly, array $declarations): array {
		foreach ($typed as $name => $token) {
			if (isset($token['$alias']) === false) {
				continue;
			}

			$type = $this->terminalType(name: $token['$alias'], typed: $typed);
			if ($type === null || isset($paths[$token['$alias']]) === false) {
				$cssOnly[$name] = trim((string)$declarations[$name]);
				unset($typed[$name]);
				continue;
			}

			$typed[$name] = ['$type' => $type, '$value' => '{' . implode('.', $paths[$token['$alias']]) . '}'];
		}

		return $typed;
	}//end resolveAliases()

	/**
	 * The type at the end of an alias chain, or null when the chain leaves the document or loops.
	 *
	 * @param string                              $name  The alias target.
	 * @param array<string, array<string, mixed>> $typed Name => token.
	 *
	 * @return string|null
	 */
	private function terminalType(string $name, array $typed): ?string {
		$seen = [];
		while (isset($typed[$name]['$alias']) === true && isset($seen[$name]) === false) {
			$seen[$name] = true;
			$name        = $typed[$name]['$alias'];
		}

		return ($typed[$name]['$type'] ?? null);
	}//end terminalType()

	/**
	 * Add `$deprecated` and the extension fields of a deprecation.
	 *
	 * @param array<string, mixed>      $token  The token.
	 * @param array<string, mixed>|null $record The deprecation, or null.
	 *
	 * @return array<string, mixed>
	 */
	private function withDeprecation(array $token, ?array $record): array {
		if ($record === null || ($record['state'] ?? 'active') === 'removed') {
			return $token;
		}

		$parts = [];
		if (($record['replacement'] ?? '') !== '') {
			$parts[] = 'Use ' . $record['replacement'] . ' instead';
		}

		if (($record['removalDate'] ?? '') !== '') {
			$parts[] = 'removal ' . $record['removalDate'];
		}

		if (($record['message'] ?? '') !== '') {
			$parts[] = $record['message'];
		}

		$token['$deprecated'] = true;
		if ($parts !== []) {
			$token['$deprecated'] = implode('; ', $parts);
		}
		$token['$extensions'][self::EXTENSION]['deprecation'] = array_filter(
			[
				'severity' => (string)($record['severity'] ?? 'warning'),
				'replacement' => ($record['replacement'] ?? null),
				'removalDate' => ($record['removalDate'] ?? null),
			],
			static fn ($v): bool => $v !== null && $v !== ''
		);

		return $token;
	}//end withDeprecation()

	/**
	 * Put a token at its path.
	 *
	 * @param array<string, mixed> $document The document, changed in place.
	 * @param array<int, string>   $path     The path.
	 * @param array<string, mixed> $token    The token.
	 *
	 * @return void
	 */
	private function place(array &$document, array $path, array $token): void {
		$document = $this->placed(node: $document, path: $path, token: $token);
	}//end place()

	/**
	 * A node with the token set at the path below it.
	 *
	 * @param array<string, mixed> $node  The node.
	 * @param array<int, string>   $path  The path below it.
	 * @param array<string, mixed> $token The token.
	 *
	 * @return array<string, mixed>
	 */
	private function placed(array $node, array $path, array $token): array {
		$segment = (string)array_shift($path);
		if ($path === []) {
			$node[$segment] = $token;
			return $node;
		}

		$child          = (array)($node[$segment] ?? []);
		$node[$segment] = $this->placed(node: $child, path: $path, token: $token);

		return $node;
	}//end placed()
}//end class
