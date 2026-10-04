<?php

/**
 * Shipped token-set typeface audit.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Answers one question per shipped set: when this set says
 * `--nldesign-font-family: 'Avenir', sans-serif`, does the instance actually
 * serve Avenir?
 *
 * Naming a typeface is not serving one. A set whose first family has no
 * `@font-face` in its own design system's font layer renders the next family in
 * the stack, which for most sets is Arial or the system sans, and nothing says
 * so: the page simply looks like a different organisation. Measured on
 * 2026-10-04, 33 of 58 shipped sets were in that state.
 *
 * The audit has three outcomes and no fourth:
 *
 *   1. `self-hosted`  — the family has a face in a stylesheet the set's design
 *                       system links, so it loads.
 *   2. `system`        — the family is a generic (`sans-serif`, `system-ui`) or a
 *                       font the operating system supplies (Arial), so there is
 *                       nothing to self-host and nothing to warn about.
 *   3. `declared`      — the family cannot be redistributed, and the set's
 *                       `font` block in `token-sets.json` says so, with its
 *                       licence position and the action an administrator takes.
 *                       The admin UI shows that, which is the whole point: an
 *                       administrator learns it from the product rather than
 *                       from a page that silently renders Arial.
 *
 * Anything else is `undeclared`: a set naming a family nobody serves and nobody
 * has written down. That is what the gate fails on.
 *
 * @category Service
 * @package  OCA\Thematiq
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Measures whether each shipped set's named typeface is actually served.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
 */
class TokenSetFontAuditService {

	/**
	 * Families the browser resolves without a webfont: CSS generics, and the
	 * faces an operating system supplies.
	 *
	 * A set naming one of these is not a defect. Arial in particular is what a
	 * municipality's own house style often specifies, and it is present on
	 * Windows and macOS and metric-substituted on Linux; self-hosting it is not
	 * possible and not needed.
	 *
	 * @var array<int, string>
	 */
	public const SYSTEM_FAMILIES = [
		'-apple-system',
		'Arial',
		'BlinkMacSystemFont',
		'Courier New',
		'cursive',
		'fantasy',
		'Georgia',
		'Helvetica',
		'Helvetica Neue',
		'inherit',
		'monospace',
		'sans-serif',
		'Segoe UI',
		'serif',
		'system-ui',
		'Tahoma',
		'Times New Roman',
		'Trebuchet MS',
		'ui-sans-serif',
		'Verdana',
	];

	/**
	 * The token a set names its typeface in.
	 */
	public const FAMILY_TOKEN = '--nldesign-font-family';

	/**
	 * Build the service from its pure collaborator.
	 *
	 * @param CssParserService $parser The shared CSS custom-property parser.
	 */
	public function __construct(
		private readonly CssParserService $parser = new CssParserService(),
	) {
	}//end __construct()

	/**
	 * The font families a design system's own stylesheets declare an
	 * `@font-face` for.
	 *
	 * Only the stylesheets the system LINKS count. `css/fonts.css` and
	 * `css/fonts-conduction.css` are not in any design system's list, so a face
	 * declared only there never loads on an instance however correct it looks in
	 * the repository.
	 *
	 * @param string $appPath The app root path.
	 * @param string $designSystem The design system id.
	 *
	 * @return array<int, string> The self-hosted family names.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function selfHostedFamilies(string $appPath, string $designSystem): array {
		$manifestPath = $appPath . '/design-systems.json';
		if (is_readable($manifestPath) === false) {
			return [];
		}

		$manifest = json_decode((string)file_get_contents($manifestPath), true);
		if (is_array($manifest) === false) {
			return [];
		}

		$families = [];
		foreach ($manifest as $system) {
			if (is_array($system) === false || ($system['id'] ?? null) !== $designSystem) {
				continue;
			}

			foreach (($system['stylesheets'] ?? []) as $stylesheet) {
				$path = $appPath . '/css/' . $stylesheet . '.css';
				if (is_file($path) === false) {
					continue;
				}

				$css = (string)file_get_contents($path);
				preg_match_all("/font-family:\s*'([^']+)'/", $this->stripComments(css: $css), $matches);
				foreach ($matches[1] as $family) {
					$families[$family] = true;
				}
			}
		}

		return array_keys($families);
	}//end selfHostedFamilies()

	/**
	 * Audit one shipped set's typeface.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string,mixed> $meta The set's `token-sets.json` entry.
	 *
	 * @return array{
	 *     id: string,
	 *     family: string|null,
	 *     stack: string|null,
	 *     kind: string,
	 *     selfHosted: bool,
	 *     declared: array<string, mixed>|null,
	 *     ok: bool
	 * }
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function auditSet(string $appPath, string $id, array $meta): array {
		$designSystem = (string)($meta['design_system'] ?? 'nldesign');
		$path = $appPath . '/css/tokens/' . $id . '.css';
		$declarations = [];
		if (is_file($path) === true) {
			$declarations = ($this->parser->parseDeclarations(content: (string)file_get_contents($path)) ?? []);
		}

		$stack = ($declarations[self::FAMILY_TOKEN] ?? null);
		$family = null;
		if ($stack !== null) {
			$resolved = $this->parser->resolveVarChain(value: $stack, declarations: $declarations);
			if ($resolved['value'] !== null) {
				$family = $this->firstFamily(stack: $resolved['value']);
			}
		}

		$declared = ($meta['font'] ?? null);
		if (is_array($declared) === false) {
			$declared = null;
		}

		$selfHosted = ($family !== null
			&& \in_array($family, $this->selfHostedFamilies(appPath: $appPath, designSystem: $designSystem), true) === true);

		$kind = $this->classify(family: $family, selfHosted: $selfHosted, declared: $declared);

		return [
			'id' => $id,
			'family' => $family,
			'stack' => $stack,
			'kind' => $kind,
			'selfHosted' => $selfHosted,
			'declared' => $declared,
			'ok' => ($kind !== 'undeclared'),
		];
	}//end auditSet()

	/**
	 * Audit every shipped set in `css/tokens/`, ordered by id.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<int, array<string, mixed>> One result per shipped set.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function auditAll(string $appPath): array {
		$manifest = [];
		$manifestPath = $appPath . '/token-sets.json';
		if (is_readable($manifestPath) === true) {
			$decoded = json_decode((string)file_get_contents($manifestPath), true);
			if (is_array($decoded) === true) {
				foreach ($decoded as $entry) {
					if (is_array($entry) === true && is_string(($entry['id'] ?? null)) === true) {
						$manifest[$entry['id']] = $entry;
					}
				}
			}
		}

		$files = glob($appPath . '/css/tokens/*.css');
		if (is_array($files) === false) {
			$files = [];
		}

		$results = [];
		foreach ($files as $file) {
			$id = basename($file, '.css');
			$results[] = $this->auditSet(appPath: $appPath, id: $id, meta: ($manifest[$id] ?? []));
		}

		usort($results, static fn (array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));

		return $results;
	}//end auditAll()

	/**
	 * The non-blocking admin warning for one set's typeface.
	 *
	 * Empty for a set whose family loads, and for one that names only system
	 * families. A set whose family cannot be served carries the licence position
	 * and the action, so the admin UI can say what the administrator has to do
	 * instead of letting the page render a substitute in silence.
	 *
	 * Shaped like the entries `TokenSetService::applyWarnings()` already passes
	 * to the admin UI, so there is no second warnings channel.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The token set id.
	 * @param array<string,mixed> $meta The set's manifest entry.
	 *
	 * @return array<int, array<string, mixed>> The warning entries (empty when the font loads).
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-set-says-when-its-typeface-cannot-be-served
	 */
	public function warningsFor(string $appPath, string $id, array $meta): array {
		$result = $this->auditSet(appPath: $appPath, id: $id, meta: $meta);
		if ($result['kind'] === 'self-hosted' || $result['kind'] === 'system') {
			return [];
		}

		$declared = ($result['declared'] ?? []);

		return [
			[
				'kind' => 'font',
				'family' => $result['family'],
				'licence' => ($declared['licence'] ?? null),
				'licenceHolder' => ($declared['licenceHolder'] ?? null),
				'action' => ($declared['action'] ?? null),
				'note' => ($declared['note'] ?? null),
			],
		];
	}//end warningsFor()

	/**
	 * Classify one set's typeface into the three acceptable outcomes, or
	 * `undeclared`.
	 *
	 * @param string|null $family The first family the set names, or null.
	 * @param bool $selfHosted Whether a linked stylesheet declares it.
	 * @param array<string,mixed>|null $declared The set's `font` block, when it has one.
	 *
	 * @return string One of `self-hosted`, `system`, `declared`, `undeclared`.
	 */
	private function classify(?string $family, bool $selfHosted, ?array $declared): string {
		// A set that names no family of its own leaves the choice to its design
		// system's own stylesheet; there is nothing here to serve or to declare.
		if ($family === null || \in_array($family, self::SYSTEM_FAMILIES, true) === true) {
			return 'system';
		}

		if ($selfHosted === true) {
			return 'self-hosted';
		}

		if ($this->declaresUndistributable(declared: $declared) === true) {
			return 'declared';
		}

		return 'undeclared';
	}//end classify()

	/**
	 * Whether a `font` block says, in full, that the family cannot be served.
	 *
	 * All three of licence, action and note are required: a block with a family
	 * name and nothing else tells an administrator nothing, so it must not count
	 * as having declared anything.
	 *
	 * @param array<string,mixed>|null $declared The set's `font` block, when it has one.
	 *
	 * @return bool True when the declaration is complete.
	 */
	private function declaresUndistributable(?array $declared): bool {
		if ($declared === null) {
			return false;
		}

		return (is_string(($declared['licence'] ?? null)) === true
			&& is_string(($declared['action'] ?? null)) === true
			&& is_string(($declared['note'] ?? null)) === true);
	}//end declaresUndistributable()

	/**
	 * The first family of a CSS font stack, unquoted.
	 *
	 * @param string $stack The font-family value.
	 *
	 * @return string|null The first family, or null for an empty stack.
	 */
	private function firstFamily(string $stack): ?string {
		$first = trim((string)(explode(',', $stack)[0] ?? ''));
		$first = trim($first, "\"'");

		if ($first === '') {
			return null;
		}

		return $first;
	}//end firstFamily()

	/**
	 * Strip CSS comments, so a commented-out `@font-face` never counts as served.
	 *
	 * @param string $css The raw CSS.
	 *
	 * @return string The CSS without comments.
	 */
	private function stripComments(string $css): string {
		return (string)preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
	}//end stripComments()
}//end class
