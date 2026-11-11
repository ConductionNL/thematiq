<?php

/**
 * NL Design Generate Dark Variants Command.
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
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\DarkPaletteService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ thematiq:generate-dark-variants` — generate static dark-mode CSS
 * variants for eligible NL Design token sets (build/install-time
 * generation, never per request — see the `dark-mode` spec).
 *
 * Exit code is non-zero ONLY on a write failure; ineligible/fresh/missing
 * skips are not failures, so a full-fleet run over sets that intentionally
 * skip (`none`, `high-contrast`) still exits 0.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */
class GenerateDarkVariants extends Command {

	/**
	 * The dark palette derivation/generation service.
	 *
	 * @var DarkPaletteService
	 */
	private DarkPaletteService $darkPalette;

	/**
	 * Constructor.
	 *
	 * @param DarkPaletteService $darkPalette The dark palette service.
	 */
	public function __construct(DarkPaletteService $darkPalette) {
		parent::__construct();
		$this->darkPalette = $darkPalette;
	}//end __construct()

	/**
	 * Configure the command name, description and options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'thematiq:generate-dark-variants')
			->setDescription('Generate static dark-mode CSS variants (css/tokens/dark/{id}.css) for eligible NL Design token sets.')
			->addOption(
				name: 'set',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Generate only this token set id (default: every discovered set)'
			)
			->addOption(
				name: 'force',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Regenerate even when the existing file is already fresh'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int The exit code — non-zero only on a write failure.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$force = ($input->getOption('force') !== false);
		$failed = false;
		foreach ($this->setIds(input: $input) as $id) {
			$outcome = new DarkVariantOutcome(setId: $id, result: $this->darkPalette->generateAndWrite(setId: $id, force: $force));
			$this->reportResult(output: $output, outcome: $outcome);

			if ($outcome->writeFailed() === true) {
				$failed = true;
			}
		}

		if ($failed === true) {
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}//end execute()

	/**
	 * The token set ids to generate: every discovered set, or only the one `--set` names.
	 *
	 * @param InputInterface $input The console input.
	 *
	 * @return array<int, string> The set ids.
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function setIds(InputInterface $input): array {
		$onlySet = $input->getOption('set');
		$ids = $this->darkPalette->discoverAllSetIds();
		if (is_string($onlySet) === true && $onlySet !== '') {
			$ids = [$onlySet];
		}

		return $ids;
	}//end setIds()

	/**
	 * Report one set's generation result to the console.
	 *
	 * @param OutputInterface $output The console output.
	 * @param DarkVariantOutcome $outcome The set id and its generation result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function reportResult(OutputInterface $output, DarkVariantOutcome $outcome): void {
		$this->reportOutcomeLine(output: $output, outcome: $outcome);

		foreach ($outcome->result['warnings'] as $warning) {
			$ratio = ($warning['ratio'] ?? 'unevaluated');
			$output->writeln('  <comment>contrast warning</comment>: ' . $warning['pair'] . ' = ' . $ratio . ' (needs ' . $warning['threshold'] . ':1)');
		}
	}//end reportResult()

	/**
	 * Print the written, write-failed or skipped line for one set.
	 *
	 * @param OutputInterface $output The console output.
	 * @param DarkVariantOutcome $outcome The set id and its generation result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function reportOutcomeLine(OutputInterface $output, DarkVariantOutcome $outcome): void {
		if ($outcome->result['written'] === true) {
			$output->writeln('<info>' . $outcome->setId . '</info>: written');

			return;
		}

		if ($outcome->writeFailed() === true) {
			$output->writeln('<error>' . $outcome->setId . '</error>: write failed');

			return;
		}

		$output->writeln($outcome->setId . ': skipped (' . $outcome->result['reason'] . ')');
	}//end reportOutcomeLine()
}//end class
