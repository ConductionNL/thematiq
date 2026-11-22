<?php

/**
 * Contract of TokenSetLicenceService
 *
 * The public surface of TokenSetLicenceService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Thematiq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/thematiq
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Kernel\Contracts;

use InvalidArgumentException;

/**
 * Contract of TokenSetLicenceService.
 *
 * @category Kernel
 * @package  OCA\Thematiq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface TokenSetLicenceServiceInterface {

	/**
	 * Restricted sets that are applied and that nobody confirmed. That only
	 * happens to an instance that applied the set before it was marked
	 * restricted; the set stays applied and the settings page asks for the
	 * confirmation.
	 *
	 * `since` is when an upgrade recorded the set, or empty.
	 *
	 * @return list<array{tokenSet: string, appliedIn: list<array{kind: string, id: string}>, since: string}> One entry per set.
	 *
	 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-set-applied-before-it-was-restricted-stays-applied-and-asks-for-confirmation
	 */
	public function awaitingConfirmation(): array;

	/**
	 * Record that this organisation is the owner of a restricted set, or has its permission.
	 *
	 * @param string $tokenSetId The restricted set.
	 * @param string $uid The confirming administrator.
	 *
	 * @return array{uid: string, at: string} The stored confirmation.
	 *
	 * @throws InvalidArgumentException When the set does not exist or is not restricted, or no administrator is named.
	 *
	 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-an-administrator-confirms-a-restricted-set
	 */
	public function confirm(string $tokenSetId, string $uid): array;

	/**
	 * A set's licence as the settings page shows it.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return array<string, mixed>|null The declaration, the full text, the confirmation and where the
	 *                                   set is applied; null when the set declares no licence.
	 *
	 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	public function describe(string $tokenSetId): ?array;

	/**
	 * Record, for every restricted set that is applied without a confirmation,
	 * that it needs one and since when. A set already recorded keeps its date;
	 * a set that no longer awaits is dropped from the record. Nothing is
	 * un-applied.
	 *
	 * @return list<string> The sets recorded by this call (new ones only).
	 *
	 * @spec openspec/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
	 */
	public function recordAwaitingConfirmation(): array;

	/**
	 * Every restricted set the settings page lists: its name, licence and confirmation.
	 *
	 * @return list<array{id: string, name: string, licence: array<string, mixed>, confirmation: array{uid: string, at: string}|null}>
	 *                                                                                                                                 Sorted by name.
	 *
	 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	public function restrictedSets(): array;

	/**
	 * Take a confirmation back. Refused while the set is applied anywhere:
	 * the administrator first chooses another set there, so nobody's screen
	 * changes as a side effect of a checkbox.
	 *
	 * @param string $tokenSetId The restricted set.
	 * @param string $uid The administrator withdrawing.
	 *
	 * @return void
	 *
	 * @throws \OCA\Thematiq\Service\Exception\RestrictedSetInUseException When the set is still applied; it names where.
	 *
	 * @spec openspec/changes/archive/2026-11-22-token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	public function withdraw(string $tokenSetId, string $uid): void;
}//end interface
