<?php

/**
 * The custom token sets in a configuration bundle.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;

/**
 * Bundle section `customTokenSets`: exports every custom set with its CSS,
 * validates each entry's id, name, CSS and declarations (via
 * {@see CustomTokenSetValidator}), and writes the validated sets.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
class CustomTokenSetsBundleSection {

	/**
	 * The optional fields an entry carries over, in order, and whether each must be a list.
	 *
	 * @var array<string, bool>
	 */
	private const OPTIONAL_FIELDS = ['warnings' => true, 'version' => false, 'importWarnings' => true];

	/**
	 * Constructor.
	 *
	 * @param CustomTokenSetService $customSets The custom token set storage.
	 * @param CustomTokenSetValidator $validator The custom token set declaration validator.
	 * @param CssParserService $cssParser The CSS parser.
	 * @param IAppManager $appManager For the app path of the design systems manifest.
	 */
	public function __construct(
		private readonly CustomTokenSetService $customSets,
		private readonly CustomTokenSetValidator $validator,
		private readonly CssParserService $cssParser,
		private readonly IAppManager $appManager,
	) {
	}//end __construct()

	/**
	 * Build the `customTokenSets` export list.
	 *
	 * @return array<int, array<string, mixed>> One entry per custom set: id + the full manifest entry + css.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function export(): array {
		$result = [];
		foreach ($this->customSets->list() as $set) {
			$id = (string)($set['id'] ?? '');
			$css = $this->customSets->getRawContent(id: $id);
			if ($css === null) {
				continue;
			}

			$entry = $set;
			$entry['css'] = $css;
			$result[] = $entry;
		}

		return $result;
	}//end export()

	/**
	 * Validate the `customTokenSets` section: each entry's id namespace and
	 * declarations (via {@see CustomTokenSetValidator}).
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<int, array<string, mixed>> The resolved, ready-to-write custom set entries.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function validate(array $bundle, array &$errors): array {
		$sets = ($bundle['customTokenSets'] ?? []);
		if (is_array($sets) === false) {
			$errors[] = ['section' => 'customTokenSets', 'message' => '"customTokenSets" must be a list.'];

			return [];
		}

		$resolved = [];
		foreach (array_values($sets) as $index => $set) {
			$entry = $this->validateEntry(item: ['set' => $set, 'index' => $index], errors: $errors);
			if ($entry !== null) {
				$resolved[] = $entry;
			}
		}

		return $resolved;
	}//end validate()

	/**
	 * Validate one `customTokenSets[]` entry: shape, then declarations.
	 * Split across two focused helpers (see {@see validateShape()},
	 * {@see validateDeclarations()}) rather than one large method,
	 * so each validation stage has an independently readable failure path.
	 *
	 * @param array{set: mixed, index: int} $item The raw entry and its position, for error messages.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, mixed>|null The resolved entry, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateEntry(array $item, array &$errors): ?array {
		$shape = $this->validateShape(item: $item, errors: $errors);
		if ($shape === null) {
			return null;
		}

		$accepted = $this->validateDeclarations(shape: $shape, errors: $errors);
		if ($accepted === null) {
			return null;
		}

		return $this->buildResolvedEntry(shape: $shape, accepted: $accepted);
	}//end validateEntry()

	/**
	 * Validate a `customTokenSets[]` entry's basic shape: it is an object,
	 * `id` is a valid custom-set id, `name` is non-empty, and `css` is a
	 * non-empty string.
	 *
	 * @param array{set: mixed, index: int} $item The raw entry and its position, for error messages.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array{id: string, name: string, css: string, index: int, set: array<string, mixed>}|null The shape, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateShape(array $item, array &$errors): ?array {
		$set = $item['set'];
		$problem = $this->shapeProblem(set: $set);
		if ($problem !== null) {
			$errors[] = ['section' => 'customTokenSets', 'index' => $item['index']] + $problem;

			return null;
		}

		return [
			'id' => (string)$set['id'],
			'name' => trim((string)$set['name']),
			'css' => $set['css'],
			'index' => $item['index'],
			'set' => $set,
		];
	}//end validateShape()

	/**
	 * What is wrong with an entry's shape, with the id when it has one.
	 *
	 * @param mixed $set The raw entry.
	 *
	 * @return array{id?: string, message: string}|null The problem, or null when the shape is fine.
	 */
	private function shapeProblem(mixed $set): array|null {
		if (is_array($set) === false) {
			return ['message' => 'Entry must be an object.'];
		}

		$id = (string)($set['id'] ?? '');
		if ($this->customSets->isCustomId(id: $id) === false) {
			return ['id' => $id, 'message' => 'Invalid custom token set id.'];
		}

		$message = ($this->nameProblem(set: $set) ?? $this->cssProblem(set: $set));
		if ($message === null) {
			return null;
		}

		return ['id' => $id, 'message' => $message];
	}//end shapeProblem()

	/**
	 * The problem when an entry has no name.
	 *
	 * @param array<string, mixed> $set The raw entry.
	 *
	 * @return string|null The problem, or null.
	 */
	private function nameProblem(array $set): string|null {
		if (trim((string)($set['name'] ?? '')) === '') {
			return 'Missing "name".';
		}

		return null;
	}//end nameProblem()

	/**
	 * The problem when an entry's css is missing or not a non-empty string.
	 *
	 * @param array<string, mixed> $set The raw entry.
	 *
	 * @return string|null The problem, or null.
	 */
	private function cssProblem(array $set): string|null {
		$css = ($set['css'] ?? null);
		if (is_string($css) === false || $css === '') {
			return 'Missing or invalid "css".';
		}

		return null;
	}//end cssProblem()

	/**
	 * Validate a `customTokenSets[]` entry's CSS: no disallowed selector,
	 * then {@see CustomTokenSetValidator::validateDeclarations()}.
	 *
	 * @param array{id: string, name: string, css: string, index: int, set: array<string, mixed>} $shape The validated shape.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, string>|null The accepted declarations, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateDeclarations(array $shape, array &$errors): ?array {
		if ($this->validator->hasDisallowedSelector(css: $shape['css']) === true) {
			$errors[] = $this->entryError(shape: $shape, message: 'The CSS contains a selector or at-rule other than :root.');

			return null;
		}

		$slug = substr($shape['id'], strlen(CustomTokenSetService::ID_PREFIX));
		$declarations = $this->cssParser->parseRootBlock(css: $shape['css']);
		$split = $this->validator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null) {
			$validatorError = $this->validator->getLastError();
			$errors[] = $this->entryError(shape: $shape, message: ($validatorError['message'] ?? 'Declaration validation failed.'));

			return null;
		}

		return $split['accepted'];
	}//end validateDeclarations()

	/**
	 * The error for a set whose shape is fine.
	 *
	 * @param array{id: string, index: int} $shape The set's position and id.
	 * @param string $message What is wrong.
	 *
	 * @return array<string, mixed> The error.
	 */
	private function entryError(array $shape, string $message): array {
		return [
			'section' => 'customTokenSets',
			'index' => $shape['index'],
			'id' => $shape['id'],
			'message' => $message,
		];
	}//end entryError()

	/**
	 * Build the resolved, ready-to-write entry from a shape-and-declaration-valid set.
	 *
	 * @param array{id: string, name: string, css: string, index: int, set: array<string, mixed>} $shape The validated shape and raw entry.
	 * @param array<string, string> $accepted The validated, accepted declarations.
	 *
	 * @return array<string, mixed> The resolved entry: `{id, entry, css}`.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function buildResolvedEntry(array $shape, array $accepted): array {
		$set = $shape['set'];
		$entry = [
			'name' => $shape['name'],
			'description' => (string)($set['description'] ?? ''),
			'theming' => $this->themingOf(set: $set),
		] + $this->optionalFields(set: $set);

		// The design system the set was created on. Dropped here, a theme saved
		// off stock Nextcloud came back from a version restore or a bundle as an
		// NL Design System one, with every layer of that system on top of it.
		// Allow-listed, like the upload's claim: it decides which stylesheets load.
		$designSystem = $this->shippedDesignSystem(set: $set);
		if ($designSystem !== null) {
			$entry['design_system'] = $designSystem;
		}

		return [
			'id' => $shape['id'],
			'entry' => $entry,
			'css' => $this->validator->serialize(declarations: $accepted),
		];
	}//end buildResolvedEntry()

	/**
	 * The set's theming block, [] when it has none.
	 *
	 * @param array<string, mixed> $set The raw entry.
	 *
	 * @return array<string, mixed> The block.
	 */
	private function themingOf(array $set): array {
		if (is_array($set['theming'] ?? null) === true) {
			return $set['theming'];
		}

		return [];
	}//end themingOf()

	/**
	 * The optional fields the set carries over: its warnings and import warnings when they are lists, and its version.
	 *
	 * @param array<string, mixed> $set The raw entry.
	 *
	 * @return array<string, mixed> The fields, in that order.
	 */
	private function optionalFields(array $set): array {
		$fields = [];
		foreach (self::OPTIONAL_FIELDS as $field => $mustBeList) {
			if (isset($set[$field]) === true && ($mustBeList === false || is_array($set[$field]) === true)) {
				$fields[$field] = $set[$field];
			}
		}

		return $fields;
	}//end optionalFields()

	/**
	 * The design system the set names, when the app ships it.
	 *
	 * @param array<string, mixed> $set The raw entry.
	 *
	 * @return string|null The design-system id, or null.
	 */
	private function shippedDesignSystem(array $set): string|null {
		$designSystem = ($set['design_system'] ?? null);
		if (is_string($designSystem) === true && in_array($designSystem, $this->shippedDesignSystemIds(), true) === true) {
			return $designSystem;
		}

		return null;
	}//end shippedDesignSystem()

	/**
	 * The ids of the design systems the app ships, from its design-systems.json.
	 *
	 * @return array<int, string> The ids; empty when the manifest is missing or unreadable.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function shippedDesignSystemIds(): array {
		$path = $this->appManager->getAppPath(Application::APP_ID) . '/design-systems.json';
		$manifest = [];
		if (is_file($path) === true) {
			$manifest = json_decode((string)file_get_contents($path), true);
		}

		$ids = array_column(array_filter((array)$manifest, 'is_array'), 'id');

		return array_values(array_filter($ids, 'is_string'));
	}//end shippedDesignSystemIds()

	/**
	 * Write every resolved custom set.
	 *
	 * @param array<int, array<string, mixed>> $resolved The entries {@see validate()} resolved.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function apply(array $resolved): void {
		foreach ($resolved as $set) {
			$this->customSets->replace(id: $set['id'], entry: $set['entry'], css: $set['css']);
		}
	}//end apply()
}//end class
