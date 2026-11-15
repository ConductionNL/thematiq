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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
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
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-figma-becomes-a-custom-token-set
	 */
	public function normalise(string $content, array &$skipped): string {
		$document = $this->jsonObject(content: $content);
		if ($document === null) {
			return $content;
		}

		$read = $this->studioRead(document: $document, skipped: $skipped);
		$document = $read['document'];
		$dark = $read['dark'];
		$darkValues = new FileDarkValues();
		$own = $this->isOwnFile(document: $document);
		if ($read['studio'] === false && $own === false) {
			return $darkValues->withDark(content: $content, document: $document, dark: $dark);
		}

		$document = $this->respelled(node: $document, depth: 0);
		if ($own === true) {
			$document = $this->named(document: $document, skipped: $skipped);
		}

		return $darkValues->withDark(content: (string)json_encode((object)$document, JSON_UNESCAPED_SLASHES), document: $document, dark: $dark);
	}//end normalise()

	/**
	 * The content as a JSON object, or null when it does not start like one or does not decode to one.
	 *
	 * @param string $content The uploaded content.
	 *
	 * @return array<string, mixed>|null The decoded document.
	 */
	private function jsonObject(string $content): ?array {
		$trimmed = ltrim($content);
		if ($trimmed === '' || $trimmed[0] !== '{') {
			return null;
		}

		$document = json_decode($trimmed, true);
		if (is_array($document) === false) {
			return null;
		}

		return $document;
	}//end jsonObject()

	/**
	 * The document with a single-file Tokens Studio export merged into one tree, and the dark
	 * values the file carries: the plugin's own, and for a Tokens Studio file its dark sets.
	 *
	 * @param array<string, mixed> $document The decoded document.
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why, appended to.
	 *
	 * @return array<string, mixed> The document, the dark values, and whether it was a Tokens Studio file.
	 */
	private function studioRead(array $document, array &$skipped): array {
		$sets = new TokensStudioSets();
		$studio = $sets->isSingleFile(document: $document);
		$darkValues = new FileDarkValues();
		$dark = $darkValues->pluginDark(document: $document);
		if ($studio === true) {
			$dark += $darkValues->fromTree(tree: $this->respelled(node: $sets->darkTree(document: $document), depth: 0));
			$document = $sets->merged(document: $document, skipped: $skipped);
		}

		return ['document' => $document, 'dark' => $dark, 'studio' => $studio];
	}//end studioRead()

	/**
	 * Whether a document is a thematiq export not yet named for upload: it has an `nldesign` group and no set id.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return bool True for such a file.
	 */
	private function isOwnFile(array $document): bool {
		return is_array($document['nldesign'] ?? null) === true && is_string($document['$extensions'][self::EXTENSION]['setId'] ?? null) === false;
	}//end isOwnFile()

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
			return $this->respelledToken(node: $node);
		}

		foreach ($node as $key => $child) {
			if ($this->isGroupChild(key: $key, child: $child) === true && $depth < self::MAX_DEPTH) {
				$node[$key] = $this->respelled(node: $child, depth: ($depth + 1));
			}
		}

		return $node;
	}//end respelled()

	/**
	 * A token in the `$value` spelling, its type a W3C type name.
	 *
	 * @param array<string, mixed> $node The token.
	 *
	 * @return array<string, mixed> The token.
	 */
	private function respelledToken(array $node): array {
		if (array_key_exists('$value', $node) === false) {
			$respelled = ['$value' => $node['value'], '$type' => ($node['type'] ?? null), '$description' => ($node['description'] ?? null)];
			$node = ($respelled + array_diff_key($node, ['value' => 1, 'type' => 1, 'description' => 1]));
			$node = array_filter($node, static fn ($member): bool => $member !== null);
		}

		if (is_string($node['$type'] ?? null) === true && isset(self::TYPES[$node['$type']]) === true) {
			$node['$type'] = self::TYPES[$node['$type']];
		}

		return $node;
	}//end respelledToken()

	/**
	 * Whether a member of a group is a child group or token, not metadata.
	 *
	 * @param int|string $key The member's key.
	 * @param mixed $child The member.
	 *
	 * @return bool True for a child to descend into.
	 */
	private function isGroupChild(int|string $key, mixed $child): bool {
		return is_array($child) === true && str_starts_with((string)$key, '$') === false;
	}//end isGroupChild()

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
		$leaves = (new DtcgTokenLeaves())->leavesOf(tree: $document);
		$cssOnly = (array)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []);

		foreach ($leaves as $dotted => $leaf) {
			$name = '--' . implode('-', $leaf['path']);
			if (preg_match('/^--[a-z][a-z0-9]*-[a-z0-9-]+$/', $name) !== 1) {
				continue;
			}

			$value = $leaf['token']['$value'];
			if ((new FigmaCssValue())->cycles(start: $dotted, leaves: $leaves) === true) {
				$skipped[] = ['path' => $dotted, 'reason' => 'alias-cycle'];
				$this->set(tree: $document, path: $leaf['path'], token: null);
				continue;
			}

			if (is_scalar($value) === false || ($leaf['token']['$type'] ?? null) === 'color') {
				$leaf['token']['$extensions'][self::EXTENSION]['cssVariable'] ??= $name;
				$this->set(tree: $document, path: $leaf['path'], token: $leaf['token']);
				continue;
			}

			$css = (new FigmaCssValue())->cssValue(value: $value, leaves: $leaves);
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
