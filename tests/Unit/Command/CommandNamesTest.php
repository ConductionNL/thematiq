<?php

/**
 * Every occ command registers under the thematiq app id.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Command
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Console\Command\Command;

/**
 * The fleet convention is that an app's occ commands move with its id (#666).
 *
 * The commands kept their nldesign:* names after the id moved to thematiq, so
 * `occ list thematiq` found nothing. They are renamed outright, with no alias
 * under the old name. Reads the command list from appinfo/info.xml, the list
 * Nextcloud itself registers, so a new command is covered without editing
 * this test.
 *
 * @spec openspec/specs/theme-cli/spec.md
 */
class CommandNamesTest extends TestCase {

	/**
	 * The command classes appinfo/info.xml registers.
	 *
	 * @return array<string, array{0: class-string<Command>}> One case per class.
	 */
	public static function commandClasses(): array {
		$xml = simplexml_load_file(__DIR__ . '/../../../appinfo/info.xml');
		$cases = [];
		foreach ($xml->commands->command as $command) {
			$class = trim((string)$command);
			$cases[$class] = [$class];
		}

		return $cases;
	}//end commandClasses()

	/**
	 * Info.xml registers commands at all, so the data provider cannot pass empty.
	 *
	 * @return void
	 */
	public function testInfoXmlRegistersCommands(): void {
		$this->assertGreaterThanOrEqual(9, count(self::commandClasses()));
	}//end testInfoXmlRegistersCommands()

	/**
	 * A command is named thematiq:* and keeps no nldesign:* alias.
	 *
	 * @param class-string<Command> $class The command class.
	 *
	 * @dataProvider commandClasses
	 *
	 * @return void
	 */
	public function testCommandIsNamedUnderThematiq(string $class): void {
		$command = $this->instantiate(class: $class);

		$this->assertStringStartsWith('thematiq:', (string)$command->getName());
		foreach ($command->getAliases() as $alias) {
			$this->assertStringStartsNotWith('nldesign:', $alias);
		}
	}//end testCommandIsNamedUnderThematiq()

	/**
	 * Build a command with a double for every constructor dependency.
	 *
	 * @param class-string<Command> $class The command class.
	 *
	 * @return Command The command, configured by its constructor.
	 */
	private function instantiate(string $class): Command {
		$reflection = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		$args = [];
		foreach (($constructor?->getParameters() ?? []) as $parameter) {
			$type = $parameter->getType();
			if ($parameter->isDefaultValueAvailable() === true) {
				$args[] = $parameter->getDefaultValue();
				continue;
			}

			$this->assertInstanceOf(ReflectionNamedType::class, $type);
			$this->assertFalse($type->isBuiltin(), $class . ' needs a scalar ' . $parameter->getName());
			$args[] = $this->createMock($type->getName());
		}

		$command = $reflection->newInstanceArgs($args);
		$this->assertInstanceOf(Command::class, $command);

		return $command;
	}//end instantiate()
}//end class
