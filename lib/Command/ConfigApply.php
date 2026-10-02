<?php

/**
 * Thematiq occ command: apply the branding package named in config.php.
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
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\ConfigSourceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ thematiq:config:apply [--force]`: applies the package named in
 * `thematiq.config_source` when it changed. Exits non-zero when the package
 * fails, when no source is set, or when another apply holds the lock, so a
 * deployment pipeline can stop on it.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ConfigApply extends Command {

	/**
	 * Constructor.
	 *
	 * @param ConfigSourceService $source The configuration source service.
	 */
	public function __construct(
		private readonly ConfigSourceService $source,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'thematiq:config:apply')
			->setDescription('Apply the branding package named in thematiq.config_source when it changed.')
			->addOption(
				name: 'force',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Apply even when the package did not change since the last apply'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int 0 when applied or unchanged, 1 otherwise.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$result = $this->source->applyIfChanged(force: ($input->getOption('force') === true));

		switch ($result['status']) {
			case 'applied':
				$output->writeln('<info>Branding package applied (' . ($result['revision'] ?? $result['hash']) . ').</info>');
				return Command::SUCCESS;
			case 'unchanged':
				$output->writeln('<info>The branding package did not change. Nothing to do.</info>');
				return Command::SUCCESS;
			case 'unconfigured':
				$output->writeln('<error>No branding package is configured. Set thematiq.config_source in config.php.</error>');
				return Command::FAILURE;
			case 'busy':
				$output->writeln('<error>Another apply is running. Try again in a moment.</error>');
				return Command::FAILURE;
		}

		$output->writeln('<error>The branding package was not applied, nothing changed:</error>');
		foreach (($result['errors'] ?? []) as $error) {
			$output->writeln('  - [' . ($error['section'] ?? 'unknown') . '] ' . ($error['message'] ?? 'Unknown error.'));
		}

		return Command::FAILURE;
	}//end execute()
}//end class
