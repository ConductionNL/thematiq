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
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\TokenSetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ thematiq:theme:set <token-set> [--sync-core] [--dry-run]`: switch the active token set
 * through the same validation and audit as the Design token set dropdown, actor `cli`.
 *
 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-switch-the-active-token-set
 */
class ThemeSet extends Command {

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $active The one write path for the active set.
	 * @param TokenSetService $tokenSets Validates the id.
	 * @param ScheduledCoreThemingSync $coreSync Applies a set's theming block to core theming.
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
		$this->setName(name: 'thematiq:theme:set')
			->setDescription('Make a token set the active instance-wide set.')
			->addArgument('token-set', InputArgument::REQUIRED, 'The token set id, as thematiq:theme:list prints it.')
			->addOption('sync-core', null, InputOption::VALUE_NONE, 'Also apply the set\'s primary colour, background and logo to Nextcloud theming.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and print what would change, without writing.');
	}//end configure()

	/**
	 * Validate, switch, and optionally sync core theming.
	 *
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int Success, or failure for an unknown id.
	 *
	 * @spec openspec/specs/theme-cli/spec.md#requirement-operators-switch-the-active-token-set
	 * @spec openspec/specs/theme-cli/spec.md#requirement-core-theming-is-changed-only-on-request
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$target = (string)$input->getArgument('token-set');
		if ($this->reportUnknown(target: $target, output: $output) === true) {
			return Command::FAILURE;
		}

		$switch = new ThemeSwitch(from: $this->active->getActive(), to: $target);
		if ((bool)$input->getOption('dry-run') === true) {
			return $this->preview(switch: $switch, output: $output);
		}

		if ($this->switchUnlessActive(switch: $switch, output: $output) === false) {
			return Command::FAILURE;
		}

		$this->reportCore(input: $input, output: $output);

		return Command::SUCCESS;
	}//end execute()

	/**
	 * Report a token set that does not exist.
	 *
	 * @param string $target The token set id asked for.
	 * @param OutputInterface $output The output.
	 *
	 * @return bool True when the set is unknown and the run stops.
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function reportUnknown(string $target, OutputInterface $output): bool {
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $target) === true) {
			return false;
		}

		$output->writeln('<error>Unknown token set: ' . $target . '. Nothing was changed.</error>');

		return true;
	}//end reportUnknown()

	/**
	 * Print what a dry run would change.
	 *
	 * @param ThemeSwitch $switch The active and the asked-for set.
	 * @param OutputInterface $output The output.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function preview(ThemeSwitch $switch, OutputInterface $output): int {
		$output->writeln(sprintf('Dry run: would switch the active token set from %s to %s.', $switch->from, $switch->to));

		return Command::SUCCESS;
	}//end preview()

	/**
	 * Switch to the asked-for set unless it is already active.
	 *
	 * @param ThemeSwitch $switch The active and the asked-for set.
	 * @param OutputInterface $output The output.
	 *
	 * @return bool False when a restricted set was refused.
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function switchUnlessActive(ThemeSwitch $switch, OutputInterface $output): bool {
		if ($switch->isNoOp() === true) {
			$output->writeln($switch->to . ' is already the active token set. Nothing was changed.');
			return true;
		}

		try {
			$this->switchTo(target: $switch->to, output: $output);
		} catch (RestrictedSetNotConfirmedException $e) {
			$this->reportRestricted(target: $switch->to, output: $output);
			return false;
		}

		return true;
	}//end switchUnlessActive()

	/**
	 * Report a restricted set the organisation has not been confirmed for.
	 *
	 * @param string $target The token set id.
	 * @param OutputInterface $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function reportRestricted(string $target, OutputInterface $output): void {
		$output->writeln(
			'<error>' . $target . ' is the house style of another organisation. An administrator first confirms in the '
			. 'Thematiq admin settings that your organisation may use it. Nothing was changed.</error>'
		);
	}//end reportRestricted()

	/**
	 * Switch the active token set and print the switch.
	 *
	 * @param string $target The token set id.
	 * @param OutputInterface $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function switchTo(string $target, OutputInterface $output): void {
		$previous = $this->active->switchTo(tokenSet: $target, auditContext: ['actor' => 'cli']);
		$output->writeln(sprintf('Switched the active token set from %s to %s.', $previous, $target));
	}//end switchTo()

	/**
	 * Report Nextcloud theming: the hint without --sync-core, the sync otherwise.
	 *
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function reportCore(InputInterface $input, OutputInterface $output): void {
		if ((bool)$input->getOption('sync-core') === false) {
			$output->writeln('Nextcloud theming is unchanged. Pass --sync-core to apply the set\'s colours and logo.');
			return;
		}

		$this->syncCore(target: (string)$input->getArgument('token-set'), output: $output);
	}//end reportCore()

	/**
	 * Apply the set's theming values to Nextcloud and print what was applied.
	 *
	 * @param string $target The token set id.
	 * @param OutputInterface $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-cli/spec.md
	 */
	private function syncCore(string $target, OutputInterface $output): void {
		$applied = $this->coreSync->sync(tokenSetId: $target);
		if ($applied === [] && $this->coreSync->themingParams(tokenSetId: $target) !== []) {
			$output->writeln('Nextcloud theming is unchanged: the theming values of ' . $target . ' did not validate, so none were applied.');
			return;
		}

		if ($applied === []) {
			$output->writeln('Nextcloud theming is unchanged: ' . $target . ' has no theming values to apply.');
			return;
		}

		$output->writeln('Applied to Nextcloud theming: ' . implode(', ', $applied) . '.');
	}//end syncCore()
}//end class
