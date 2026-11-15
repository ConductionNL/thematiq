<?php

/**
 * The vocabulary of one set in the shell catalogue: its own tokens beyond the roles.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Picks a set's own vocabulary: every `--nldesign-*` token its own file declares, by name without the prefix.
 *
 * Only what the set declares itself, not the defaults under it: a product that reads the catalogue keeps its
 * own value for a token the set leaves out. The values are the resolved ones. Left out: the names the roles
 * come from ({@see self::ROLE_NAMES}), pictures, addresses and shapes ({@see self::NOT_A_VALUE_PATTERN}), and
 * values that are empty, still point at another variable or hold an address.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */
class ShellCatalogueVocabulary {

	/**
	 * The names the eighteen roles are written under in a Figma file and the OpenProject plugin.
	 *
	 * Their values are the roles already, so the vocabulary leaves them out: a value has one home.
	 *
	 * @var string[]
	 */
	public const ROLE_NAMES = [
		'--nldesign-color-primary', '--nldesign-color-primary-hover', '--nldesign-color-primary-light',
		'--nldesign-color-action', '--nldesign-color-action-hover', '--nldesign-color-link', '--nldesign-color-accent',
		'--nldesign-color-text', '--nldesign-color-text-muted', '--nldesign-color-border', '--nldesign-color-background-dark',
		'--nldesign-color-background', '--nldesign-color-background-hover', '--nldesign-color-border-dark',
		'--nldesign-border-radius', '--nldesign-border-radius-large', '--nldesign-font-family',
	];

	/**
	 * The prefix of thematiq's token names, left off the keys of the vocabulary.
	 *
	 * @var string
	 */
	public const PREFIX = '--nldesign-';

	/**
	 * Names whose value is a picture, an address or a shape: a logo, a gradient image, a clip path.
	 *
	 * Another product has these as files and drawings of its own, so the vocabulary leaves them out.
	 *
	 * @var string
	 */
	public const NOT_A_VALUE_PATTERN = '/-(url|image|image-inverse|clip-path)$/';

	/**
	 * The vocabulary of one set.
	 *
	 * @param string $css The set's own token file. Its comments are not read.
	 * @param array<string, string> $resolved The set's resolved declarations.
	 *
	 * @return array<string, string> Key => value, sorted by key.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
	 */
	public function ofSet(string $css, array $resolved): array {
		$css = (string)preg_replace('#/\\*.*?\\*/#s', '', $css);
		preg_match_all('/(--nldesign-[a-z0-9-]+)\\s*:/', $css, $matches);

		$vocabulary = [];
		foreach (array_unique($matches[1]) as $name) {
			$value = ($resolved[$name] ?? null);
			if ($this->isVocabulary(name: $name) === false || is_string($value) === false || $this->carriesMeaning(value: $value) === false) {
				continue;
			}

			$vocabulary[substr($name, strlen(self::PREFIX))] = trim($value);
		}

		ksort($vocabulary, SORT_STRING);

		return $vocabulary;
	}//end ofSet()

	/**
	 * The vocabulary of a block of declarations that are the set's own already, such as its dark variant's.
	 *
	 * @param array<string, string> $declarations Declaration name => value.
	 *
	 * @return array<string, string> Key => value, sorted by key.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-set-with-a-dark-variant-has-dark-roles
	 */
	public function ofDeclarations(array $declarations): array {
		$css = implode('', array_map(static fn (string $name): string => $name . ': x;', array_keys($declarations)));

		return $this->ofSet(css: $css, resolved: $declarations);
	}//end ofDeclarations()

	/**
	 * Whether a token name belongs in the vocabulary: not a role name, not a picture, address or shape.
	 *
	 * @param string $name The token name.
	 *
	 * @return bool True when it does.
	 */
	private function isVocabulary(string $name): bool {
		return in_array($name, self::ROLE_NAMES, true) === false && preg_match(self::NOT_A_VALUE_PATTERN, $name) !== 1;
	}//end isVocabulary()

	/**
	 * Whether a value means something outside thematiq: not empty, no variable, no address.
	 *
	 * @param string $value The resolved value.
	 *
	 * @return bool True when another product can use it.
	 */
	private function carriesMeaning(string $value): bool {
		$value = trim($value);

		return $value !== '' && str_contains($value, 'var(') === false && str_contains($value, 'url(') === false;
	}//end carriesMeaning()
}//end class
