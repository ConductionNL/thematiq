<?php

/**
 * NL Design Configuration Bundle Export Command.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\ConfigBundleService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `occ thematiq:config:export [file]` — writes the complete nldesign
 * configuration bundle (`config-portability` spec) to a file, or stdout
 * when no file is given, for OTAP (dev/test/acceptatie/productie) promotion
 * pipelines.
 *
 * Reuses {@see ConfigBundleService::export()} exclusively — no second
 * serialization path — so the output is byte-for-byte identical to the
 * `configBundle#export` HTTP endpoint.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
class ConfigExport extends ConfigBundleCommand {

	/**
	 * Configure the command name, description and arguments.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'thematiq:config:export')
			->setDescription(
				'Export the complete NL Design configuration (token set, toggles, per-app '
				. 'exclusions, overrides, custom token sets, email footer, custom-font metadata, '
				. 'upstream-freshness toggle) as a single JSON bundle, for OTAP promotion.'
			)
			->addArgument(
				name: 'file',
				mode: InputArgument::OPTIONAL,
				description: 'Write the bundle to this file path instead of stdout'
			)
			->addOption(
				name: 'package',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Write a branding package (bundle.json plus the font files) to this directory'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int The exit code — 0 on success, non-zero on generation/write failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$packageDir = (string)$input->getOption('package');
		if ($packageDir !== '') {
			return $this->writePackage(output: $output, dir: $packageDir);
		}

		$json = $this->encodedBundle(output: $output);
		if ($json === null) {
			return Command::FAILURE;
		}

		return $this->deliver(
			output: $output,
			bundle: new OutputFile(path: (string)$input->getArgument('file'), content: $json)
		);
	}//end execute()

	/**
	 * Export the bundle and encode it as pretty JSON, reporting a failure.
	 *
	 * @param OutputInterface $output The console output.
	 *
	 * @return string|null The bundle JSON, or null when the export or the encoding failed.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function encodedBundle(OutputInterface $output): ?string {
		try {
			$bundle = $this->service->export();
		} catch (Throwable $e) {
			$output->writeln('<error>Configuration export failed: ' . $e->getMessage() . '</error>');

			return null;
		}

		$json = json_encode($bundle, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		if ($json === false) {
			$output->writeln('<error>Configuration export failed: could not encode the bundle as JSON.</error>');

			return null;
		}

		return $json;
	}//end encodedBundle()

	/**
	 * Write the bundle JSON to its file, or to stdout when no file was given.
	 *
	 * @param OutputInterface $output The console output.
	 * @param OutputFile $bundle The destination and the bundle JSON.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function deliver(OutputInterface $output, OutputFile $bundle): int {
		if ($bundle->toFile() === true) {
			return $this->writeToFile(output: $output, file: $bundle);
		}

		$output->writeln($bundle->content);

		return Command::SUCCESS;
	}//end deliver()

	/**
	 * Write a branding package directory, fonts included.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $dir The target directory.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function writePackage(OutputInterface $output, string $dir): int {
		try {
			$written = $this->packages->export(dir: $dir);
		} catch (Throwable $e) {
			$output->writeln('<error>Branding package export failed: ' . $e->getMessage() . '</error>');

			return Command::FAILURE;
		}

		$output->writeln('<info>Branding package written to ' . $dir . ' (' . count($written) . ' files).</info>');

		return Command::SUCCESS;
	}//end writePackage()

	/**
	 * Write the exported bundle to a file path, reporting success/failure.
	 *
	 * @param OutputInterface $output The console output.
	 * @param OutputFile $file The destination file path and the bundle JSON.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function writeToFile(OutputInterface $output, OutputFile $file): int {
		if (file_put_contents($file->path, $file->content . PHP_EOL) === false) {
			$output->writeln('<error>Could not write the bundle to ' . $file->path . '</error>');

			return Command::FAILURE;
		}

		$output->writeln('<info>Configuration bundle written to ' . $file->path . '</info>');

		return Command::SUCCESS;
	}//end writeToFile()
}//end class
