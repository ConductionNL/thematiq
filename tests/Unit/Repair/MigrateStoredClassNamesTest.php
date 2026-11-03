<?php

/**
 * Unit tests for the OCA\NLDesign -> OCA\Thematiq stored-class-name migration.
 *
 * WHAT MAKES THIS STEP DIFFERENT from its two siblings: they move rows between
 * app-id namespaces and never inspect a value, while this one rewrites a single
 * fixed slot whose VALUE is a class name that no longer resolves.
 *
 * The stale value fails twice over, and silently both times. Nextcloud's
 * `Mailer::createEMailTemplate()` guards `mail_template_class` with
 * `class_exists()` and falls back to the stock template, so branded government
 * email just stops; and `EmailThemingService::getState()` classifies any value
 * that is neither empty nor the current class as `foreign`, so the admin panel
 * reports the app's OWN pre-rename value as a third-party template and
 * `enable()` refuses it with HTTP 409. The admin is locked out of the fix and
 * told the wrong reason.
 *
 * The tests below therefore assert the WRITTEN value, and — just as important —
 * assert the cases where nothing must be written at all.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Repair;

use OCA\Thematiq\Repair\MigrateStoredClassNames;
use OCA\Thematiq\Service\EmailThemingService;
use OCP\IConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers the rewrite, the four cases that must not write, and the failure paths.
 */
final class MigrateStoredClassNamesTest extends TestCase {

	/**
	 * The mail template FQCN stored before the namespace rename.
	 *
	 * A literal, not a `::class` reference: the class it names is gone.
	 *
	 * @var string
	 */
	private const OLD_CLASS = 'OCA\\NLDesign\\Mail\\NLDesignEMailTemplate';

	/**
	 * The mail template FQCN this app registers now.
	 *
	 * Also a literal. Writing it out rather than deriving it from
	 * `NLDesignEMailTemplate::class` is deliberate: the class extends the
	 * server-private `OC\Mail\EMailTemplate`, and spelling the expectation
	 * independently means this test pins the exact string an admin's config.php
	 * must end up containing rather than restating whatever the code computes.
	 *
	 * @var string
	 */
	private const NEW_CLASS = 'OCA\\Thematiq\\Mail\\NLDesignEMailTemplate';

	/**
	 * The system-config key under test.
	 *
	 * @var string
	 */
	private const KEY = 'mail_template_class';

	/**
	 * The in-memory system config: key => value.
	 *
	 * @var array<string, mixed>
	 */
	private array $system = [];

	/**
	 * Whether the instance reports config.php as read-only.
	 *
	 * @var bool
	 */
	private bool $readOnly = false;

	/**
	 * Whether a write attempt throws.
	 *
	 * @var bool
	 */
	private bool $writeThrows = false;

	/**
	 * Whether reading the system value throws.
	 *
	 * @var bool
	 */
	private bool $readThrows = false;

	/**
	 * Reset the fixture between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->system = [];
		$this->readOnly = false;
		$this->writeThrows = false;
		$this->readThrows = false;

	}//end setUp()

	/**
	 * An IConfig mock backed by `$this->system`.
	 *
	 * @return IConfig
	 */
	private function config(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);

		$config->method('getSystemValue')
			->willReturnCallback(
				function (string $key, mixed $default = '') {
					if ($this->readThrows === true) {
						throw new RuntimeException('config unreadable');
					}

					return ($this->system[$key] ?? $default);
				}
			);

		$config->method('getSystemValueBool')
			->willReturnCallback(
				function (string $key, bool $default = false): bool {
					if ($key === 'config_is_read_only') {
						return $this->readOnly;
					}

					return $default;
				}
			);

		$config->method('setSystemValue')
			->willReturnCallback(
				function (string $key, mixed $value): void {
					if ($this->writeThrows === true) {
						throw new RuntimeException('config.php not writable');
					}

					$this->system[$key] = $value;
				}
			);

		return $config;
	}//end config()

	/**
	 * Build the step over the in-memory system config.
	 *
	 * @param LoggerInterface|null $logger An optional logger to assert against.
	 *
	 * @return MigrateStoredClassNames
	 */
	private function step(?LoggerInterface $logger = null): MigrateStoredClassNames {
		return new MigrateStoredClassNames(
			config: $this->config(),
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);

	}//end step()

	/**
	 * The stale class name is rewritten to the current one.
	 *
	 * This is the whole point: until it is, every outbound email silently loses
	 * its government branding and the admin cannot switch it back on.
	 *
	 * @return void
	 */
	public function testRewritesTheStaleMailTemplateClass(): void {
		$this->system = [self::KEY => self::OLD_CLASS];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: self::NEW_CLASS, actual: $this->system[self::KEY]);

	}//end testRewritesTheStaleMailTemplateClass()

	/**
	 * The rewritten value is exactly what EmailThemingService considers enabled.
	 *
	 * A rewrite to a class name the app does not recognise would clear the
	 * `class_exists()` failure while leaving the admin panel still reporting
	 * `foreign`, so the value has to match the service's own notion of enabled,
	 * not merely be a class that exists.
	 *
	 * @return void
	 */
	public function testTheRewrittenValueIsTheOneTheServiceCallsEnabled(): void {
		$this->system = [self::KEY => self::OLD_CLASS];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$configured = $this->system[self::KEY];
		$state = 'disabled';
		if ($configured === self::NEW_CLASS) {
			$state = 'enabled';
		} elseif ($configured !== '') {
			$state = 'foreign';
		}

		$this->assertSame(expected: 'enabled', actual: $state);

	}//end testTheRewrittenValueIsTheOneTheServiceCallsEnabled()

	/**
	 * A third-party mail template is never clobbered.
	 *
	 * An admin who configured an enterprise template did so deliberately. This
	 * step exists to undo the rename's damage, not to claim the slot.
	 *
	 * @return void
	 */
	public function testLeavesAForeignMailTemplateUntouched(): void {
		$this->system = [self::KEY => 'OCA\\SomeVendor\\Mail\\CorporateTemplate'];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: 'OCA\\SomeVendor\\Mail\\CorporateTemplate', actual: $this->system[self::KEY]);

	}//end testLeavesAForeignMailTemplateUntouched()

	/**
	 * An instance that never enabled email theming does not get it enabled.
	 *
	 * The absence of a value means the admin never turned this on. Writing the
	 * template class here would silently change how every email on the
	 * instance looks, which is not a migration but a new feature.
	 *
	 * @return void
	 */
	public function testDoesNotEnableEmailThemingWhereItWasNeverConfigured(): void {
		$this->system = [];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertArrayNotHasKey(key: self::KEY, array: $this->system);

	}//end testDoesNotEnableEmailThemingWhereItWasNeverConfigured()

	/**
	 * An already-migrated value is left exactly as it is.
	 *
	 * @return void
	 */
	public function testLeavesAnAlreadyMigratedValueAlone(): void {
		$this->system = [self::KEY => self::NEW_CLASS];

		$this->step()->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: self::NEW_CLASS, actual: $this->system[self::KEY]);

	}//end testLeavesAnAlreadyMigratedValueAlone()

	/**
	 * A second run is a no-op, so re-running the repair is safe.
	 *
	 * @return void
	 */
	public function testIsIdempotent(): void {
		$this->system = [self::KEY => self::OLD_CLASS];

		$step = $this->step();
		$step->run($this->createMock(originalClassName: IOutput::class));
		$step->run($this->createMock(originalClassName: IOutput::class));

		$this->assertSame(expected: self::NEW_CLASS, actual: $this->system[self::KEY]);

	}//end testIsIdempotent()

	/**
	 * A read-only config.php warns with the occ command instead of throwing.
	 *
	 * The step runs under `<install>`; an escaping exception aborts the install
	 * and the app never enables at all.
	 *
	 * @return void
	 */
	public function testReadOnlyConfigWarnsInsteadOfThrowing(): void {
		$this->system = [self::KEY => self::OLD_CLASS];
		$this->readOnly = true;

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: 'read-only'));

		$this->step()->run($output);

		$this->assertSame(expected: self::OLD_CLASS, actual: $this->system[self::KEY]);

	}//end testReadOnlyConfigWarnsInsteadOfThrowing()

	/**
	 * The occ command handed to the admin names the CURRENT class.
	 *
	 * Regression test for the defect this migration is a response to. The
	 * constant behind this message kept naming `OCA\NLDesign\...` after the
	 * namespace rename, so an admin on a read-only config.php who followed the
	 * instruction wrote a class that does not exist — `class_exists()` rejected
	 * it, Nextcloud fell back to the stock template, and the command reported
	 * success. Telling an admin to reproduce the bug is worse than telling them
	 * nothing.
	 *
	 * @return void
	 */
	public function testTheOccCommandItPrintsNamesTheCurrentClass(): void {
		$this->system = [self::KEY => self::OLD_CLASS];
		$this->readOnly = true;

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: self::NEW_CLASS));

		$this->step()->run($output);

		$this->assertStringContainsString(needle: self::NEW_CLASS, haystack: EmailThemingService::OCC_ENABLE_COMMAND);
		$this->assertStringNotContainsString(needle: self::OLD_CLASS, haystack: EmailThemingService::OCC_ENABLE_COMMAND);

	}//end testTheOccCommandItPrintsNamesTheCurrentClass()

	/**
	 * A filesystem-level unwritable config.php is caught, logged and survived.
	 *
	 * `config_is_read_only` can be false while config.php is chmod 444, in
	 * which case only the write itself reveals the problem.
	 *
	 * @return void
	 */
	public function testAFailingWriteIsLoggedAndDoesNotThrow(): void {
		$this->system = [self::KEY => self::OLD_CLASS];
		$this->writeThrows = true;

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringContains(string: 'could not rewrite mail_template_class'),
				$this->arrayHasKey(key: 'exception')
			);

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		$this->step(logger: $logger)->run($output);

		$this->assertSame(expected: self::OLD_CLASS, actual: $this->system[self::KEY]);

	}//end testAFailingWriteIsLoggedAndDoesNotThrow()

	/**
	 * An unreadable config warns rather than aborting the install.
	 *
	 * @return void
	 */
	public function testAnUnreadableConfigIsLoggedAndSkipped(): void {
		$this->readThrows = true;

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with($this->stringContains(string: 'could not read mail_template_class'));

		$output = $this->createMock(originalClassName: IOutput::class);
		$output->expects($this->once())->method('warning');

		$this->step(logger: $logger)->run($output);

		$this->assertSame(expected: [], actual: $this->system);

	}//end testAnUnreadableConfigIsLoggedAndSkipped()

	/**
	 * The step names both namespaces, so the repair output is self-explanatory.
	 *
	 * @return void
	 */
	public function testGetNameNamesBothNamespaces(): void {
		$name = $this->step()->getName();

		$this->assertStringContainsString(needle: 'NLDesign', haystack: $name);
		$this->assertStringContainsString(needle: 'Thematiq', haystack: $name);

	}//end testGetNameNamesBothNamespaces()

}//end class
