<?php

/**
 * NL Design Generate Dark Variants Repair Step.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Migration
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Migration;

use OCA\Thematiq\Kernel\Contracts\DarkPaletteServiceInterface;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Post-migration repair step: regenerate any missing or stale
 * `css/tokens/dark/{id}.css` file on install/upgrade.
 *
 * Degrades gracefully — a read-only app directory (some deployments ship a
 * read-only app tree) logs one warning and completes without error; theming
 * simply continues light-only for the sets that could not be (re)generated.
 * Never regenerates a file whose source hash is unchanged (idempotent).
 *
 * @spec openspec/specs/dark-mode/spec.md
 */
class GenerateDarkVariantsRepairStep implements IRepairStep {

	/**
	 * The dark palette derivation/generation service.
	 *
	 * @var DarkPaletteServiceInterface
	 */
	private DarkPaletteServiceInterface $darkPalette;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param DarkPaletteServiceInterface $darkPalette The dark palette service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(DarkPaletteServiceInterface $darkPalette, LoggerInterface $logger) {
		$this->darkPalette = $darkPalette;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Get the step's name.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function getName(): string {
		return 'Generate NL Design dark-mode token variants';
	}//end getName()

	/**
	 * Run the repair step: regenerate missing/stale dark variants for every
	 * discovered token set. Never throws — a write failure or unwritable
	 * directory is logged and skipped, not fatal to the migration.
	 *
	 * @param IOutput $output The migration output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	public function run(IOutput $output): void {
		$results = $this->darkPalette->generateAll(force: false);
		$written = 0;
		$notWritable = false;

		foreach ($results as $setId => $result) {
			if ($result['written'] === true) {
				$written++;
			}

			if ($result['reason'] === 'not-writable') {
				$notWritable = true;
			}

			if ($result['reason'] === 'write-failed') {
				$this->logger->warning('NL Design dark-variant generation failed for "{set}" during repair.', ['set' => $setId]);
			}
		}

		if ($notWritable === true) {
			$output->warning('css/tokens/dark/ is not writable — dark-mode variants will not be generated; theming continues light-only.');

			return;
		}

		$output->info('NL Design dark-mode variants: ' . $written . ' written.');
	}//end run()
}//end class
