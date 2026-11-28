<?php

/**
 * Thematiq Warm Token Set Audits.
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
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\Service\TokenSetService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Lists the token set catalogue once after an install or upgrade, so the
 * contrast, vocabulary and typeface audits of the shipped sets run here and
 * not on the first admin page that lists the sets.
 *
 * The audits' result is keyed on the app version and the shipped files, so
 * every install and upgrade made the first Theming settings page (or the
 * first-time setup wizard) audit all shipped sets: ~3 s. Listing the
 * catalogue stores the warnings in AuditWarningsStore (app data, shared by the
 * command line and the web server, unlike the local memory cache), so the
 * first web read finds them there.
 *
 * Never fails the upgrade: a failure only means the first page audits again.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
 */
class WarmTokenSetAudits implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSets The catalogue whose listing runs and stores the audits.
	 * @param LoggerInterface $logger The Nextcloud log.
	 */
	public function __construct(
		private readonly TokenSetService $tokenSets,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name in `occ upgrade` output.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function getName(): string {
		return 'Audit the shipped thematiq token sets ahead of the first admin page';
	}//end getName()

	/**
	 * List the catalogue once; never fail the upgrade.
	 *
	 * @param IOutput $output The upgrade output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown
	 */
	public function run(IOutput $output): void {
		try {
			$count = count($this->tokenSets->getAvailableTokenSets());
		} catch (Throwable $e) {
			$this->logger->warning('Thematiq: the token set audits could not be run ahead of the admin page', ['exception' => $e->getMessage()]);
			$output->warning('WarmTokenSetAudits: not audited; the first admin page that lists the sets audits them.');
			return;
		}

		$output->info('WarmTokenSetAudits: audited ' . $count . ' token sets.');
	}//end run()
}//end class
