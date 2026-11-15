<?php

/**
 * Writes a token set as a file a Figma tool reads.
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
 * Turns the W3C Design Tokens document of a set into one of the Figma side formats.
 *
 * Three formats besides the document itself. `figma` is the same document with every
 * colour as a hex or `rgba()` string, which is what variable import tools read.
 * `tokens-studio` is a Tokens Studio single file: the set under its own key, with
 * `$themes` and `$metadata`, every value a string. `tokens-studio-legacy` is that file
 * in the `value` and `type` spelling. A token keeps its path in all of them.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
 */
class FigmaTokenWriter {

	/**
	 * The formats a set can be downloaded in.
	 *
	 * @var array<int, string>
	 */
	public const FORMATS = ['dtcg', 'figma', 'tokens-studio', 'tokens-studio-legacy'];

	/**
	 * The thematiq extension key.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * The Tokens Studio type of each W3C type. Any other type is written as `other`.
	 *
	 * @var array<string, string>
	 */
	private const STUDIO_TYPES = [
		'color' => 'color',
		'dimension' => 'dimension',
		'fontFamily' => 'fontFamilies',
		'fontWeight' => 'fontWeights',
		'number' => 'number',
	];

	/**
	 * The document in the asked format.
	 *
	 * @param array<string, mixed> $document The W3C document of the set, as DesignTokensWriter wrote it.
	 * @param array<string, string> $declarations The set's own declarations, name to CSS value.
	 * @param string $format One of FORMATS.
	 * @param string $setId The set id, which is the token set key in a Tokens Studio file.
	 * @param string $setName The set's display name.
	 *
	 * @return array<string, mixed> The document to encode as JSON.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function write(array $document, array $declarations, string $format, string $setId, string $setName): array {
		$download = new TokenSetDownload(setId: $setId, setName: $setName, declarations: $declarations, format: $format);

		return $this->inFormat(document: $document, download: $download);
	}//end write()

	/**
	 * The W3C document of a download in the format it asks for.
	 *
	 * @param array<string, mixed> $document The W3C document of the set, as DesignTokensWriter wrote it.
	 * @param TokenSetDownload $download The set, its declarations and the format.
	 *
	 * @return array<string, mixed> The document to encode as JSON.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function inFormat(array $document, TokenSetDownload $download): array {
		$format = $download->format;
		if ($format !== 'figma' && $format !== 'tokens-studio' && $format !== 'tokens-studio-legacy') {
			return $document;
		}

		$document = (array)json_decode((string)json_encode($document), true);
		if ($format === 'figma') {
			return $this->figmaDocument(document: $document);
		}

		return $this->studioDocument(document: $document, download: $download);
	}//end inFormat()

	/**
	 * The W3C document as Figma's own import reads it: colours as strings, every group an object.
	 *
	 * @param array<string, mixed> $document The W3C document, decoded to arrays.
	 *
	 * @return array<string, mixed> The document.
	 */
	private function figmaDocument(array $document): array {
		$document = (new FigmaColourStrings())->withColourStrings(node: $document);
		$document['$extensions'][self::EXTENSION]['cssOnly'] = (object)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []);

		return (array)$this->grouped(node: $document, valueKey: '$value');
	}//end figmaDocument()

	/**
	 * The W3C document as a Tokens Studio file: one token set, one theme that enables it.
	 *
	 * @param array<string, mixed> $document The W3C document, decoded to arrays.
	 * @param TokenSetDownload $download The set, its declarations and the format.
	 *
	 * @return array<string, mixed> The file.
	 */
	private function studioDocument(array $document, TokenSetDownload $download): array {
		$run = new StudioWriteRun(declarations: $download->declarations, valueKey: '$value', typeKey: '$type');
		if ($download->format === 'tokens-studio-legacy') {
			$run = new StudioWriteRun(declarations: $download->declarations, valueKey: 'value', typeKey: 'type');
		}

		$this->placeTypedTokens(run: $run, document: $document);
		$this->placeCssOnly(run: $run, document: $document);

		$setId = $download->setId;

		return [
			$setId => $this->grouped(node: $run->tree, valueKey: $run->valueKey),
			'$themes' => [['id' => $setId, 'name' => $download->setName, 'selectedTokenSets' => (object)[$setId => 'enabled']]],
			'$metadata' => ['tokenSetOrder' => [$setId]],
		];
	}//end studioDocument()

	/**
	 * Every token of the W3C document as a Tokens Studio token, at the same path.
	 *
	 * @param StudioWriteRun $run The run; its tree is filled.
	 * @param array<string, mixed> $document The W3C document, decoded to arrays.
	 *
	 * @return void
	 */
	private function placeTypedTokens(StudioWriteRun $run, array $document): void {
		foreach ($this->w3cTokensOf(node: $document) as [$path, $w3cToken]) {
			$value = $this->studioValue(token: $w3cToken, declarations: $run->declarations);
			if ($value !== null) {
				$type = (self::STUDIO_TYPES[(string)($w3cToken['$type'] ?? '')] ?? 'other');
				$this->place(run: $run, token: new StudioToken(path: $path, token: [$run->valueKey => $value, $run->typeKey => $type]));
			}
		}
	}//end placeTypedTokens()

	/**
	 * Every value of the `cssOnly` map as a Tokens Studio token of type `other`, at the path its name gives.
	 *
	 * @param StudioWriteRun $run The run; its tree is filled.
	 * @param array<string, mixed> $document The W3C document, decoded to arrays.
	 *
	 * @return void
	 */
	private function placeCssOnly(StudioWriteRun $run, array $document): void {
		foreach ((array)($document['$extensions'][self::EXTENSION]['cssOnly'] ?? []) as $name => $value) {
			$path = array_values(array_filter(explode('-', ltrim((string)$name, '-'), 3), static fn (string $part): bool => $part !== ''));
			$this->place(run: $run, token: new StudioToken(path: $path, token: [$run->valueKey => (string)$value, $run->typeKey => 'other']));
		}
	}//end placeCssOnly()

	/**
	 * The file name of a download.
	 *
	 * @param string $setId The set id.
	 * @param string $format One of FORMATS.
	 *
	 * @return string The file name.
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function fileName(string $setId, string $format): string {
		if ($format === 'tokens-studio' || $format === 'tokens-studio-legacy') {
			return $setId . '.tokens-studio.json';
		}

		return $setId . '.tokens.json';
	}//end fileName()

	/**
	 * Every token of a W3C group with its path, depth first in document order.
	 *
	 * @param array<string, mixed> $node A group of the W3C document.
	 * @param array<int, string> $path The path of the group.
	 *
	 * @return array<int, array{0: array<int, string>, 1: array<string, mixed>}> Path and token, per token.
	 */
	private function w3cTokensOf(array $node, array $path = []): array {
		$found = [];
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			$here = [...$path, (string)$key];
			if (array_key_exists('$value', $child) === false) {
				$found = [...$found, ...$this->w3cTokensOf(node: $child, path: $here)];
				continue;
			}

			$found[] = [$here, $child];
		}

		return $found;
	}//end w3cTokensOf()

	/**
	 * The string a Tokens Studio token holds: the reference, the colour, or the CSS value as declared.
	 *
	 * @param array<string, mixed> $token The W3C token.
	 * @param array<string, string> $declarations The set's declarations.
	 *
	 * @return string|null The value, or null when the token has none to write.
	 */
	private function studioValue(array $token, array $declarations): ?string {
		return ($this->referenceOf(token: $token)
			?? $this->colourOf(token: $token)
			?? $this->declaredValueOf(token: $token, declarations: $declarations));
	}//end studioValue()

	/**
	 * A token's value when it is a reference, `{group.token}`.
	 *
	 * @param array<string, mixed> $token The W3C token.
	 *
	 * @return string|null The reference, or null when the value is not one.
	 */
	private function referenceOf(array $token): ?string {
		$value = $token['$value'];
		if (is_string($value) === true && preg_match('/^\{[^{}]+\}$/', $value) === 1) {
			return $value;
		}

		return null;
	}//end referenceOf()

	/**
	 * A colour token's value as a string.
	 *
	 * @param array<string, mixed> $token The W3C token.
	 *
	 * @return string|null The colour, or null when the token is no colour object with a hex.
	 */
	private function colourOf(array $token): ?string {
		if (is_array($token['$value']) === false || ($token['$type'] ?? null) !== 'color') {
			return null;
		}

		return (new FigmaColourStrings())->colourString(value: $token['$value']);
	}//end colourOf()

	/**
	 * The CSS value the set declares for a token's custom property, as declared.
	 *
	 * @param array<string, mixed> $token The W3C token.
	 * @param array<string, string> $declarations The set's declarations.
	 *
	 * @return string|null The value, or null when the set declares none.
	 */
	private function declaredValueOf(array $token, array $declarations): ?string {
		$name = ($token['$extensions'][self::EXTENSION]['cssVariable'] ?? null);
		if (is_string($name) === true && isset($declarations[$name]) === true) {
			return trim((string)$declarations[$name]);
		}

		return null;
	}//end declaredValueOf()

	/**
	 * Put a token at its path, unless a token or a group is already in the way.
	 *
	 * @param StudioWriteRun $run The run; its tree is changed.
	 * @param StudioToken $token The token and its path.
	 *
	 * @return void
	 */
	private function place(StudioWriteRun $run, StudioToken $token): void {
		$path = $token->path;
		if ($path === []) {
			return;
		}

		$tree = $run->tree;
		$this->placeIn(tree: $tree, token: $token);
		$run->tree = $tree;
	}//end place()

	/**
	 * Put a token at its path in a tree, unless a token is in the way; groups on the way are created.
	 *
	 * @param array<string, mixed> $tree The tree, changed in place.
	 * @param StudioToken $token The token and its path; the value key is the token's first key.
	 *
	 * @return void
	 */
	private function placeIn(array &$tree, StudioToken $token): void {
		$path = $token->path;
		$valueKey = (string)array_key_first($token->token);
		$node = &$tree;
		$last = array_pop($path);
		foreach ($path as $segment) {
			$node[$segment] = ($node[$segment] ?? []);
			if (array_key_exists($valueKey, $node[$segment]) === true) {
				return;
			}

			$node = &$node[$segment];
		}

		$node[$last] ??= $token->token;
	}//end placeIn()

	/**
	 * Every group as an object, so a group with the keys 0, 1, 2 still encodes as a JSON object.
	 *
	 * @param array<string, mixed> $node A group.
	 * @param string $valueKey The key that marks a token.
	 *
	 * @return object The group.
	 */
	private function grouped(array $node, string $valueKey): object {
		foreach ($node as $key => $child) {
			if (is_array($child) === true && str_starts_with((string)$key, '$') === false && array_key_exists($valueKey, $child) === false) {
				$node[$key] = $this->grouped(node: $child, valueKey: $valueKey);
			}
		}

		return (object)$node;
	}//end grouped()
}//end class
