<?php

/**
 * Thematiq ThemeList command.
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

use OCA\Thematiq\Service\TokenSetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ nldesign:theme:list`: every available token set with its id, name and kind.
 *
 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-list-and-read-token-sets
 */
class ThemeList extends Command {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSets The shipped and custom sets.
	 */
	public function __construct(
		private readonly TokenSetService $tokenSets,
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
		$this->setName(name: 'nldesign:theme:list')
			->setDescription('List every available token set: id, kind (shipped or custom) and name.');
	}//end configure()

	/**
	 * Print one line per set.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int Always success.
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-list-and-read-token-sets
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - Symfony's execute() signature; this command takes no input.
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			$kind = 'shipped';
			if (str_starts_with((string)$set['id'], 'custom-') === true) {
				$kind = 'custom';
			}

			$output->writeln(implode('  ', [(string)$set['id'], $kind, (string)$set['name']]));
		}

		return Command::SUCCESS;
	}//end execute()
}//end class
