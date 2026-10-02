<?php

/**
 * NL Design Configuration Bundle Import Command.
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
 * `occ nldesign:config:import <file> [--dry-run]` — validates (and, unless
 * `--dry-run`, applies) a configuration bundle produced by
 * `nldesign:config:export` or the settings-panel download, for OTAP
 * (dev/test/acceptatie/productie) promotion pipelines.
 *
 * Reuses {@see ConfigBundleService::import()} exclusively — no second
 * validation path — so the result is identical to the `configBundle#import`
 * HTTP endpoint for the same bundle. Import is all-or-nothing: ANY hard
 * validation failure prints the full per-section error listing and exits
 * non-zero with ZERO writes; `--dry-run` performs validation only (phase 1)
 * and never writes, regardless of the validation outcome.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
class ConfigImport extends Command {

	/**
	 * The configuration bundle service.
	 *
	 * @var ConfigBundleService
	 */
	private ConfigBundleService $service;

	/**
	 * The branding package service, for a package directory or ZIP.
	 *
	 * @var BrandingPackageService
	 */
	private BrandingPackageService $packages;

	/**
	 * Constructor.
	 *
	 * @param ConfigBundleService    $service  The configuration bundle service.
	 * @param BrandingPackageService $packages The branding package service.
	 */
	public function __construct(ConfigBundleService $service, BrandingPackageService $packages) {
		parent::__construct();
		$this->service = $service;
		$this->packages = $packages;
	}//end __construct()

	/**
	 * Configure the command name, description, arguments and options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'nldesign:config:import')
			->setDescription(
				'Import a complete NL Design configuration bundle (validate-everything-first, '
				. 'then write — any hard validation failure applies nothing).'
			)
			->addArgument(
				name: 'file',
				mode: InputArgument::REQUIRED,
				description: 'The bundle JSON file, or a branding package directory or ZIP'
			)
			->addOption(
				name: 'dry-run',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Validate only — print what would change, write nothing'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int 0 on success (or a valid dry-run); non-zero on an
	 *             unreadable/undecodable file or a hard validation failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$path = (string)$input->getArgument('file');
		$dryRun = ($input->getOption('dry-run') === true);

		try {
			$result = $this->runImport(output: $output, path: $path, dryRun: $dryRun);
		} catch (Throwable $e) {
			$output->writeln('<error>Configuration import failed: ' . $e->getMessage() . '</error>');

			return Command::FAILURE;
		}

		if ($result === null) {
			return Command::FAILURE;
		}

		if ($result['valid'] === false) {
			$this->printErrors(output: $output, errors: $result['errors']);

			return Command::FAILURE;
		}

		$this->printSections(output: $output, result: $result);

		return Command::SUCCESS;
	}//end execute()

	/**
	 * Import a package (directory or ZIP) or a bare bundle file.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $path The argument.
	 * @param bool $dryRun Validate only.
	 *
	 * @return array<string, mixed>|null The import result, or null when a bare bundle could not be read.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the --dry-run switch, passed through.
	 */
	private function runImport(OutputInterface $output, string $path, bool $dryRun): ?array {
		if ($this->packages->isPackage(path: $path) === true) {
			return $this->packages->import(path: $path, dryRun: $dryRun);
		}

		$bundle = $this->readBundle(output: $output, path: $path);
		if ($bundle === null) {
			return null;
		}

		return $this->service->import(bundle: $bundle, dryRun: $dryRun);
	}//end runImport()

	/**
	 * Read and JSON-decode the bundle file.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $path The bundle file path.
	 *
	 * @return array<string, mixed>|null The decoded bundle, or null on read/decode failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function readBundle(OutputInterface $output, string $path): ?array {
		if (is_readable($path) === false) {
			$output->writeln('<error>Cannot read bundle file: ' . $path . '</error>');

			return null;
		}

		$content = file_get_contents($path);
		if ($content === false) {
			$output->writeln('<error>Cannot read bundle file: ' . $path . '</error>');

			return null;
		}

		$decoded = json_decode($content, true);
		if (is_array($decoded) === false) {
			$output->writeln('<error>The bundle file does not contain valid JSON.</error>');

			return null;
		}

		return $decoded;
	}//end readBundle()

	/**
	 * Print the full-error listing for a failed (or unresolvable) import.
	 *
	 * @param OutputInterface $output The console output.
	 * @param array<int, array<string, mixed>> $errors The per-section errors.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function printErrors(OutputInterface $output, array $errors): void {
		$output->writeln('<error>Configuration bundle validation failed — nothing was applied:</error>');
		foreach ($errors as $error) {
			$section = ($error['section'] ?? 'unknown');
			$message = ($error['message'] ?? 'Unknown error.');
			$output->writeln('  - [' . $section . '] ' . $message);
		}
	}//end printErrors()

	/**
	 * Print the per-section result table for a valid import (applied or dry-run).
	 *
	 * @param OutputInterface $output The console output.
	 * @param array<string, mixed> $result The service's import() result.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function printSections(OutputInterface $output, array $result): void {
		$headline = '<info>Configuration bundle applied:</info>';
		if ($result['dryRun'] === true) {
			$headline = '<info>Dry run — the following sections would be applied:</info>';
		}

		$output->writeln($headline);

		foreach ($result['sections'] as $section => $summary) {
			$output->writeln('  - ' . $section . ': ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
		}
	}//end printSections()
}//end class
