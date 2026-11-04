<?php

/**
 * What a new custom token set is stored from.
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

use InvalidArgumentException;

/**
 * The fields CustomTokenSetService::store() writes a new set from.
 *
 * Built from a field array, so callers name each field and leave out the ones they do not set.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class NewCustomTokenSet {
	/**
	 * The optional fields and their values when left out.
	 */
	private const OPTIONAL = [
		'version' => null,
		'importWarnings' => [],
		'css' => null,
		'theming' => [],
		'logoAsset' => null,
		'designSystem' => null,
	];

	/**
	 * The fields every set needs.
	 */
	private const REQUIRED = ['displayName' => true, 'description' => true, 'declarations' => true];

	/**
	 * The admin display name.
	 *
	 * @var string
	 */
	public readonly string $displayName;

	/**
	 * The description, possibly empty.
	 *
	 * @var string
	 */
	public readonly string $description;

	/**
	 * The whitelisted declarations.
	 *
	 * @var array<string, string>
	 */
	public readonly array $declarations;

	/**
	 * The declared DTCG package version, verbatim (never fabricated).
	 *
	 * @var string|null
	 */
	public readonly ?string $version;

	/**
	 * DTCG `$deprecated` import warnings. Kept apart from the contrast `warnings` store() computes:
	 * the contrast banner keys off `pair`/`ratio`, an import warning carries `path`/`message`.
	 *
	 * @var array<int, array{path: string, message: string|null}>
	 */
	public readonly array $importWarnings;

	/**
	 * Canonical file text to write verbatim instead of re-serialising the declarations.
	 *
	 * The converter path passes its own emitted file, whose four commented sections and provenance
	 * block would be flattened away by a re-serialise. The caller MUST have validated these exact
	 * bytes: this never bypasses a gate, it only chooses which already-validated text is stored.
	 *
	 * @var string|null
	 */
	public readonly ?string $css;

	/**
	 * Theming values merged OVER the derived ones.
	 *
	 * The converter routes a theme's page background to `theming.background_color` on purpose (so
	 * Nextcloud keeps owning `--color-main-background` and dark mode), and the derived theming
	 * cannot see that decision from the declarations alone.
	 *
	 * @var array<string, string>
	 */
	public readonly array $theming;

	/**
	 * A logo the converter decoded out of the theme, to be written under `img/logos/`.
	 *
	 * Nextcloud's core theming takes a logo as a FILE, so a theme's inline `data:` URI has to become
	 * one before `theming.logo` can mean anything.
	 *
	 * @var array{path: string, contents: string}|null
	 */
	public readonly ?array $logoAsset;

	/**
	 * The design system the set was created from, when the caller knows it.
	 *
	 * Decides which stylesheet layers the set emits; left unset for an upload of an unknown
	 * document, which still resolves to nldesign.
	 *
	 * @var string|null
	 */
	public readonly ?string $designSystem;

	/**
	 * Build the set from named fields.
	 *
	 * @param array<string, mixed> $fields displayName, description and declarations, plus any optional field.
	 *
	 * @throws InvalidArgumentException When a field is unknown or a required one is missing.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(array $fields) {
		$all = self::withDefaults(fields: $fields);
		$this->displayName = $all['displayName'];
		$this->description = $all['description'];
		$this->declarations = $all['declarations'];
		$this->version = $all['version'];
		$this->importWarnings = $all['importWarnings'];
		$this->css = $all['css'];
		$this->theming = $all['theming'];
		$this->logoAsset = $all['logoAsset'];
		$this->designSystem = $all['designSystem'];
	}//end __construct()

	/**
	 * The given fields with every optional one that was left out at its default.
	 *
	 * @param array<string, mixed> $fields The given fields.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When a field is unknown or a required one is missing.
	 */
	private static function withDefaults(array $fields): array {
		$unknown = array_keys(array_diff_key($fields, self::REQUIRED + self::OPTIONAL));
		$missing = array_keys(array_diff_key(self::REQUIRED, $fields));
		if ($unknown !== [] || $missing !== []) {
			throw new InvalidArgumentException('Unknown fields [' . implode(', ', $unknown) . '], missing fields [' . implode(', ', $missing) . ']');
		}

		return array_replace(self::OPTIONAL, $fields);
	}//end withDefaults()
}//end class
