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

use OCA\Thematiq\Service\ThemeVersionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ nldesign:config:versions`: list the kept configuration versions.
 *
 * @spec openspec/specs/theme-versions/spec.md
 */
class ConfigVersions extends Command {

	/**
	 * Constructor.
	 *
	 * @param ThemeVersionService $versions The kept versions.
	 */
	public function __construct(
		private readonly ThemeVersionService $versions,
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
		$this->setName(name: 'nldesign:config:versions')
			->setDescription('List the kept configuration versions, newest first: id, time, actor and audit action.');
	}//end configure()

	/**
	 * Print one line per version.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int The exit code.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - Symfony's execute() signature; this command takes no input.
	 *
	 * @spec openspec/specs/theme-versions/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$versions = $this->versions->list();
		if ($versions === []) {
			$output->writeln('No versions are kept yet.');
			return Command::SUCCESS;
		}

		foreach ($versions as $version) {
			$output->writeln(implode('  ', [$version['id'], $version['ts'], $version['actor'], $version['action']]));
		}

		return Command::SUCCESS;
	}//end execute()
}//end class
