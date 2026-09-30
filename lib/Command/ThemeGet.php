<?php

/**
 * Thematiq ThemeGet command.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Command
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-cli/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\GroupThemingService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ nldesign:theme:get`: the active instance-wide token set and the group mappings in priority order.
 *
 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-list-and-read-token-sets
 */
class ThemeGet extends Command {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $active The active set.
	 * @param GroupThemingService   $groups The group mappings.
	 */
	public function __construct(
		private readonly ActiveTokenSetService $active,
		private readonly GroupThemingService $groups,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-list-and-read-token-sets
	 */
	protected function configure(): void {
		$this->setName(name: 'nldesign:theme:get')
			->setDescription('Print the active token set and every group mapping, highest priority first.');
	}//end configure()

	/**
	 * Print the active set, then one line per mapping.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int Always success.
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-list-and-read-token-sets
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('Active token set: ' . $this->active->getActive());
		$mapping = $this->groups->getMapping();
		if ($mapping === []) {
			$output->writeln('No group mappings.');
			return Command::SUCCESS;
		}

		$output->writeln('Group mappings, highest priority first:');
		foreach ($mapping as $position => $entry) {
			$output->writeln(sprintf('%d. %s  %s', ($position + 1), $entry['group'], $entry['tokenSet']));
		}

		return Command::SUCCESS;
	}//end execute()
}//end class
