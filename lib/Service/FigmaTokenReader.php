<?php

/**
 * Reads a file from a Figma tool into the shape the token set converter takes.
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
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Rewrites two kinds of upload, and leaves every other document byte for byte as it was.
 *
 * A Tokens Studio single file (it has `$themes` or `$metadata`) has its token sets merged
 * in `tokenSetOrder`, so a reference finds its token without the set's name in front. A
 * file whose tokens sit under `nldesign` came from thematiq, perhaps through Figma, which
 * drops the extension that carried each CSS name. The path is the name: `nldesign.color.primary`
 * is `--nldesign-color-primary`. Both kinds may use the `value` and `type` spelling.
 *
 * The file is hostile input. This class only moves values around; every value still
 * passes the mapper and the token set validator afterwards.
 *
 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
 */
class FigmaTokenReader {

	/**
	 * The thematiq extension key.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * The set id a rewritten upload carries, so the converter keeps every name as it is.
	 *
	 * @var string
	 */
	private const UPLOAD_ID = 'figma-upload';

	/**
	 * The W3C type of each Tokens Studio type that has one.
	 *
	 * @var array<string, string>
	 */
	private const TYPES = [
		'fontFamilies' => 'fontFamily',
		'fontWeights' => 'fontWeight',
		'borderRadius' => 'dimension',
		'borderWidth' => 'dimension',
		'spacing' => 'dimension',
		'sizing' => 'dimension',
		'fontSizes' => 'dimension',
	];

	/**
	 * How deep a document may nest.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 16;

	/**
	 * The upload as the converter takes it.
	 *
	 * @param string $content The uploaded content.
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why, appended to.
	 *
	 * @return string The rewritten document, or the content itself when it is neither kind.
	 *
	 * @spec openspec/changes/figma-token-files/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function normalise(string $content, array &$skipped): string {
		$trimmed = ltrim($content);
		if ($trimmed === '' || $trimmed[0] !== '{') {
			return $content;
		}

		$document = json_decode($trimmed, true);
		if (is_array($document) === false) {
			return $content;
		}

		$sets = new TokensStudioSets();
		$studio = $sets->isSingleFile(document: $document);
		if ($studio === true) {
			$document = $sets->merged(document: $document, skipped: $skipped);
		}

		$own = (is_array($document['nldesign'] ?? null) === true && is_string($document['$extensions'][self::EXTENSION]['setId'] ?? null) === false);
		if ($studio === false && $own === false) {
			return $content;
		}

		$document = $this->respelled(node: $document, depth: 0);
		if ($own === true) {
			$document = $this->named(document: $document, skipped: $skipped);
		}

		return (string)json_encode((object)$document, JSON_UNESCAPED_SLASHES);
	}//end normalise()

	/**
	 * The tree in the `$value` spelling, with W3C type names.
	 *
	 * @param array<string, mixed> $node A group or a token.
	 * @param int $depth The depth, guarded.
	 *
	 * @return array<string, mixed> The same node.
	 */
	private function respelled(array $node, int $depth): array {
		if ((new TokensStudioSets())->isToken(node: $node) === true) {
			if (array_key_exists('$value', $node) === false) {
				$respelled = ['$value' => $node['value'], '$type' => ($node['type'] ?? null), '$description' => ($node['description'] ?? null)];
				$node = ($respelled + array_diff_key($node, ['value' => 1, 'type' => 1, 'description' => 1]));
				$node = array_filter($node, static fn ($member): bool => $member !== null);
			}

			if (is_string($node['$type'] ?? null) === true && isset(self::TYPES[$node['$type']]) === true) {
				$node['$type'] = self::TYPES[$node['$type']];
			}

			return $node;
		}

		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false && $depth < self::MAX_DEPTH) {
				$node[$key] = $this->respelled(node: $child, depth: ($depth + 1));
			}
		}

		return $node;
	}//end respelled()

	/**
	 * Give every token of a thematiq file its CSS name back: the path joined by dashes.
	 *
	 * A colour stays a token and gets its name in the extension. Any other plain value is
	 * the CSS value itself and goes to the `cssOnly` map, a reference as `var()`. A token in
	 * a reference cycle, and a value that is a sum over references, are left out and reported.
	 *
	 * @param array<string, mixed> $document The document.
	 * @param array<int, array{path: string, reason: string}> $skipped Appended to.
	 *
	 * @return array<string, mixed> The document.
	 */
	private function named(array $document, array &$skipped): array {
		$leaves = [];
		$this->leaves(node: $document, path: [], leaves: $leaves, depth: 0);
		$cssOnly = (array)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []);

		foreach ($leaves as $dotted => $leaf) {
			$name = '--' . implode('-', $leaf['path']);
			if (preg_match('/^--[a-z][a-z0-9]*-[a-z0-9-]+$/', $name) !== 1) {
				continue;
			}

			$value = $leaf['token']['$value'];
			if ($this->cycles(start: $dotted, leaves: $leaves) === true) {
				$skipped[] = ['path' => $dotted, 'reason' => 'alias-cycle'];
				$this->set(tree: $document, path: $leaf['path'], token: null);
				continue;
			}

			if (is_scalar($value) === false || ($leaf['token']['$type'] ?? null) === 'color') {
				$leaf['token']['$extensions'][self::EXTENSION]['cssVariable'] ??= $name;
				$this->set(tree: $document, path: $leaf['path'], token: $leaf['token']);
				continue;
			}

			$css = $this->cssValue(value: $value, leaves: $leaves);
			$this->set(tree: $document, path: $leaf['path'], token: null);
			if ($css === null) {
				$skipped[] = ['path' => $dotted, 'reason' => 'value-not-a-css-value'];
				continue;
			}

			$cssOnly[$name] = $css;
		}

		$document['$extensions'][self::EXTENSION]['setId'] = self::UPLOAD_ID;
		$document['$extensions'][self::EXTENSION]['cssOnly'] = (object)$cssOnly;

		return $document;
	}//end named()

	/**
	 * A plain token value as CSS: itself, or `var()` for a reference to another token of the file.
	 *
	 * @param mixed $value The scalar value.
	 * @param array<string, mixed> $leaves Every token of the file by dotted path.
	 *
	 * @return string|null The CSS value, or null for a boolean, an empty value, a sum over
	 *                     references or a reference to a token the file does not hold.
	 */
	private function cssValue(mixed $value, array $leaves): ?string {
		if (is_bool($value) === true || trim((string)$value) === '') {
			return null;
		}

		$value = trim((string)$value);
		if (preg_match('/^\{([a-z][a-z0-9]*(?:\.[a-z0-9-]+)+)\}$/', $value, $match) === 1 && isset($leaves[$match[1]]) === true) {
			return 'var(--' . str_replace('.', '-', $match[1]) . ')';
		}

		// A sum over references is not a CSS value. Everything else is judged by the token set
		// validator, which sees the file that would be stored: a data: image may hold markup.
		if (str_contains($value, '{') === true || str_contains($value, '}') === true) {
			return null;
		}

		return $value;
	}//end cssValue()

	/**
	 * Whether following a token's references leads back to a token already passed.
	 *
	 * @param string $start The dotted path of the token.
	 * @param array<string, array<string, mixed>> $leaves Every token by dotted path.
	 *
	 * @return bool True for a cycle.
	 */
	private function cycles(string $start, array $leaves): bool {
		$seen = [];
		$current = $start;
		while (isset($leaves[$current]) === true) {
			if (isset($seen[$current]) === true) {
				return true;
			}

			$seen[$current] = true;
			$value = $leaves[$current]['token']['$value'];
			if (is_string($value) === false || preg_match('/^\{([^{}]+)\}$/', trim($value), $match) !== 1) {
				return false;
			}

			$current = $match[1];
		}

		return false;
	}//end cycles()

	/**
	 * Every token under a group, by dotted path.
	 *
	 * @param array<string, mixed> $node The group.
	 * @param array<int, string> $path The group's path.
	 * @param array<string, array<string, mixed>> $leaves Filled in place: dotted path to `path` and `token`.
	 * @param int $depth The depth, guarded.
	 *
	 * @return void
	 */
	private function leaves(array $node, array $path, array &$leaves, int $depth): void {
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$here = [...$path, (string)$key];
			if (array_key_exists('$value', $child) === true) {
				$leaves[implode('.', $here)] = ['path' => $here, 'token' => $child];
				continue;
			}

			if ($depth < self::MAX_DEPTH) {
				$this->leaves(node: $child, path: $here, leaves: $leaves, depth: ($depth + 1));
			}
		}
	}//end leaves()

	/**
	 * Replace the token at a path, or remove it.
	 *
	 * @param array<string, mixed> $tree The document, changed in place.
	 * @param array<int, string> $path The path.
	 * @param array<string, mixed>|null $token The token, or null to remove it.
	 *
	 * @return void
	 */
	private function set(array &$tree, array $path, ?array $token): void {
		$node = &$tree;
		$last = array_pop($path);
		foreach ($path as $segment) {
			$node = &$node[$segment];
		}

		if ($token === null) {
			unset($node[$last]);

			return;
		}

		$node[$last] = $token;
	}//end set()
}//end class
