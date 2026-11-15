<?php

/**
 * Reads the OpenProject plugin's set file into the shape the token set converter takes.
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
 * The plugin keeps one design system as `{id, name, tokens, logo, dark, vocabulary}`: the
 * eighteen roles (and `menu`) under `tokens`, and the rest of thematiq's vocabulary by name
 * without the `--nldesign-` prefix. This class writes each value under the CSS name thematiq
 * reads that role from, as a thematiq document whose every value sits in its `cssOnly` map,
 * so the converter keeps the names and fills in nothing.
 *
 * The file is hostile input. This class only moves values; each one still passes the mapper's
 * name check and the token set validator.
 *
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */
class OpenProjectSetFile {

	/**
	 * The CSS name each role is written under: the first name thematiq's role mapper reads it from.
	 *
	 * @var array<string, string>
	 */
	public const ROLE_TOKENS = [
		'primary' => '--nldesign-color-primary',
		'primary-deep' => '--nldesign-color-primary-hover',
		'primary-light' => '--nldesign-color-primary-light',
		'action' => '--nldesign-color-action',
		'action-deep' => '--nldesign-color-action-hover',
		'link' => '--nldesign-color-link',
		'accent' => '--nldesign-color-accent',
		'ink' => '--nldesign-color-text',
		'text' => '--thematiq-shell-text',
		'muted' => '--nldesign-color-text-muted',
		'line' => '--nldesign-color-border',
		'soft' => '--nldesign-color-background-dark',
		'ground' => '--nldesign-color-background',
		'chip' => '--nldesign-color-background-hover',
		'field' => '--nldesign-color-border-dark',
		'radius' => '--nldesign-border-radius',
		'radius-lg' => '--nldesign-border-radius-large',
		'font-family' => '--nldesign-font-family',
		'menu' => '--thematiq-shell-menu',
	];

	/**
	 * The keys of a set file.
	 *
	 * @var array<int, string>
	 */
	private const SET_KEYS = ['id', 'name', 'tokens', 'logo', 'dark', 'uploads', 'vocabulary'];

	/**
	 * The thematiq extension key.
	 *
	 * @var string
	 */
	private const EXTENSION = 'nl.conduction.thematiq';

	/**
	 * An upload as the converter takes it: a set file rewritten, any other content as it was.
	 *
	 * @param string $content The uploaded content.
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why, appended to.
	 *
	 * @return string The content.
	 *
	 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
	 */
	public function normalise(string $content, array &$skipped): string {
		$document = json_decode(trim($content), true);
		if (is_array($document) === false || $this->isSetFile(document: $document) === false) {
			return $content;
		}

		return (string)json_encode((object)$this->toDocument(document: $document, skipped: $skipped), JSON_UNESCAPED_SLASHES);
	}//end normalise()

	/**
	 * Whether a decoded document is a set file: an `id`, a `tokens` object, and no other keys.
	 *
	 * @param array<string, mixed> $document The document.
	 *
	 * @return bool True for a set file.
	 *
	 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
	 */
	public function isSetFile(array $document): bool {
		return is_string($document['id'] ?? null) === true
			&& is_array($document['tokens'] ?? null) === true
			&& array_diff(array_map('strval', array_keys($document)), self::SET_KEYS) === [];
	}//end isSetFile()

	/**
	 * The set file as a thematiq document.
	 *
	 * @param array<string, mixed> $document The set file.
	 * @param array<int, array{path: string, reason: string}> $skipped What was left out and why, appended to.
	 *
	 * @return array<string, mixed> The document.
	 *
	 * @spec openspec/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
	 */
	public function toDocument(array $document, array &$skipped): array {
		$run = new SetFileReadRun(skipped: $skipped);
		$light = new SetFileBlock(path: '', roles: (array)$document['tokens'], vocabulary: (array)($document['vocabulary'] ?? []));
		$cssOnly = $this->declarations(run: $run, block: $light);
		$dark = $this->darkDeclarations(run: $run, document: $document);
		$skipped = $run->skipped;

		$extension = ['setId' => 'openproject-set-file', 'cssOnly' => (object)$cssOnly];
		if ($dark !== []) {
			$extension['dark'] = (object)$dark;
		}

		$out = ['$extensions' => [self::EXTENSION => $extension]];
		if (is_string($document['name'] ?? null) === true) {
			$out['$description'] = $document['name'];
		}

		return $out;
	}//end toDocument()

	/**
	 * The dark block's declarations, or none when the file has no dark block.
	 *
	 * The dark block: the roles under `tokens` (or the roles on their own, as the plugin also
	 * reads them) and the dark vocabulary. They become the uploaded set's dark values.
	 *
	 * @param SetFileReadRun $run The read; what is left out is appended to it.
	 * @param array<string, mixed> $document The set file.
	 *
	 * @return array<string, string> Name => value.
	 */
	private function darkDeclarations(SetFileReadRun $run, array $document): array {
		if (is_array($document['dark'] ?? null) === false) {
			return [];
		}

		$roles = $document['dark'];
		if (is_array($roles['tokens'] ?? null) === true) {
			$roles = $roles['tokens'];
		}

		unset($roles['vocabulary']);
		$block = new SetFileBlock(path: 'dark.', roles: $roles, vocabulary: (array)($document['dark']['vocabulary'] ?? []));

		return $this->declarations(run: $run, block: $block);
	}//end darkDeclarations()

	/**
	 * The roles and vocabulary of one block as declarations, each under its CSS name.
	 *
	 * @param SetFileReadRun $run The read; what is left out is appended to it.
	 * @param SetFileBlock $block The block.
	 *
	 * @return array<string, string> Name => value.
	 */
	private function declarations(SetFileReadRun $run, SetFileBlock $block): array {
		$run->declarations = [];
		foreach ($block->roles as $role => $value) {
			$name = (self::ROLE_TOKENS[(string)$role] ?? null);
			$this->take(run: $run, entry: new SetFileEntry(name: $name, value: $value, path: $block->path . 'tokens.' . $role));
		}

		foreach ($block->vocabulary as $key => $value) {
			$entry = new SetFileEntry(name: $this->vocabularyName(key: (string)$key), value: $value, path: $block->path . 'vocabulary.' . $key);
			$this->takeVocabulary(run: $run, entry: $entry);
		}

		return $run->declarations;
	}//end declarations()

	/**
	 * The CSS name of a vocabulary key, or null for a key that is no token name.
	 *
	 * @param string $key The key without the prefix.
	 *
	 * @return string|null The name.
	 */
	private function vocabularyName(string $key): ?string {
		if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) === 1) {
			return ShellCatalogueVocabulary::PREFIX . $key;
		}

		return null;
	}//end vocabularyName()

	/**
	 * Keep a vocabulary value, unless a role of the block already took its name.
	 *
	 * A role's own name in the vocabulary would compete with the role; the role wins.
	 *
	 * @param SetFileReadRun $run The read.
	 * @param SetFileEntry $entry The vocabulary value.
	 *
	 * @return void
	 */
	private function takeVocabulary(SetFileReadRun $run, SetFileEntry $entry): void {
		if ($entry->name !== null && isset($run->declarations[$entry->name]) === true) {
			return;
		}

		$this->take(run: $run, entry: $entry);
	}//end takeVocabulary()

	/**
	 * Keep one value under its name, or report why not.
	 *
	 * @param SetFileReadRun $run The read; gets the value, or the reason it was left out.
	 * @param SetFileEntry $entry The value, its name and where it sits.
	 *
	 * @return void
	 */
	private function take(SetFileReadRun $run, SetFileEntry $entry): void {
		if ($entry->name === null) {
			$run->skipped[] = ['path' => $entry->path, 'reason' => 'unmapped'];

			return;
		}

		$text = '';
		if (is_string($entry->value) === true) {
			$text = trim($entry->value);
		}

		if ($text === '' || str_contains($text, '{') === true || str_contains($text, '}') === true) {
			$run->skipped[] = ['path' => $entry->path, 'reason' => 'value-not-a-css-value'];

			return;
		}

		$run->declarations[$entry->name] = $text;
	}//end take()
}//end class
