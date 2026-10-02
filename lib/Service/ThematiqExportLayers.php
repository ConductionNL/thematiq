<?php

/**
 * Thematiq export layers.
 *
 * A DTCG document thematiq itself exported is imported without the conversion rules:
 * every declaration comes back under its own name, so a round trip is exact. Only the
 * brand palette moves to the new set's prefix (the validator accepts no other), and every
 * `var()` pointing at a moved name follows it.
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
 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.4
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Split an exported set's declarations into the three emitted layers.
 *
 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.4
 */
class ThematiqExportLayers {

	/**
	 * Split, rename the palette, and report each declaration.
	 *
	 * @param array<string, string>            $declarations Name => value, as the export carried them.
	 * @param string                           $slug         The new set's slug.
	 * @param array<int, array<string, mixed>> $report       The conversion report, appended to.
	 *
	 * @return array{palette: array<string, string>, component: array<string, string>, semantic: array<string, string>}
	 *
	 * @spec openspec/changes/authoring-dtcg-export/tasks.md#task-4.4
	 */
	public function split(array $declarations, string $slug, array &$report): array {
		$renames = [];
		foreach (array_keys($declarations) as $name) {
			if ($this->layerOf(name: $name) === 'palette' && str_starts_with($name, '--' . $slug . '-') === false) {
				$renames[$name] = '--' . $slug . '-' . (string)(explode('-', ltrim($name, '-'), 2)[1] ?? '');
			}
		}

		$layers = ['palette' => [], 'component' => [], 'semantic' => []];
		foreach ($declarations as $name => $value) {
			$target = ($renames[$name] ?? $name);
			$value  = $this->followRenames(value: $value, renames: $renames);
			$layers[$this->layerOf(name: $name)][$target] = $value;
			$report[] = [
				'source' => $name,
				'target' => $target,
				'action' => ($target === $name ? 'kept' : 'adapted'),
				'reason' => ($target === $name ? 'thematiq-export' : 'palette-reprefixed'),
				'value' => $value,
			];
		}

		return $layers;
	}//end split()

	/**
	 * Which layer a name belongs to.
	 *
	 * @param string $name The custom property.
	 *
	 * @return string palette, component or semantic.
	 */
	private function layerOf(string $name): string {
		if (str_starts_with($name, '--nldesign-') === true) {
			return 'semantic';
		}

		foreach (TokenSetConverterService::COMPONENT_PREFIXES as $prefix) {
			if (str_starts_with($name, $prefix) === true) {
				return 'component';
			}
		}

		return 'palette';
	}//end layerOf()

	/**
	 * Point every `var()` at a moved name's new name.
	 *
	 * @param string                $value   The value.
	 * @param array<string, string> $renames Old name => new name.
	 *
	 * @return string
	 */
	private function followRenames(string $value, array $renames): string {
		return (string)preg_replace_callback(
			'/var\(\s*(--[A-Za-z0-9_-]+)/',
			static fn (array $m): string => 'var(' . ($renames[$m[1]] ?? $m[1]),
			$value
		);
	}//end followRenames()
}//end class
