<?php

/**
 * Thematiq repair step: apply the configured branding package after an upgrade.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Repair;

use OCA\Thematiq\Service\ConfigSourceService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Applies `thematiq.config_source` after every upgrade, when it changed.
 * Never throws: a failing package is recorded and the upgrade goes on.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ApplyConfigSource implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param ConfigSourceService $source The configuration source service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		private readonly ConfigSourceService $source,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step name shown during the upgrade.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function getName(): string {
		return 'Apply the thematiq branding package from config.php';
	}//end getName()

	/**
	 * Run the step.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			$result = $this->source->applyIfChanged();
		} catch (\Throwable $e) {
			$this->logger->error('thematiq: applying the branding package failed: ' . $e->getMessage(), ['exception' => $e]);
			$output->warning('The thematiq branding package could not be applied: ' . $e->getMessage());

			return;
		}

		if ($result['status'] === 'failed') {
			$output->warning('The thematiq branding package was not applied; see Settings > Administration > Theming.');
			return;
		}

		$output->info('thematiq branding package: ' . $result['status']);
	}//end run()
}//end class
