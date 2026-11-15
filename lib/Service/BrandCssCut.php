<?php

/**
 * Cuts one brand out of a CSS source with a class per brand.
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Cuts one brand out of the blocks of a CSS source: the blocks every brand shares, then the
 * brand's own class, written back as CSS.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md
 */
class BrandCssCut {

	/**
	 * The CSS of one brand.
	 *
	 * @param array<int, array{selector: string, declarations: array<string, string>}> $blocks The source's blocks.
	 * @param string $key The brand key.
	 *
	 * @return string|null The CSS, or null when the source has no block for the brand.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-each-brand-is-converted-by-the-existing-pipeline
	 */
	public function css(array $blocks, string $key): ?string {
		$own = $this->brandBlock(blocks: $blocks, key: $key);
		if ($own === null) {
			return null;
		}

		return $this->blocksCss(blocks: array_merge($this->sharedBlocks(blocks: $blocks), [$own]));
	}//end css()

	/**
	 * The last block whose selector is the brand's own class.
	 *
	 * @param array<int, array{selector: string, declarations: array<string, string>}> $blocks The blocks.
	 * @param string $key The brand key.
	 *
	 * @return array{selector: string, declarations: array<string, string>}|null The block, or null when the source has none.
	 */
	private function brandBlock(array $blocks, string $key): ?array {
		$own = null;
		foreach ($blocks as $block) {
			if (preg_match(MultiBrandSource::BRAND_SELECTOR, $block['selector'], $match) === 1 && $match[1] === $key) {
				$own = $block;
			}
		}

		return $own;
	}//end brandBlock()

	/**
	 * The blocks every brand shares: neither a brand's class nor a modifier of one.
	 *
	 * A modifier of another brand (`.zuid-theme--dark`) is that brand's, not shared.
	 *
	 * @param array<int, array{selector: string, declarations: array<string, string>}> $blocks The blocks.
	 *
	 * @return array<int, array{selector: string, declarations: array<string, string>}> The shared blocks.
	 */
	private function sharedBlocks(array $blocks): array {
		$shared = [];
		foreach ($blocks as $block) {
			if (preg_match(MultiBrandSource::BRAND_SELECTOR, $block['selector']) !== 1 && preg_match('/^\.([a-z0-9-]+)-theme--/', $block['selector']) !== 1) {
				$shared[] = $block;
			}
		}

		return $shared;
	}//end sharedBlocks()

	/**
	 * Blocks written back as CSS.
	 *
	 * @param array<int, array{selector: string, declarations: array<string, string>}> $blocks The blocks.
	 *
	 * @return string The CSS.
	 */
	private function blocksCss(array $blocks): string {
		$css = '';
		foreach ($blocks as $block) {
			$lines = [];
			foreach ($block['declarations'] as $name => $value) {
				$lines[] = '  ' . $name . ': ' . $value . ';';
			}

			$css .= $block['selector'] . " {\n" . implode("\n", $lines) . "\n}\n";
		}

		return $css;
	}//end blocksCss()
}//end class
