<?php

/**
 * NL Design Compliance Report Command.
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
 * @spec openspec/specs/compliance-evidence/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Command;

use OCA\Thematiq\Service\ComplianceReportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `occ thematiq:compliance-report` — headless/OTAP export of the
 * active-configuration WCAG contrast compliance evidence report.
 *
 * Reuses {@see ComplianceReportService} exclusively, so output is byte-for-byte
 * identical to the `settings#complianceReport` HTTP endpoint for the same
 * configuration. Exit code is 0 whenever the report generates — regardless of
 * verdict — and non-zero ONLY on generation failure, so audit pipelines can
 * distinguish "evidence says fail" from "no evidence produced".
 *
 * @spec openspec/specs/compliance-evidence/spec.md
 */
class ComplianceReport extends Command {

	/**
	 * The compliance report service.
	 *
	 * @var ComplianceReportService
	 */
	private ComplianceReportService $service;

	/**
	 * Constructor.
	 *
	 * @param ComplianceReportService $service The compliance report service.
	 */
	public function __construct(ComplianceReportService $service) {
		parent::__construct();
		$this->service = $service;
	}//end __construct()

	/**
	 * Configure the command name, description and options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'thematiq:compliance-report')
			->setDescription(
				'Generate the NL Design active-configuration WCAG contrast compliance evidence '
				. 'report (color-contrast of theme tokens only — NOT a WCAG-EM audit).'
			)
			->addOption(
				name: 'format',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Output format: json or markdown',
				default: 'json'
			)
			->addOption(
				name: 'output',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Write the report to this file path instead of stdout'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int The exit code — 0 on successful generation (any verdict), non-zero on generation failure.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$format = $input->getOption('format');
		if ($format !== 'json' && $format !== 'markdown') {
			$output->writeln('<error>Unknown format "' . $format . '". Use "json" or "markdown".</error>');

			return Command::FAILURE;
		}

		$content = $this->renderedReport(output: $output, format: $format);
		if ($content === null) {
			return Command::FAILURE;
		}

		return $this->deliver(
			output: $output,
			report: new OutputFile(path: (string)$input->getOption('output'), content: $content)
		);
	}//end execute()

	/**
	 * Write the report to its file, or to stdout when no file was given.
	 *
	 * @param OutputInterface $output The console output.
	 * @param OutputFile $report The destination and the rendered report.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function deliver(OutputInterface $output, OutputFile $report): int {
		if ($report->toFile() === true) {
			return $this->writeToFile(output: $output, file: $report);
		}

		$output->write($report->content);

		return Command::SUCCESS;
	}//end deliver()

	/**
	 * Render the report, reporting a generation failure.
	 *
	 * @param OutputInterface $output The console output.
	 * @param string $format The requested format ("json" or "markdown").
	 *
	 * @return string|null The rendered report, or null when generation failed.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function renderedReport(OutputInterface $output, string $format): ?string {
		try {
			return $this->renderContent(format: $format);
		} catch (Throwable $e) {
			$output->writeln('<error>Compliance report generation failed: ' . $e->getMessage() . '</error>');

			return null;
		}
	}//end renderedReport()

	/**
	 * Render the report content for the requested format.
	 *
	 * @param string $format The requested format ("json" or "markdown").
	 *
	 * @return string The rendered report.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function renderContent(string $format): string {
		if ($format === 'markdown') {
			return $this->service->renderMarkdown();
		}

		return $this->service->renderJson();
	}//end renderContent()

	/**
	 * Write the rendered content to a file path, reporting success/failure.
	 *
	 * @param OutputInterface $output The console output.
	 * @param OutputFile $file The destination file path and the content to write.
	 *
	 * @return int The exit code.
	 *
	 * @spec openspec/specs/compliance-evidence/spec.md
	 */
	private function writeToFile(OutputInterface $output, OutputFile $file): int {
		if (file_put_contents($file->path, $file->content) === false) {
			$output->writeln('<error>Could not write report to ' . $file->path . '</error>');

			return Command::FAILURE;
		}

		$output->writeln('<info>Report written to ' . $file->path . '</info>');

		return Command::SUCCESS;
	}//end writeToFile()
}//end class
