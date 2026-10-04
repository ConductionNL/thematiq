<?php

/**
 * Which token sets an administrator may choose.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * `TokenSetService` discovers what the app ships and merges its metadata. This
 * class answers a different question — what may be PICKED — and it is separate
 * because the two have different failure modes and the decision used to be a
 * hand-written constant that went stale without anything noticing.
 *
 * WHAT IT REPLACED. `TokenSetService::SELECTABLE_SHIPPED_SETS = ['nextcloud',
 * 'cunningham']` offered 2 of 59 shipped sets, so an administrator could not
 * choose vng, leiden, zwolle, rotterdam or any other municipality at all. It was
 * correct when written: in 2026-09 nearly every set file declared none of the
 * `--nldesign-*` vocabulary the theme reads, so picking one rendered
 * Rijkshuisstijl with the wrong header. Its own docblock named the exit
 * condition — a set returns once it passes `TokenSetVocabularyAuditService` and
 * leaves the shrink-only allow-list, and then "every shipped set is selectable
 * again and this constant is deleted rather than widened". That condition was
 * met and the constant stayed, because nothing failed when it went stale.
 *
 * So the decision is now derived from the data on every read, and the thing that
 * can go stale no longer exists.
 *
 * @category Service
 * @package  OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * Filters the catalogue down to what an administrator may choose.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
 */
class TokenSetSelectionPolicy {

	/**
	 * The warning kind the vocabulary audit puts on an incomplete set, on the
	 * `warnings` channel `TokenSetService::applyWarnings()` already fills.
	 */
	public const INCOMPLETE_WARNING_KIND = 'incomplete';

	/**
	 * The id prefix of an admin-imported set.
	 */
	public const CUSTOM_PREFIX = 'custom-';

	/**
	 * The token sets an administrator may choose, read from the live catalogue.
	 *
	 * This lives here rather than on `TokenSetService` because the two answer
	 * different questions — what the app SHIPS and what may be PICKED — and
	 * because keeping it there put that class over its coupling and complexity
	 * budget, which is the kind of pressure that tells you a class has grown a
	 * second job.
	 *
	 * The group mapping is read straight from `IConfig` rather than through
	 * `GroupThemingService`, which depends on `TokenSetService`: re-reading the
	 * plain JSON array of `{group, tokenSet}` avoids a circular dependency.
	 *
	 * @param TokenSetService $tokenSets Discovery and metadata for every shipped and uploaded set.
	 * @param IConfig $config Reads the active set and the per-group mapping.
	 * @param string $appPath The app root, for `token-sets.json`.
	 *
	 * @return array<int, array<string, mixed>> The selectable entries, in catalogue order.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function selectable(TokenSetService $tokenSets, IConfig $config, string $appPath): array {
		return $this->filter(
			entries: $tokenSets->getAvailableTokenSets(),
			namedIds: $this->namedIds(appPath: $appPath),
			// Cast at the boundary: an appconfig value is whatever is stored,
			// and a key that was never written back comes through as null on
			// some paths. Null would make the active-set rule key on '' and the
			// mapping parse throw.
			activeId: (string)$config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud'),
			mappedIds: $this->mappedIds(
				rawMapping: (string)$config->getAppValue(Application::APP_ID, 'group_token_sets', '[]')
			)
		);
	}

	/**
	 * The set ids `token-sets.json` names.
	 *
	 * A missing or malformed manifest yields an empty list, which withholds
	 * every shipped set rather than offering unnamed ones — the safe direction,
	 * and the three survival rules still keep the instance working.
	 *
	 * @param string $appPath The app root.
	 *
	 * @return array<int, string> The named set ids.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	private function namedIds(string $appPath): array {
		$path = $appPath . '/token-sets.json';
		if (is_readable($path) === false) {
			return [];
		}

		$manifest = json_decode((string)file_get_contents($path), true);
		if (is_array($manifest) === false) {
			return [];
		}

		$ids = [];
		foreach ($manifest as $entry) {
			if (is_array($entry) === true && is_string($entry['id'] ?? null) === true) {
				$ids[] = $entry['id'];
			}
		}

		return $ids;
	}

	/**
	 * Filter a catalogue to the sets an administrator may choose.
	 *
	 * Two measured conditions, and three ids that survive both:
	 *
	 *  A. The set is NAMED in `token-sets.json`. Discovery is filesystem-based,
	 *     so `css/tokens/conduction.css` is found like any other file — but it
	 *     is the shared role layer `scripts/generate-brand-set.mjs` copies into
	 *     a brand set, not a theme, and it has no name, description or theming
	 *     of its own. Offered, it would read as "Conduction" with a generated
	 *     description.
	 *  B. The vocabulary audit does not report it incomplete. A set declaring
	 *     none of the vocabulary the theme reads renders as `defaults.css`'s
	 *     brand rather than its own, and an administrator cannot see that in a
	 *     dropdown.
	 *
	 * The number of `--utrecht-*` component tokens a set declares is
	 * deliberately NOT a condition. Measured on
	 * `css/systems/nldesign/utrecht-bridge.css`, 42 of its 84 declarations fall
	 * back to a `--nldesign-*` token the set declares, 38 to a non-colour
	 * literal and 3 to a colour literal, so a set declaring none of them adopts
	 * the design system's component geometry and type scale and still brands the
	 * component colours itself. Resolved over the real cascade, `amsterdam` and
	 * `rijkshuisstijl` are both at zero and differ on 32 of the 84 component
	 * tokens. Withholding such a set would withhold a working theme.
	 *
	 * The three survivors exist because narrowing a picker must not be able to
	 * change what an instance is DOING:
	 *
	 *  1. the set the instance is currently running — dropping it renders the
	 *     panel with nothing selected, and the first save re-themes the
	 *     instance to whatever came first;
	 *  2. any set a per-group mapping points at — the group picker is fed from
	 *     this same list, so a theme that is still applying would vanish from
	 *     the UI meant to manage it;
	 *  3. every `custom-*` import, unconditionally — the importer tells the
	 *     administrator their upload is "added and selectable".
	 *
	 * @param array<int, array<string, mixed>> $entries The catalogue, from `getAvailableTokenSets()`.
	 * @param array<int, string> $namedIds The ids `token-sets.json` carries.
	 * @param string $activeId The set the instance is running ('' when unset).
	 * @param array<int, string> $mappedIds The sets per-group mappings point at.
	 *
	 * @return array<int, array<string, mixed>> The selectable entries, in catalogue order.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function filter(array $entries, array $namedIds, string $activeId, array $mappedIds): array {
		$survivors = array_fill_keys($mappedIds, true);
		if ($activeId !== '') {
			$survivors[$activeId] = true;
		}

		$named = array_fill_keys($namedIds, true);

		$selectable = [];
		foreach ($entries as $entry) {
			if ($this->isSelectable(entry: $entry, named: $named, survivors: $survivors) === true) {
				$selectable[] = $entry;
			}
		}

		return $selectable;
	}

	/**
	 * Whether one catalogue entry may be chosen.
	 *
	 * @param array<string, mixed> $entry The catalogue entry.
	 * @param array<string, bool> $named Ids `token-sets.json` carries, as a lookup.
	 * @param array<string, bool> $survivors Ids that survive both conditions, as a lookup.
	 *
	 * @return bool True when the entry belongs in the picker.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function isSelectable(array $entry, array $named, array $survivors): bool {
		$id = (string)($entry['id'] ?? '');

		if (isset($survivors[$id]) === true || str_starts_with($id, self::CUSTOM_PREFIX) === true) {
			return true;
		}

		if (isset($named[$id]) === false) {
			return false;
		}

		return ($this->isVocabularyIncomplete(entry: $entry) === false);
	}

	/**
	 * Whether a catalogue entry carries the vocabulary audit's "incomplete"
	 * finding.
	 *
	 * The finding is READ off the entry, never recomputed:
	 * `TokenSetService::getAvailableTokenSets()` already ran the audit for every
	 * set through `applyWarnings()`, so auditing again would double the work on
	 * every admin page render and make the dropdown and the "Incomplete set"
	 * banner two answers to one question.
	 *
	 * @param array<string, mixed> $entry The catalogue entry.
	 *
	 * @return bool True when the set is vocabulary-incomplete.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function isVocabularyIncomplete(array $entry): bool {
		$warnings = ($entry['warnings'] ?? []);
		if (is_array($warnings) === false) {
			return false;
		}

		foreach ($warnings as $warning) {
			if (is_array($warning) === true
				&& ($warning['kind'] ?? null) === self::INCOMPLETE_WARNING_KIND
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The token set ids a stored per-group mapping points at.
	 *
	 * A corrupt appconfig value degrades to an empty list rather than throwing:
	 * it must not take the admin panel down, and it must not widen the picker
	 * either.
	 *
	 * @param string $rawMapping The stored `group_token_sets` JSON.
	 *
	 * @return array<int, string> The mapped token set ids.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function mappedIds(string $rawMapping): array {
		$decoded = json_decode($rawMapping, true);
		if (is_array($decoded) === false) {
			return [];
		}

		$ids = [];
		foreach ($decoded as $entry) {
			if (is_array($entry) === true && is_string($entry['tokenSet'] ?? null) === true) {
				$ids[] = $entry['tokenSet'];
			}
		}

		return $ids;
	}
}
