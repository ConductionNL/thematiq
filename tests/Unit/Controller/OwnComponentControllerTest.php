<?php

/**
 * Tests for OwnComponentController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Controller\OwnComponentController;
use OCA\Thematiq\Service\OwnComponentService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Over a mocked component store.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */
final class OwnComponentControllerTest extends TestCase {

	/**
	 * The component store.
	 *
	 * @var OwnComponentService&MockObject
	 */
	private OwnComponentService&MockObject $components;

	/**
	 * A fresh store per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->components = $this->createMock(originalClassName: OwnComponentService::class);
	}//end setUp()

	/**
	 * The controller over a request with these params.
	 *
	 * @param array<string, string> $params The params.
	 *
	 * @return OwnComponentController
	 */
	private function controller(array $params = []): OwnComponentController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($params[$key] ?? $default));
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text): string => 'T:' . $text);

		return new OwnComponentController(appName: 'thematiq', request: $request, components: $this->components, l: $l10n);
	}//end controller()

	/**
	 * The list carries the components and the limits.
	 *
	 * @return void
	 */
	public function testListCarriesTheComponentsAndTheLimits(): void {
		$this->components->method('list')->willReturn([['slug' => 'card']]);

		$data = $this->controller()->list()->getData();
		$this->assertSame(expected: [['slug' => 'card']], actual: $data['components']);
		$this->assertSame(expected: ['count' => 20, 'bytes' => 65536], actual: $data['limits']);
	}//end testListCarriesTheComponentsAndTheLimits()

	/**
	 * A store that cannot be read is a 500 that says so.
	 *
	 * @return void
	 */
	public function testListThatCannotBeReadIsA500(): void {
		$this->components->method('list')->willThrowException(new RuntimeException('no app data'));

		$response = $this->controller()->list();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:Your components could not be loaded.'], actual: $response->getData());
	}//end testListThatCannotBeReadIsA500()

	/**
	 * A save passes the name, HTML and CSS and answers with the component.
	 *
	 * @return void
	 */
	public function testSavePassesTheFieldsOn(): void {
		$this->components->expects($this->once())->method('save')->with('Card', '<div></div>', '.x{}')->willReturn(['slug' => 'card']);

		$response = $this->controller(params: ['name' => 'Card', 'html' => '<div></div>', 'css' => '.x{}'])->save();
		$this->assertSame(expected: ['status' => 'ok', 'component' => ['slug' => 'card']], actual: $response->getData());
	}//end testSavePassesTheFieldsOn()

	/**
	 * Every refused field with its text.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function refusalProvider(): array {
		return [
			'name' => ['name', 'T:Give the component a name of at most 80 characters, with at least one letter or digit.'],
			'size' => ['size', 'T:The HTML and CSS together may be at most 64 KB.'],
			'count' => ['count', 'T:You can keep at most 20 components. Remove one first.'],
			'unknown' => ['other', 'T:The component was not saved.'],
		];
	}//end refusalProvider()

	/**
	 * A refused save is a 400 naming the field.
	 *
	 * @param string $field The refused field.
	 * @param string $expected The text.
	 *
	 * @return void
	 */
	#[DataProvider('refusalProvider')]
	public function testRefusedSaveIs400NamingTheField(string $field, string $expected): void {
		$this->components->method('save')->willThrowException(new InvalidArgumentException($field));

		$response = $this->controller()->save();
		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => $expected, 'field' => $field], actual: $response->getData());
	}//end testRefusedSaveIs400NamingTheField()

	/**
	 * A store failure while saving is a 500 without a field.
	 *
	 * @return void
	 */
	public function testSaveFailureIsA500(): void {
		$this->components->method('save')->willThrowException(new RuntimeException('disk'));

		$response = $this->controller()->save();
		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'T:The component was not saved.'], actual: $response->getData());
	}//end testSaveFailureIsA500()

	/**
	 * A delete removes the component, and an unknown one is a 404.
	 *
	 * @return void
	 */
	public function testDeleteRemovesOrIsNotFound(): void {
		$this->components->expects($this->exactly(count: 2))->method('delete')->willReturnCallback(
			function (string $slug): void {
				if ($slug !== 'card') {
					throw new InvalidArgumentException('unknown');
				}
			}
		);

		$this->assertSame(expected: ['status' => 'ok'], actual: $this->controller()->delete(slug: 'card')->getData());
		$missing = $this->controller()->delete(slug: 'nope');
		$this->assertSame(expected: 404, actual: $missing->getStatus());
		$this->assertSame(expected: ['error' => 'T:There is no component with this name.'], actual: $missing->getData());
	}//end testDeleteRemovesOrIsNotFound()

	/**
	 * Every endpoint is an admin setting.
	 *
	 * @return void
	 */
	public function testEveryEndpointIsAnAdminSetting(): void {
		foreach (['list', 'save', 'delete'] as $method) {
			$attributes = (new ReflectionMethod(OwnComponentController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: ['settings' => Admin::class], actual: $attributes[0]->getArguments(), message: $method);
		}
	}//end testEveryEndpointIsAnAdminSetting()
}//end class
