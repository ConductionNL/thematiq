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
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int The exit code: 0 applied or previewed, 1 unknown or refused.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$request = $this->request(input: $input);
		$result = $this->previewOrRestore(request: $request);
		if ($result === null) {
			$output->writeln('<error>Unknown version: ' . $request->id . '</error>');
			return Command::FAILURE;
		}

		$this->printChanges(output: $output, result: $result);

		if ($this->isRefused(result: $result, request: $request) === true) {
			$this->printErrors(output: $output, errors: $result['errors']);

			return Command::FAILURE;
		}

		$output->writeln($this->doneLine(request: $request));

		return Command::SUCCESS;
	}//end execute()

	/**
	 * The version and the mode the console input asks for.
	 *
	 * @param InputInterface $input The input.
	 *
	 * @return RestoreRequest The request.
	 */
	private function request(InputInterface $input): RestoreRequest {
		return new RestoreRequest(
			id: (string)$input->getArgument('id'),
			dryRun: ($input->getOption('dry-run') === true)
		);
	}//end request()

	/**
	 * Preview on a dry run, restore otherwise.
	 *
	 * @param RestoreRequest $request The version id and whether to write nothing.
	 *
	 * @return array<string, mixed>|null The result, or null for an unknown id.
	 */
	private function previewOrRestore(RestoreRequest $request): ?array {
		if ($request->dryRun === true) {
			return $this->restorer->preview(id: $request->id);
		}

		return $this->restorer->restore(id: $request->id, actor: 'cli');
	}//end previewOrRestore()

	/**
	 * Whether the version is refused: invalid, or a real run that did not apply.
	 *
	 * @param array<string, mixed> $result The preview or restore result.
	 * @param RestoreRequest $request The request.
	 *
	 * @return bool True when the run failed.
	 */
	private function isRefused(array $result, RestoreRequest $request): bool {
		return $result['valid'] === false || ($request->dryRun === false && ($result['applied'] ?? false) !== true);
	}//end isRefused()

	/**
	 * Print why the version was refused, one line per error.
	 *
	 * @param OutputInterface $output The output.
	 * @param array<int, array<string, mixed>> $errors The per-section errors.
	 *
	 * @return void
	 */
	private function printErrors(OutputInterface $output, array $errors): void {
		$output->writeln('<error>The version does not validate today; nothing was applied:</error>');
		foreach ($errors as $error) {
			$output->writeln('  - [' . ($error['section'] ?? 'unknown') . '] ' . ($error['message'] ?? json_encode($error)));
		}
	}//end printErrors()

	/**
	 * The closing line of a run that succeeded.
	 *
	 * @param RestoreRequest $request The request.
	 *
	 * @return string The line.
	 */
	private function doneLine(RestoreRequest $request): string {
		if ($request->dryRun === true) {
			return '<info>Dry run: nothing was written.</info>';
		}

		return '<info>Version ' . $request->id . ' restored.</info>';
	}//end doneLine()

	/**
	 * Print the changes a restore makes.
	 *
	 * @param OutputInterface $output The output.
	 * @param array<string, mixed> $result The preview or restore result.
	 *
	 * @return void
	 */
	private function printChanges(OutputInterface $output, array $result): void {
		$this->printFieldChanges(output: $output, changes: $result['changes']);
		$this->printTokenSetChanges(output: $output, sets: $result['customTokenSets']);
		foreach ($result['missingFonts'] as $font) {
			$output->writeln('  ' . $font['role'] . ' font no longer uploaded, stays on the default: ' . $font['name']);
		}
	}//end printChanges()

	/**
	 * Print each changed field with its old and new value.
	 *
	 * @param OutputInterface $output The output.
	 * @param array<int, array<string, mixed>> $changes The field changes.
	 *
	 * @return void
	 */
	private function printFieldChanges(OutputInterface $output, array $changes): void {
		foreach ($changes as $change) {
			$from = json_encode($change['from'], JSON_UNESCAPED_SLASHES);
			$to = json_encode($change['to'], JSON_UNESCAPED_SLASHES);
			$output->writeln('  ' . $change['field'] . ': ' . $from . ' -> ' . $to);
		}
	}//end printFieldChanges()

	/**
	 * Print the custom token sets a restore adds and removes.
	 *
	 * @param OutputInterface $output The output.
	 * @param array{add: array<int, string>, remove: array<int, string>} $sets The added and removed set ids.
	 *
	 * @return void
	 */
	private function printTokenSetChanges(OutputInterface $output, array $sets): void {
		foreach ($sets['add'] as $setId) {
			$output->writeln('  custom token set added: ' . $setId);
		}

		foreach ($sets['remove'] as $setId) {
			$output->writeln('  custom token set removed: ' . $setId);
		}
	}//end printTokenSetChanges()
}//end class
