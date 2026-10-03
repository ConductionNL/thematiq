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

use OCA\Thematiq\Service\BrandingPackageService;
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
class ConfigExport extends Command {

	/**
	 * The configuration bundle service.
	 *
	 * @var ConfigBundleService
	 */
	private ConfigBundleService $service;

	/**
	 * The branding package service, for `--package`.
	 *
	 * @var BrandingPackageService
	 */
	private BrandingPackageService $packages;

	/**
	 * Constructor.
	 *
	 * @param ConfigBundleService $service The configuration bundle service.
	 * @param BrandingPackageService $packages The branding package service.
	 */
	public function __construct(ConfigBundleService $service, BrandingPackageService $packages) {
		parent::__construct();
		$this->service = $service;
		$this->packages = $packages;
	}//end __construct()

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
		$packageDir = $input->getOption('package');
		if (is_string($packageDir) === true && $packageDir !== '') {
			return $this->writePackage(output: $output, dir: $packageDir);
		}

		try {
			$bundle = $this->service->export();
		} catch (Throwable $e) {
			$output->writeln('<error>Configuration export failed: ' . $e->getMessage() . '</error>');

			return Command::FAILURE;
		}

		$json = json_encode($bundle, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		if ($json === false) {
			$output->writeln('<error>Configuration export failed: could not encode the bundle as JSON.</error>');

			return Command::FAILURE;
		}

		$filePath = $input->getArgument('file');
		if (is_string($filePath) === true && $filePath !== '') {
			return $this->writeToFile(output: $output, path: $filePath, content: $json);
		}

		$output->writeln($json);

		return Command::SUCCESS;
	}//end execute()

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
	 * @param string $path The destination file path.
	 * @param string $content The bundle JSON.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function writeToFile(OutputInterface $output, string $path, string $content): int {
		if (file_put_contents($path, $content . PHP_EOL) === false) {
			$output->writeln('<error>Could not write the bundle to ' . $path . '</error>');

			return Command::FAILURE;
		}

		$output->writeln('<info>Configuration bundle written to ' . $path . '</info>');

		return Command::SUCCESS;
	}//end writeToFile()
}//end class
