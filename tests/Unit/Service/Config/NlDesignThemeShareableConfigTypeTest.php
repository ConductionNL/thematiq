<?php

/**
 * Federated-config contract test for the NL Design theme shareable type.
 *
 * This capability shipped in #192 with NO test coverage of any kind. Its three
 * identity strings are the WIRE IDENTITY a receiving instance matches on, so a
 * silent rename would break every already-published share with no local signal.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/federated-config-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service\Config;

use OCA\Thematiq\Listener\ShareableConfigTypeListener;
use OCA\Thematiq\Service\Config\NlDesignThemeShareableConfigType;
use OCA\Thematiq\Service\ConfigBundleService;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * OpenRegister is a SOFT dependency and is absent here, exactly as it is during
 * static analysis. `stubs/openregister-config.php` is the declaration-only copy
 * of its contract that phpstan/psalm already use; loading it gives this test the
 * same contract without pulling OpenRegister into the test environment.
 *
 * It is loaded here rather than from tests/bootstrap.php on purpose: if
 * OpenRegister IS installed, its real classes are already declared and the stub
 * must not redeclare them.
 */
if (interface_exists(\OCA\OpenRegister\Service\Config\IShareableConfigType::class) === false) {
	require_once \dirname(__DIR__, 4) . '/stubs/openregister-config.php';
}

/**
 * Contract test: identity, serialisation, deserialisation, laziness, soft dependency.
 */
class NlDesignThemeShareableConfigTypeTest extends TestCase {
	/**
	 * A container that records whether it was asked for anything.
	 *
	 * @param ConfigBundleService|null $bundle What to return, or null to fail if asked.
	 *
	 * @return object{container: ContainerInterface, calls: \ArrayObject}
	 */
	private function recordingContainer(?ConfigBundleService $bundle): object {
		$calls = new \ArrayObject();
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			function (string $id) use ($calls, $bundle) {
				$calls[] = $id;
				return $bundle;
			}
		);

		return (object)['container' => $container, 'calls' => $calls];
	}

	/**
	 * The three wire-identity strings are exactly what peers match on.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testWireIdentityIsStable(): void {
		$rec = $this->recordingContainer(bundle: null);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		$this->assertSame(expected: 'nldesign.theme', actual: $type->getId());
		$this->assertSame(expected: 'NL Design theme', actual: $type->getDisplayName());
		$this->assertSame(expected: 'nldesign-theme', actual: $type->getTopic());
	}

	/**
	 * Constructing the type, and reading its identity, resolves nothing from the
	 * container. The shareable-type catalogue constructs every registered type on
	 * any cross-app read; eager resolution would drag the theming chain into all
	 * of them.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testTheBundleServiceIsResolvedLazily(): void {
		$rec = $this->recordingContainer(bundle: null);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		$type->getId();
		$type->getDisplayName();
		$type->getTopic();

		$this->assertCount(
			expectedCount: 0,
			haystack: $rec->calls,
			message: 'Constructing the type and reading its identity must not resolve anything '
			. 'from the container; it resolved: ' . implode(', ', (array)$rec->calls)
		);
	}

	/**
	 * serialise() returns the envelope wrapped around ConfigBundleService::export(),
	 * and ignores the selection.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testSerialiseWrapsTheConfigBundleAndIgnoresTheSelection(): void {
		$exported = ['config' => ['tokenSet' => 'amsterdam'], 'bundleVersion' => 1];

		$bundle = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundle->expects($this->exactly(count: 2))->method('export')->willReturn($exported);

		$rec = $this->recordingContainer(bundle: $bundle);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		$empty = $type->serialise([]);
		$selected = $type->serialise(['something', 'else']);

		$this->assertSame(
			expected: ['type' => 'nldesign.theme', 'version' => '1.0', 'bundle' => $exported],
			actual: $empty
		);
		$this->assertSame(
			expected: $empty,
			actual: $selected,
			message: 'A theme has no selection — $selection must make no difference to the output.'
		);
		$this->assertSame(
			expected: [ConfigBundleService::class, ConfigBundleService::class],
			actual: (array)$rec->calls,
			message: 'serialise() must resolve ConfigBundleService from the container, once per call.'
		);
	}

	/**
	 * deserialise() applies the INNER bundle through the validated import path.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testDeserialiseAppliesTheInnerBundleThroughImport(): void {
		$inner = ['config' => ['tokenSet' => 'rijkshuisstijl']];
		$result = ['applied' => 4];

		$bundle = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundle->expects($this->once())
			->method('import')
			->with($inner, false)
			->willReturn($result);

		$rec = $this->recordingContainer(bundle: $bundle);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		$this->assertSame(
			expected: ['installed' => ['nldesign-theme'], 'import' => $result],
			actual: $type->deserialise(['type' => 'nldesign.theme', 'bundle' => $inner])
		);
	}

	/**
	 * A payload with no usable bundle imports an empty array rather than fataling.
	 * A peer sending a bad payload must not be able to take this instance down.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testAMalformedPayloadImportsAnEmptyArrayInsteadOfFataling(): void {
		// `(array) 'x'` is `['x']`, not `[]` — a scalar bundle used to reach the
		// importer as a one-element list. `->with([], false)` is what catches it:
		// this test failed on the pre-fix code with
		// "Parameter 0 ... does not match expected value / + 0 => 'not-an-array'".
		$bundle = $this->createMock(originalClassName: ConfigBundleService::class);
		$bundle->expects($this->exactly(count: 4))
			->method('import')
			->with([], false)
			->willReturn([]);

		$rec = $this->recordingContainer(bundle: $bundle);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		foreach ([[], ['bundle' => null], ['bundle' => 'not-an-array'], ['bundle' => 42]] as $payload) {
			$this->assertSame(
				expected: ['installed' => ['nldesign-theme'], 'import' => []],
				actual: $type->deserialise($payload),
				message: 'A malformed payload must import an empty array, not fatal and not smuggle a scalar.'
			);
		}
	}

	/**
	 * The listener contributes the type on the matching event, and ignores anything else.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testListenerRegistersOnTheMatchingEventOnly(): void {
		$rec = $this->recordingContainer(bundle: null);
		$type = new NlDesignThemeShareableConfigType(container: $rec->container);

		$listener = new ShareableConfigTypeListener(theme: $type);

		$event = $this->createMock(originalClassName: \OCA\OpenRegister\Service\Config\RegisterShareableConfigTypesEvent::class);
		$event->expects($this->once())->method('registerType')->with($type);
		$listener->handle($event);

		// Any other event must be ignored — no exception, no registration.
		$listener->handle(new class extends Event {
		});
		$this->addToAssertionCount(count: 1);
	}

	/**
	 * OpenRegister stays a SOFT dependency.
	 *
	 * `NlDesignThemeShareableConfigType` names `IShareableConfigType` in its CLASS
	 * HEADER. PHP resolves a class header at load time and no lazy-DI arrangement
	 * can defer that, so constructing it on an instance without OpenRegister is
	 * fatal. The `class_exists()` guard in Application.php is what ensures nothing
	 * constructs it there — and `<app>openregister</app>` must stay absent from
	 * info.xml, or the app becomes uninstallable without it.
	 *
	 * @spec openspec/specs/federated-config-sharing/spec.md
	 *
	 * @return void
	 */
	public function testOpenRegisterRemainsASoftDependency(): void {
		$root = \dirname(__DIR__, 4);

		$application = file_get_contents($root . '/lib/AppInfo/Application.php');
		$this->assertIsString(actual: $application);

		$this->assertMatchesRegularExpression(
			pattern: '/class_exists\(\s*\\\\?OCA\\\\OpenRegister\\\\Service\\\\Config\\\\RegisterShareableConfigTypesEvent::class\s*\)/',
			string: $application,
			message: 'The shareable-config listener registration must stay behind a class_exists() guard '
			. 'on the OpenRegister event class, or an instance without OpenRegister cannot boot.'
		);

		$info = file_get_contents($root . '/appinfo/info.xml');
		$this->assertIsString(actual: $info);
		$this->assertDoesNotMatchRegularExpression(
			pattern: '#<app>\s*openregister\s*</app>#i',
			string: $info,
			message: 'openregister must NOT be declared as a hard <app> dependency — it is optional, '
			. 'and declaring it would also force this app min-version up to openregister\'s floor.'
		);
	}
}
