<?php

/**
 * Thematiq branding package token source conversion.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use Throwable;

/**
 * Converts the DTCG sources of a branding package (`tokens/<id>.json`) into
 * the bundle's `customTokenSets` entries of the same id.
 *
 * A source is refused when its file name is not a custom token set id, when
 * a colour token holds a value that is not a colour, or when the converter
 * fails or yields no CSS. A refused source adds an error and leaves the
 * bundle entry as it was.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class PackageTokenSourceConverter {

	/**
	 * Constructor.
	 *
	 * @param TokenSetConverterService $converter DTCG to token set conversion.
	 * @param CustomTokenSetService $customSets For the custom set id rules.
	 * @param DtcgValueChecker $values The value rules the editor and the overrides API apply.
	 */
	public function __construct(
		private readonly TokenSetConverterService $converter,
		private readonly CustomTokenSetService $customSets,
		private readonly DtcgValueChecker $values,
	) {
	}//end __construct()

	/**
	 * Convert every `tokens/<id>.json` and put it in the bundle as the custom set of that id.
	 *
	 * @param array<string, mixed> $package The read package.
	 * @param array<int, array<string, mixed>> $errors Accumulator.
	 *
	 * @return array<string, mixed> The bundle with the converted sets.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function intoBundle(array $package, array &$errors): array {
		$bundle = $package['bundle'];
		if (is_array($bundle['customTokenSets'] ?? []) === false) {
			return $bundle;
		}

		$sets = array_values($bundle['customTokenSets'] ?? []);
		foreach ($package['tokens'] as $id => $content) {
			$index = $this->findSet(sets: $sets, id: (string)$id);
			$converted = $this->convertOne(source: ['id' => (string)$id, 'content' => $content, 'existing' => ($sets[$index] ?? null)], errors: $errors);
			if ($converted !== null) {
				$sets[($index ?? count($sets))] = $converted;
			}
		}

		$bundle['customTokenSets'] = $sets;

		return $bundle;
	}//end intoBundle()

	/**
	 * Convert one DTCG source into a bundle `customTokenSets[]` entry.
	 *
	 * @param array{id: string, content: string, existing: array<string, mixed>|null} $source The source.
	 * @param array<int, array<string, mixed>> $errors Accumulator.
	 *
	 * @return array<string, mixed>|null The entry, or null on failure.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function convertOne(array $source, array &$errors): ?array {
		$refusals = $this->refusals(source: $source);
		if ($refusals !== []) {
			array_push($errors, ...$refusals);

			return null;
		}

		$source['name'] = $this->displayName(existing: $source['existing'], id: $source['id']);
		$result = $this->convertSource(source: $source);
		if (is_string($result) === true) {
			$errors[] = ['section' => 'tokens', 'id' => $source['id'], 'message' => 'tokens/' . $source['id'] . '.json: ' . $result];

			return null;
		}

		return $this->entry(source: $source, result: $result);
	}//end convertOne()

	/**
	 * The errors that refuse a source before conversion: a file name that is
	 * not a custom set id, or colour tokens whose value is not a colour.
	 *
	 * @param array{id: string, content: string, existing: array<string, mixed>|null} $source The source.
	 *
	 * @return array<int, array{section: string, id: string, message: string}> The errors; empty when the source may be converted.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function refusals(array $source): array {
		$id = $source['id'];
		if ($this->customSets->isCustomId(id: $id) === false) {
			return [
				[
					'section' => 'tokens',
					'id' => $id,
					'message' => 'tokens/' . $id . '.json: the file name must be a custom token set id (custom-<name>).',
				],
			];
		}

		return $this->invalidValueErrors(id: $id, content: $source['content']);
	}//end refusals()

	/**
	 * Run the converter on a source.
	 *
	 * @param array{id: string, content: string, existing: array<string, mixed>|null, name: string} $source The source and its display name.
	 *
	 * @return array<string, mixed>|string The converter result, or the message why it yielded no token set.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function convertSource(array $source): array|string {
		try {
			$result = $this->converter->convert(
				content: $source['content'],
				slug: $this->slug(id: $source['id']),
				displayName: $source['name'],
				options: ['sourceName' => $source['id'] . '.json', 'assetName' => $source['id']]
			);
		} catch (Throwable $e) {
			return $e->getMessage();
		}

		if (trim((string)$result['css']) === '' || $result['errors'] !== []) {
			return 'the document could not be converted into a token set.';
		}

		return $result;
	}//end convertSource()

	/**
	 * The bundle entry for a converted source, on top of the entry of the same id.
	 *
	 * @param array{id: string, content: string, existing: array<string, mixed>|null, name: string} $source The source.
	 * @param array<string, mixed> $result The converter result.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function entry(array $source, array $result): array {
		$entry = ($source['existing'] ?? []);
		$entry['id'] = $source['id'];
		$entry['name'] = $source['name'];
		$entry['css'] = $result['css'];
		$entry['theming'] = ($result['manifestEntry']['theming'] ?? ($entry['theming'] ?? []));
		$entry['importWarnings'] = $result['importWarnings'];
		if (isset($result['manifestEntry']['upstreamVersion']) === true) {
			$entry['version'] = $result['manifestEntry']['upstreamVersion'];
		}

		return $entry;
	}//end entry()

	/**
	 * The name of the converted set: the name of the bundle entry of the same id, else the slug.
	 *
	 * @param array<string, mixed>|null $existing The bundle entry of the same id, if any.
	 * @param string $id The custom set id.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function displayName(?array $existing, string $id): string {
		$name = trim((string)($existing['name'] ?? ''));
		if ($name === '') {
			return $this->slug(id: $id);
		}

		return $name;
	}//end displayName()

	/**
	 * The custom set id without its prefix.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return string The slug.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function slug(string $id): string {
		return substr($id, strlen(CustomTokenSetService::ID_PREFIX));
	}//end slug()

	/**
	 * One error per DTCG colour token whose value is not a colour.
	 *
	 * The same value rules as the editor and the overrides API (#809): a
	 * colour that is not one would otherwise be served as it is (#941).
	 *
	 * @param string $id The custom set id.
	 * @param string $content The DTCG document.
	 *
	 * @return array<int, array{section: string, id: string, message: string}> The errors, naming each token.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function invalidValueErrors(string $id, string $content): array {
		$decoded = json_decode($content, true);
		if (is_array($decoded) === false) {
			return [];
		}

		$errors = [];
		foreach ($this->values->findInvalidColours(document: $decoded) as $path => $value) {
			$errors[] = [
				'section' => 'tokens',
				'id' => $id,
				'message' => 'tokens/' . $id . '.json: ' . $path . ': "' . $value . '" is not a valid color value.',
			];
		}

		return $errors;
	}//end invalidValueErrors()

	/**
	 * The index of the set with an id in a list of bundle sets.
	 *
	 * @param array<int, mixed> $sets The bundle sets.
	 * @param string $id The id.
	 *
	 * @return int|null The index, or null when absent.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function findSet(array $sets, string $id): ?int {
		foreach ($sets as $index => $set) {
			if (is_array($set) === true && ($set['id'] ?? null) === $id) {
				return $index;
			}
		}

		return null;
	}//end findSet()
}//end class
