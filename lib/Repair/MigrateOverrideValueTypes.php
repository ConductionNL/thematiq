<?php

/**
 * Thematiq Migrate Override Value Types Repair Step
 *
 * Writes every custom overrides file again in the shape typed token values
 * introduced, so an upgrade lands the change and not the next save:
 *   - each motion override (`--animation-quick`, `--animation-slow`) also
 *     declares its thematiq twin (`--nldesign-animation-quick`, `-slow`), which
 *     thematiq's own transitions and any set without `overrides.css` read;
 *   - each brand-layer colour override gets a declaration in both dark scopes,
 *     so a user who follows a dark system and a user who chose the dark theme
 *     see the same override.
 *
 * Values are kept as they are, including one the new type check would refuse:
 * an upgrade never drops what an administrator set. The editor shows the
 * refusal on the next save, naming the token.
 *
 * SAFETY. Idempotent (a file already in today's shape is not touched) and
 * non-throwing: an unwritable `css/` directory is logged and reported, and the
 * upgrade goes on.
 *
 * @category Repair
 * @package  OCA\Thematiq\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-2.5
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\Service\CustomOverridesService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Bring every overrides file to the typed-values shape, keeping its values.
 *
 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-2.5
 */
class MigrateOverrideValueTypes implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param CustomOverridesService $overrides The overrides files.
	 * @param LoggerInterface        $logger    Logger for a file that cannot be written.
	 */
	public function __construct(
		private readonly CustomOverridesService $overrides,
		private readonly LoggerInterface $logger,
	) {

	}//end __construct()

	/**
	 * The repair step name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-2.5
	 */
	public function getName(): string {
		return 'Add motion twins and dark values to thematiq token overrides';
	}//end getName()

	/**
	 * Rewrite each overrides file that is not in today's shape.
	 *
	 * @param IOutput $output The output interface for progress reporting.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/authoring-token-value-types/tasks.md#task-2.5
	 */
	public function run(IOutput $output): void {
		try {
			$changed = $this->overrides->rewriteAll();
		} catch (Throwable $e) {
			$this->logger->warning(
				'Thematiq: token overrides were not brought to the typed-values shape',
				['exception' => $e->getMessage()]
			);
			$output->warning('MigrateOverrideValueTypes: an overrides file could not be written; the next save in the token editor writes it.');
			return;
		}

		if ($changed === []) {
			$output->info('MigrateOverrideValueTypes: every overrides file is up to date; nothing to do.');
			return;
		}

		$output->info('MigrateOverrideValueTypes: rewrote ' . implode(', ', $changed) . '.');
	}//end run()
}//end class
