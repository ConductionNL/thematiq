<?php

/**
 * Thematiq Gallery Entry Validator.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Decides which theme gallery index entries may be listed and installed. The same rules are in
 * gallery/index.schema.json; tests/Unit/Gallery/GalleryIndexSchemaTest.php keeps the two in step.
 *
 * @spec openspec/specs/theme-gallery/spec.md#requirement-an-administrator-browses-the-gallery
 */
class GalleryEntryValidator {

	/**
	 * The formats the upload path can convert.
	 *
	 * @var array<int, string>
	 */
	private const FORMATS = ['css', 'dtcg', 'design-tokens-css'];

	/**
	 * The normalised entry, or null when it may not be listed. Mirrors gallery/index.schema.json.
	 *
	 * @param mixed $raw One element of the index's `entries`.
	 *
	 * @return array<string, mixed>|null The entry.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-an-administrator-browses-the-gallery
	 */
	public function validate(mixed $raw): ?array {
		if (is_array($raw) === false) {
			return null;
		}

		$checks = [
			'id' => '/^[a-z0-9]+(-[a-z0-9]+)*$/',
			'licence' => '/^[A-Za-z0-9.+-]+( (AND|OR|WITH) [A-Za-z0-9.+-]+)*$/',
			'sha256' => '/^[a-f0-9]{64}$/',
			'sourceUrl' => '#^https://[^\s]+$#',
			'fileUrl' => '#^https://[^\s]+$#',
			'addedOn' => '/^\d{4}-\d{2}-\d{2}$/',
		];
		foreach ($checks as $field => $pattern) {
			if (is_string($raw[$field] ?? null) === false || preg_match($pattern, $raw[$field]) !== 1) {
				return null;
			}
		}

		if ($this->validText(raw: $raw) === false || $this->validSwatches(swatches: ($raw['swatches'] ?? null)) === false) {
			return null;
		}

		return [
			'id' => $raw['id'],
			'name' => $raw['name'],
			'organisation' => $raw['organisation'],
			'description' => $this->description(raw: $raw),
			'licence' => $raw['licence'],
			'sourceUrl' => $raw['sourceUrl'],
			'fileUrl' => $raw['fileUrl'],
			'sha256' => $raw['sha256'],
			'format' => $raw['format'],
			'swatches' => $raw['swatches'],
			'contrast' => ($raw['contrast'] ?? null),
			'addedOn' => $raw['addedOn'],
		];
	}//end validate()

	/**
	 * Whether the swatches are three hex colours.
	 *
	 * @param mixed $swatches The swatches field.
	 *
	 * @return boolean True when primary, background and text are all `#rgb` or `#rrggbb`.
	 */
	private function validSwatches(mixed $swatches): bool {
		if (is_array($swatches) === false) {
			return false;
		}

		foreach (['primary', 'background', 'text'] as $key) {
			if (is_string($swatches[$key] ?? null) === false || preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $swatches[$key]) !== 1) {
				return false;
			}
		}

		return true;
	}//end validSwatches()

	/**
	 * Whether the name and organisation are non-empty and the format is one the upload path converts.
	 *
	 * @param array<string, mixed> $raw The entry.
	 *
	 * @return boolean True when they are.
	 */
	private function validText(array $raw): bool {
		foreach (['name', 'organisation'] as $field) {
			if (is_string($raw[$field] ?? null) === false || trim($raw[$field]) === '') {
				return false;
			}
		}

		return in_array(($raw['format'] ?? null), self::FORMATS, true);
	}//end validText()

	/**
	 * The description, or '' when it is missing or not text.
	 *
	 * @param array<string, mixed> $raw The entry.
	 *
	 * @return string The description.
	 */
	private function description(array $raw): string {
		if (is_string($raw['description'] ?? null) === false) {
			return '';
		}

		return $raw['description'];
	}//end description()
}//end class
