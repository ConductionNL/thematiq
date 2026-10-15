<?php

/**
 * Thematiq Record Restricted Sets Awaiting Confirmation.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Repair
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\Service\TokenSetLicenceService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * After an upgrade that marks shipped sets as restricted, an instance may
 * have one of them applied without a confirmation. This step changes nothing
 * a user sees: the set stays applied wherever it is. It records, per set,
 * that it needs a confirmation and since when, so the admin settings show the
 * notice with the confirm action, and it writes one log warning per set.
 *
 * Running it again records nothing new: a set already recorded keeps its date.
 *
 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
 */
class RecordRestrictedSetsAwaitingConfirmation implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param TokenSetLicenceService $licences Finds the applied, unconfirmed restricted sets and keeps the record.
	 * @param LoggerInterface $logger The Nextcloud log.
	 */
	public function __construct(
		private readonly TokenSetLicenceService $licences,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name in `occ upgrade` output.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
	 */
	public function getName(): string {
		return 'Record restricted thematiq token sets that are applied without a confirmation';
	}//end getName()

	/**
	 * Record the sets; never un-apply one, never fail the upgrade.
	 *
	 * @param IOutput $output The upgrade output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-an-upgrade-records-every-restricted-set-that-is-already-applied
	 */
	public function run(IOutput $output): void {
		try {
			$recorded = $this->licences->recordAwaitingConfirmation();
		} catch (Throwable $e) {
			$this->logger->warning('Thematiq: restricted token sets in use could not be recorded', ['exception' => $e->getMessage()]);
			$output->warning('RecordRestrictedSetsAwaitingConfirmation: nothing recorded; the admin settings still list the sets.');
			return;
		}

		foreach ($recorded as $tokenSetId) {
			$this->logger->warning(
				'Thematiq: token set {set} is applied, and its licence limits use to its owner. '
				. 'It stays applied. An administrator confirms it in the Thematiq admin settings.',
				['set' => $tokenSetId]
			);
		}

		if ($recorded === []) {
			$output->info('RecordRestrictedSetsAwaitingConfirmation: no restricted set is applied without a confirmation.');
			return;
		}

		$output->warning('RecordRestrictedSetsAwaitingConfirmation: applied without a confirmation, kept applied: ' . implode(', ', $recorded) . '.');
	}//end run()
}//end class
