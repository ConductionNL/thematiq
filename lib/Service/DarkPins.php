<?php

/**
 * The dark values an uploaded file brought, kept per uploaded set.
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

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;

/**
 * One `:root` block per uploaded set in `css/tokens/dark-pins/{id}.css` of the runtime store. The
 * dark generator merges it over the derived dark values ({@see DarkPaletteService::generateForSet()}).
 * Only for an uploaded set: a shipped set authors its dark values in its own dark block. The caller
 * has validated the declarations.
 *
 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
 */
class DarkPins {

	/**
	 * Where the values are kept.
	 *
	 * @var string
	 */
	public const DIR = 'css/tokens/dark-pins/';

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileStore $store The runtime store.
	 * @param CssParserService $parser Reads the block back.
	 */
	public function __construct(
		private readonly RuntimeFileStore $store,
		private readonly CssParserService $parser = new CssParserService(),
	) {
	}//end __construct()

	/**
	 * Keep a set's dark values; an empty map removes them.
	 *
	 * @param string $setId The token set id.
	 * @param array<string, string> $declarations Token name => dark value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function write(string $setId, array $declarations): void {
		if ($this->isUploaded(setId: $setId) === false) {
			return;
		}

		if ($declarations === []) {
			$this->delete(setId: $setId);

			return;
		}

		ksort($declarations);
		$css = ":root {\n";
		foreach ($declarations as $token => $value) {
			$css .= "\t" . $token . ': ' . $value . ";\n";
		}

		$this->store->write(name: self::DIR . $setId . '.css', content: $css . "}\n");
	}//end write()

	/**
	 * A set's dark values, or none.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return array<string, string> Token name => dark value.
	 *
	 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function read(string $setId): array {
		if ($this->isUploaded(setId: $setId) === false) {
			return [];
		}

		$css = $this->store->read(name: self::DIR . $setId . '.css');
		if ($css === null) {
			return [];
		}

		return $this->parser->parseRootBlock(css: $css);
	}//end read()

	/**
	 * Remove a set's dark values.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-colours-from-a-file/spec.md#requirement-dark-colours-from-a-file-are-kept
	 */
	public function delete(string $setId): void {
		if ($this->isUploaded(setId: $setId) === true) {
			$this->store->delete(name: self::DIR . $setId . '.css');
		}
	}//end delete()

	/**
	 * Whether a set id is an uploaded set.
	 *
	 * @param string $setId The token set id.
	 *
	 * @return bool True for a `custom-` id.
	 */
	private function isUploaded(string $setId): bool {
		return str_starts_with($setId, CustomTokenSetService::ID_PREFIX);
	}//end isUploaded()
}//end class
