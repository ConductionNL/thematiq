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
		$onlySet = $input->getOption('set');

		$ids = $this->darkPalette->discoverAllSetIds();
		if (is_string($onlySet) === true && $onlySet !== '') {
			$ids = [$onlySet];
		}

		$failed = false;
		foreach ($ids as $id) {
			$result = $this->darkPalette->generateAndWrite(setId: $id, force: $force);
			$this->reportResult(output: $output, setId: $id, result: $result);

			if ($result['reason'] === 'write-failed') {
				$failed = true;
			}
		}

		if ($failed === true) {
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}//end execute()

	/**
	 * Report one set's generation result to the console.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $setId The token set id.
	 * @param array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>} $result The generation result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function reportResult(OutputInterface $output, string $setId, array $result): void {
		$this->reportOutcomeLine(output: $output, setId: $setId, result: $result);

		foreach ($result['warnings'] as $warning) {
			$ratio = ($warning['ratio'] ?? 'unevaluated');
			$output->writeln('  <comment>contrast warning</comment>: ' . $warning['pair'] . ' = ' . $ratio . ' (needs ' . $warning['threshold'] . ':1)');
		}
	}//end reportResult()

	/**
	 * Write the single written/skipped/failed outcome line for one set.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $setId The token set id.
	 * @param array{written: bool, skipped: bool, reason: string, warnings: array<int, array<string, mixed>>} $result The generation result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dark-mode/spec.md
	 */
	private function reportOutcomeLine(OutputInterface $output, string $setId, array $result): void {
		if ($result['written'] === true) {
			$output->writeln('<info>' . $setId . '</info>: written');

			return;
		}

		if ($result['reason'] === 'write-failed') {
			$output->writeln('<error>' . $setId . '</error>: write failed');

			return;
		}

		$output->writeln($setId . ': skipped (' . $result['reason'] . ')');
	}//end reportOutcomeLine()
}//end class
