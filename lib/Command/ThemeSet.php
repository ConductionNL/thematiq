<?php

/**
 * Thematiq ThemeSet command.
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
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\TokenSetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ nldesign:theme:set <token-set> [--sync-core] [--dry-run]`: switch the active token set
 * through the same validation and audit as the Design token set dropdown, actor `cli`.
 *
 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-switch-the-active-token-set
 */
class ThemeSet extends Command {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService    $active    The one write path for the active set.
	 * @param TokenSetService          $tokenSets Validates the id.
	 * @param ScheduledCoreThemingSync $coreSync  Applies a set's theming block to core theming.
	 */
	public function __construct(
		private readonly ActiveTokenSetService $active,
		private readonly TokenSetService $tokenSets,
		private readonly ScheduledCoreThemingSync $coreSync,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-switch-the-active-token-set
	 */
	protected function configure(): void {
		$this->setName(name: 'nldesign:theme:set')
			->setDescription('Make a token set the active instance-wide set.')
			->addArgument('token-set', InputArgument::REQUIRED, 'The token set id, as nldesign:theme:list prints it.')
			->addOption('sync-core', null, InputOption::VALUE_NONE, 'Also apply the set\'s primary colour, background and logo to Nextcloud theming.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and print what would change, without writing.');
	}//end configure()

	/**
	 * Validate, switch, and optionally sync core theming.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int Success, or failure for an unknown id.
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-switch-the-active-token-set
	 * @spec openspec/specs/theme-cli/spec.md#requirement-core-theming-is-changed-only-on-request
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$target = (string)$input->getArgument('token-set');
		$syncCore = (bool)$input->getOption('sync-core');
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $target) === false) {
			$output->writeln('<error>Unknown token set: ' . $target . '. Nothing was changed.</error>');
			return Command::FAILURE;
		}

		$current = $this->active->getActive();
		if ((bool)$input->getOption('dry-run') === true) {
			$output->writeln(sprintf('Dry run: would switch the active token set from %s to %s.', $current, $target));
			return Command::SUCCESS;
		}

		if ($current === $target) {
			$output->writeln($target . ' is already the active token set. Nothing was changed.');
		}

		if ($current !== $target) {
			$this->switchTo(target: $target, output: $output);
		}

		$this->reportCore(target: $target, syncCore: $syncCore, output: $output);

		return Command::SUCCESS;
	}//end execute()

	/**
	 * Switch the active set, audited with actor `cli`.
	 *
	 * @param string          $target The set id.
	 * @param OutputInterface $output The output.
	 *
	 * @return void
	 */
	private function switchTo(string $target, OutputInterface $output): void {
		$previous = $this->active->switchTo(tokenSet: $target, auditContext: ['actor' => 'cli']);
		$output->writeln(sprintf('Switched the active token set from %s to %s.', $previous, $target));
	}//end switchTo()

	/**
	 * Apply core theming when asked, and say which of the two happened.
	 *
	 * @param string          $target   The set id.
	 * @param boolean         $syncCore Whether --sync-core was given.
	 * @param OutputInterface $output   The output.
	 *
	 * @return void
	 */
	private function reportCore(string $target, bool $syncCore, OutputInterface $output): void {
		if ($syncCore === false) {
			$output->writeln('Nextcloud theming is unchanged. Pass --sync-core to apply the set\'s colours and logo.');
			return;
		}

		$applied = $this->coreSync->sync(tokenSetId: $target);
		if ($applied === []) {
			$output->writeln('Nextcloud theming is unchanged: ' . $target . ' has no theming values to apply.');
			return;
		}

		$output->writeln('Applied to Nextcloud theming: ' . implode(', ', $applied) . '.');
	}//end reportCore()
}//end class
