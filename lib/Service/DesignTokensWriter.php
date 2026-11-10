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
	 * The fields of a deprecation record a `$deprecated` note names, in order, with the text around each.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const DEPRECATION_NOTE_PARTS = [
		'replacement' => ['Use ', ' instead'],
		'removalDate' => ['removal ', ''],
		'message' => ['', ''],
	];

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
	 * @param array<string, string> $declarations Custom property => value, as the set declares them.
	 * @param string $setId The set id.
	 * @param string $setName The set name, the root `$description`.
	 * @param string $appVersion The app version, in the root extension.
	 * @param array<string, array<string, mixed>> $deprecations Token name => deprecation record, written as `$deprecated`.
	 *
	 * @return array<string, mixed> The DTCG document.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function write(array $declarations, string $setId, string $setName, string $appVersion, array $deprecations = []): array {
		$download = new TokenSetDownload(
			setId: $setId,
			setName: $setName,
			declarations: $declarations,
			appVersion: $appVersion,
			deprecations: $deprecations
		);

		return $this->document(download: $download);
	}//end write()

	/**
	 * The DTCG document of a download.
	 *
	 * @param TokenSetDownload $download The set, its declarations, the app version and the deprecation records.
	 *
	 * @return array<string, mixed> The DTCG document.
	 *
	 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-each-value-gets-a-dtcg-type-by-its-shape
	 */
	public function document(TokenSetDownload $download): array {
		$run = new DtcgWriteRun(declarations: $download->declarations, deprecations: $download->deprecations);
		$this->typeDeclarations(run: $run);
		$this->assignPaths(run: $run);
		$this->resolveAliases(run: $run);

		$run->document = ['$description' => $download->setName];
		foreach (array_keys($run->typed) as $name) {
			$this->place(run: $run, name: (string)$name);
		}

		$run->document['$extensions'] = [
			self::EXTENSION => ['setId' => $download->setId, 'appVersion' => $download->appVersion, 'cssOnly' => (object)$run->cssOnly],
		];

		return $this->groupsAsObjects(node: $run->document);
	}//end document()

	/**
	 * Type each declaration by its shape; a value DTCG has no type for goes to cssOnly.
	 *
	 * @param DtcgWriteRun $run The run; its typed and cssOnly maps are filled.
	 *
	 * @return void
	 */
	private function typeDeclarations(DtcgWriteRun $run): void {
		foreach ($run->declarations as $name => $value) {
			$token = $this->typer->typed(name: (string)$name, value: trim((string)$value));
			if ($token === null) {
				$run->cssOnly[(string)$name] = trim((string)$value);
				continue;
			}

			$run->typed[(string)$name] = $token;
		}
	}//end typeDeclarations()

	/**
	 * A typed token as the document holds it: with its CSS name, and the text a colour was written in.
	 *
	 * @param DtcgWriteRun $run The run.
	 * @param string $name The token's custom property.
	 *
	 * @return array<string, mixed> The token.
	 */
	private function documentToken(DtcgWriteRun $run, string $name): array {
		$token = $run->typed[$name];
		$token['$extensions'] = [self::EXTENSION => ['cssVariable' => $name]];
		// A colour keeps the text it was written in when that is not its hex, so an upload
		// gives back `rgb(…)` or `hsl(…)` as it was, not only the same colour.
		$text = trim((string)$run->declarations[$name]);
		$hex = strtolower((string)($token['$value']['hex'] ?? ''));
		if (($token['$type'] ?? null) === 'color' && is_array($token['$value']) === true && strtolower($text) !== $hex) {
			$token['$extensions'][self::EXTENSION]['css'] = $text;
		}

		return $this->withDeprecation(token: $token, record: ($run->deprecations[$name] ?? null));
	}//end documentToken()

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
	 * @param DtcgWriteRun $run The run; its paths are set, its cssOnly map extended and its typed map narrowed to the tokens with a path.
	 *
	 * @return void
	 */
	private function assignPaths(DtcgWriteRun $run): void {
		$joined = [];
		foreach (array_keys($run->typed) as $name) {
			$run->paths[$name] = $this->pathOf(name: $name);
			$joined[$name] = implode('.', $run->paths[$name]);
		}

		foreach ($joined as $name => $path) {
			if ($this->holdsAPathBelow(joined: $joined, path: $path) === true) {
				$run->cssOnly[$name] = trim((string)$run->declarations[$name]);
				unset($run->paths[$name]);
			}
		}

		$run->typed = array_intersect_key($run->typed, $run->paths);
	}//end assignPaths()

	/**
	 * Whether any of the joined paths sits below a path.
	 *
	 * @param array<string, string> $joined Name => path joined with dots.
	 * @param string $path The joined path.
	 *
	 * @return bool True when another token's path starts inside it.
	 */
	private function holdsAPathBelow(array $joined, string $path): bool {
		foreach ($joined as $other) {
			if (str_starts_with($other, $path . '.') === true) {
				return true;
			}
		}

		return false;
	}//end holdsAPathBelow()

	/**
	 * Turn each `var()` into a DTCG alias to its target's path, with the target's type. A
	 * `var()` whose target is not a token of this document goes to cssOnly.
	 *
	 * @param DtcgWriteRun $run The run; its typed map has the aliases resolved, its cssOnly map is extended.
	 *
	 * @return void
	 */
	private function resolveAliases(DtcgWriteRun $run): void {
		foreach ($run->typed as $name => $token) {
			if (isset($token['$alias']) === false) {
				continue;
			}

			$type = $this->terminalType(name: $token['$alias'], typed: $run->typed);
			if ($type === null || isset($run->paths[$token['$alias']]) === false) {
				$run->cssOnly[$name] = trim((string)$run->declarations[$name]);
				unset($run->typed[$name]);
				continue;
			}

			$run->typed[$name] = ['$type' => $type, '$value' => '{' . implode('.', $run->paths[$token['$alias']]) . '}'];
		}
	}//end resolveAliases()

	/**
	 * The type at the end of an alias chain, or null when the chain leaves the document or loops.
	 *
	 * @param string $name The alias target.
	 * @param array<string, array<string, mixed>> $typed Name => token.
	 *
	 * @return string|null
	 */
	private function terminalType(string $name, array $typed): ?string {
		$seen = [];
		while (isset($typed[$name]['$alias']) === true && isset($seen[$name]) === false) {
			$seen[$name] = true;
			$name = $typed[$name]['$alias'];
		}

		return ($typed[$name]['$type'] ?? null);
	}//end terminalType()

	/**
	 * Add `$deprecated` and the extension fields of a deprecation.
	 *
	 * @param array<string, mixed> $token The token.
	 * @param array<string, mixed>|null $record The deprecation, or null.
	 *
	 * @return array<string, mixed>
	 */
	private function withDeprecation(array $token, ?array $record): array {
		if ($record === null || ($record['state'] ?? 'active') === 'removed') {
			return $token;
		}

		$token['$deprecated'] = $this->deprecationNote(record: $record);
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
	 * The `$deprecated` value of a deprecation: what it says, or true when it says nothing.
	 *
	 * @param array<string, mixed> $record The deprecation.
	 *
	 * @return bool|string The note.
	 */
	private function deprecationNote(array $record): bool|string {
		$parts = [];
		foreach (self::DEPRECATION_NOTE_PARTS as $field => [$before, $after]) {
			if (($record[$field] ?? '') !== '') {
				$parts[] = $before . $record[$field] . $after;
			}
		}

		if ($parts === []) {
			return true;
		}

		return implode('; ', $parts);
	}//end deprecationNote()

	/**
	 * Put a typed token at its path in the document.
	 *
	 * @param DtcgWriteRun $run The run; its document is changed.
	 * @param string $name The token's custom property.
	 *
	 * @return void
	 */
	private function place(DtcgWriteRun $run, string $name): void {
		$token = $this->documentToken(run: $run, name: $name);
		$path = $run->paths[$name];
		$last = (string)array_shift($path);
		$node = &$run->document;
		foreach ($path as $segment) {
			$node[$last] = (array)($node[$last] ?? []);
			$node = &$node[$last];
			$last = (string)$segment;
		}

		$node[$last] = $token;
	}//end place()
}//end class
