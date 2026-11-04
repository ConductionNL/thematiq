<?php

/**
 * Parity of the theming app test doubles with the server's classes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theming-sync/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Server\Theming;

use OCA\Thematiq\Tests\Server\Support\MethodSignature;
use PHPUnit\Framework\TestCase;

/**
 * Checks tests/doubles/OCA/Theming against the real theming app classes of the booted server: every member a double
 * declares exists there with the same signature or value, so the unit tests that mock a double mock the real shape.
 */
class ThemingDoublesParityTest extends TestCase {
	/**
	 * The doubled classes, by the file their double lives in.
	 */
	private const DOUBLES = [
		'ImageManager.php' => 'OCA\\Theming\\ImageManager',
		'ThemingDefaults.php' => 'OCA\\Theming\\ThemingDefaults',
		'Service/BackgroundService.php' => 'OCA\\Theming\\Service\\BackgroundService',
	];

	/**
	 * The namespace the doubles are loaded under, next to the real classes.
	 */
	private const DOUBLE_NAMESPACE = 'OCA\\Thematiq\\Tests\\Server\\Theming\\Double';

	/**
	 * Require the booted server and its theming app.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->assertTrue(
			condition: class_exists(class: 'OC', autoload: false),
			message: 'This suite needs a booted Nextcloud server; run it through phpunit.xml inside a server tree.'
		);
	}//end setUp()

	/**
	 * Every method a double declares has the real class's signature.
	 *
	 * @return void
	 */
	public function testEveryDoubledMethodHasTheRealSignature(): void {
		$compared = 0;
		foreach (self::DOUBLES as $file => $class) {
			$real = new \ReflectionClass(objectOrClass: $class);
			$this->assertStringContainsString(needle: '/apps/theming/', haystack: (string)$real->getFileName(), message: $class);
			$double = new \ReflectionClass(objectOrClass: $this->loadDouble(file: $file, class: $class));
			foreach ($double->getMethods() as $method) {
				$name = $method->getName();
				$this->assertTrue(condition: $real->hasMethod(name: $name), message: $class . '::' . $name . ' does not exist');
				$this->assertSame(
					expected: MethodSignature::of(method: $real->getMethod(name: $name)),
					actual: MethodSignature::of(method: $method),
					message: $class . '::' . $name
				);
				$compared++;
			}
		}

		$this->assertSame(expected: 6, actual: $compared);
	}//end testEveryDoubledMethodHasTheRealSignature()

	/**
	 * Every constant a double declares has the real class's value.
	 *
	 * @return void
	 */
	public function testEveryDoubledConstantHasTheRealValue(): void {
		$compared = 0;
		foreach (self::DOUBLES as $file => $class) {
			$real = new \ReflectionClass(objectOrClass: $class);
			$double = new \ReflectionClass(objectOrClass: $this->loadDouble(file: $file, class: $class));
			foreach ($double->getConstants() as $name => $value) {
				$this->assertSame(expected: $real->getConstant(name: $name), actual: $value, message: $class . '::' . $name);
				$compared++;
			}
		}

		$this->assertSame(expected: 2, actual: $compared);
	}//end testEveryDoubledConstantHasTheRealValue()

	/**
	 * Load one double under DOUBLE_NAMESPACE and return its class name there.
	 *
	 * @param string $file The double's path under tests/doubles/OCA/Theming.
	 * @param string $class The real class it doubles.
	 *
	 * @return string
	 */
	private function loadDouble(string $file, string $class): string {
		$renamed = self::DOUBLE_NAMESPACE . substr(string: $class, offset: strlen(string: 'OCA\\Theming'));
		if (class_exists(class: $renamed, autoload: false) === true) {
			return $renamed;
		}

		$source = (string)file_get_contents(filename: __DIR__ . '/../../doubles/OCA/Theming/' . $file);
		$namespace = substr(string: $class, offset: 0, length: (int)strrpos(haystack: $class, needle: '\\'));
		$target = substr(string: $renamed, offset: 0, length: (int)strrpos(haystack: $renamed, needle: '\\'));
		$moved = str_replace(search: 'namespace ' . $namespace . ';', replace: 'namespace ' . $target . ';', subject: $source);
		$this->assertNotSame(expected: $source, actual: $moved, message: $file);

		$path = tempnam(directory: sys_get_temp_dir(), prefix: 'thematiq-theming-double');
		file_put_contents(filename: $path, data: $moved);
		require_once $path;
		unlink(filename: $path);

		return $renamed;
	}//end loadDouble()
}//end class
