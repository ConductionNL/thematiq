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
	 * The line and exit code for each status that settles the run without applying,
	 * keyed by status.
	 *
	 * @var array<string, array{0: string, 1: int}>
	 */
	private const SETTLED = [
		'unchanged' => ['<info>The branding package did not change. Nothing to do.</info>', Command::SUCCESS],
		'unconfigured' => [
			'<error>No branding package is configured. Set thematiq.config_source in config.php.</error>',
			Command::FAILURE,
		],
		'busy' => ['<error>Another apply is running. Try again in a moment.</error>', Command::FAILURE],
	];

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

		if ($result['status'] === 'applied') {
			$output->writeln('<info>Branding package applied (' . ($result['revision'] ?? $result['hash']) . ').</info>');
			return Command::SUCCESS;
		}

		if (isset(self::SETTLED[$result['status']]) === true) {
			$output->writeln(self::SETTLED[$result['status']][0]);
			return self::SETTLED[$result['status']][1];
		}

		$this->printNotApplied(output: $output, errors: ($result['errors'] ?? []));

		return Command::FAILURE;
	}//end execute()

	/**
	 * Print why the package was not applied, one line per error.
	 *
	 * @param OutputInterface $output The console output.
	 * @param array<int, array<string, mixed>> $errors The per-section errors.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function printNotApplied(OutputInterface $output, array $errors): void {
		$output->writeln('<error>The branding package was not applied, nothing changed:</error>');
		foreach ($errors as $error) {
			$output->writeln('  - [' . ($error['section'] ?? 'unknown') . '] ' . ($error['message'] ?? 'Unknown error.'));
		}
	}//end printNotApplied()
}//end class
