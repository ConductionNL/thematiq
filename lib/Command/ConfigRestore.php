<?php

/**
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
 * @spec openspec/specs/theme-versions/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\ThemeVersionRestoreService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ thematiq:config:restore <id> [--dry-run]`: restore a kept version.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */
class ConfigRestore extends Command {

	/**
	 * Constructor.
	 *
	 * @param ThemeVersionRestoreService $restorer Previews and restores a version.
	 */
	public function __construct(
		private readonly ThemeVersionRestoreService $restorer,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'thematiq:config:restore')
			->setDescription('Restore a kept configuration version through the validated bundle import.')
			->addArgument(name: 'id', mode: InputArgument::REQUIRED, description: 'The version id (see thematiq:config:versions)')
			->addOption(name: 'dry-run', shortcut: null, mode: InputOption::VALUE_NONE, description: 'Print the changes, write nothing');
	}//end configure()

	/**
	 * Preview or restore.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int The exit code: 0 applied or previewed, 1 unknown or refused.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (string)$input->getArgument('id');
		$dryRun = ($input->getOption('dry-run') === true);

		$result = $this->previewOrRestore(id: $id, dryRun: $dryRun);

		if ($result === null) {
			$output->writeln('<error>Unknown version: ' . $id . '</error>');
			return Command::FAILURE;
		}

		$this->printChanges(output: $output, result: $result);

		if ($result['valid'] === false || ($dryRun === false && ($result['applied'] ?? false) !== true)) {
			$output->writeln('<error>The version does not validate today; nothing was applied:</error>');
			foreach ($result['errors'] as $error) {
				$output->writeln('  - [' . ($error['section'] ?? 'unknown') . '] ' . ($error['message'] ?? json_encode($error)));
			}

			return Command::FAILURE;
		}

		$done = '<info>Version ' . $id . ' restored.</info>';
		if ($dryRun === true) {
			$done = '<info>Dry run: nothing was written.</info>';
		}

		$output->writeln($done);

		return Command::SUCCESS;
	}//end execute()

	/**
	 * Preview on a dry run, restore otherwise.
	 *
	 * @param string $id     The version id.
	 * @param bool   $dryRun Whether to write nothing.
	 *
	 * @return array<string, mixed>|null The result, or null for an unknown id.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - mirrors the --dry-run option.
	 */
	private function previewOrRestore(string $id, bool $dryRun): ?array {
		if ($dryRun === true) {
			return $this->restorer->preview(id: $id);
		}

		return $this->restorer->restore(id: $id);
	}//end previewOrRestore()

	/**
	 * Print the changes a restore makes.
	 *
	 * @param OutputInterface      $output The output.
	 * @param array<string, mixed> $result The preview or restore result.
	 *
	 * @return void
	 */
	private function printChanges(OutputInterface $output, array $result): void {
		foreach ($result['changes'] as $change) {
			$from = json_encode($change['from'], JSON_UNESCAPED_SLASHES);
			$to = json_encode($change['to'], JSON_UNESCAPED_SLASHES);
			$output->writeln('  ' . $change['field'] . ': ' . $from . ' -> ' . $to);
		}

		foreach ($result['customTokenSets']['add'] as $setId) {
			$output->writeln('  custom token set added: ' . $setId);
		}

		foreach ($result['customTokenSets']['remove'] as $setId) {
			$output->writeln('  custom token set removed: ' . $setId);
		}

		foreach ($result['missingFonts'] as $font) {
			$output->writeln('  ' . $font['role'] . ' font no longer uploaded, stays on the default: ' . $font['name']);
		}
	}//end printChanges()
}//end class
