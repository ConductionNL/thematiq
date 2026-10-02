<?php

/**
 * OwnComponentService and OwnComponentController: the limits, the slug rule, raw storage,
 * and the admin-only posture.
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
 * @spec openspec/changes/authoring-own-markup-preview/tasks.md#task-4.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Controller\OwnComponentController;
use OCA\Thematiq\Service\OwnComponentService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

// The flat in-memory folder the theme version tests use.
require_once __DIR__ . '/ThemeVersionServiceTest.php';

/**
 * Over an in-memory app data folder.
 *
 * @spec openspec/changes/authoring-own-markup-preview/tasks.md#task-4.1
 */
final class OwnComponentServiceTest extends TestCase {

	/**
	 * The components folder.
	 *
	 * @var FakeVersionFolder
	 */
	private FakeVersionFolder $folder;

	/**
	 * The service under test.
	 *
	 * @var OwnComponentService
	 */
	private OwnComponentService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->folder = new FakeVersionFolder();
		$folder = $this->folder;
		$root   = new class($folder) implements IAppData {
			public bool $exists = false;

			public function __construct(private FakeVersionFolder $folder) {
			}

			public function getFolder(string $name): ISimpleFolder {
				if ($this->exists === false || $name !== OwnComponentService::FOLDER) {
					throw new NotFoundException();
				}

				return $this->folder;
			}

			public function getDirectoryListing(): array {
				return [];
			}

			public function newFolder(string $name): ISimpleFolder {
				$this->exists = true;
				return $this->folder;
			}
		};
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($root);
		$this->service = new OwnComponentService($factory);
	}//end setUp()

	/**
	 * At most 20 components and 64 KB per component; replacing one does not count twice.
	 *
	 * @return void
	 */
	public function testLimits(): void {
		for ($i = 1; $i <= 20; $i++) {
			$this->service->save(name: 'Kaart ' . $i, html: '<p>x</p>', css: '');
		}

		$this->service->save(name: 'Kaart 1', html: '<p>y</p>', css: '');
		$this->assertCount(20, $this->service->list());

		foreach ([['Kaart 21', 'x', 'count'], ['Kaart 1', str_repeat('a', 65537), 'size']] as [$name, $html, $field]) {
			try {
				$this->service->save(name: $name, html: $html, css: '');
				$this->fail('expected a refusal: ' . $field);
			} catch (InvalidArgumentException $e) {
				$this->assertSame([$field, 400], [$e->getMessage(), $e->getCode()]);
			}
		}

		$this->assertSame('<p>y</p>', $this->service->list()[0]['html']);
	}//end testLimits()

	/**
	 * The slug comes from the name; a name with nothing usable is refused.
	 *
	 * @return void
	 */
	public function testSlugRule(): void {
		$this->assertSame('afvalkaart', $this->service->save(name: 'Afvalkaart', html: '', css: '')['slug']);
		$this->assertSame('kaart-voor-ophaaldagen', OwnComponentService::slugFor(name: 'Kaart voor ophaaldagen!'));
		$this->assertFalse(OwnComponentService::isSlug(slug: '../x'));

		$this->expectExceptionMessage('name');
		$this->service->save(name: '!!!', html: '', css: '');
	}//end testSlugRule()

	/**
	 * The text is stored raw: cleaning happens each time it renders, in the browser.
	 *
	 * @return void
	 */
	public function testStoredRawAndCleanedOnRender(): void {
		$html = '<button onclick="alert(1)">Test</button><script>alert(2)</script>';
		$this->service->save(name: 'Proef', html: $html, css: '.x{background:url(https://example.org/a.png)}');

		$this->assertSame($html, $this->service->list()[0]['html']);
		$this->service->delete(slug: 'proef');
		$this->assertSame([], $this->service->list());

		$this->expectExceptionCode(404);
		$this->service->delete(slug: 'proef');
	}//end testStoredRawAndCleanedOnRender()

	/**
	 * The controller: admin-only, 400 naming the size rule, 404 for an unknown slug.
	 *
	 * @return void
	 */
	public function testControllerPosture(): void {
		foreach (['list', 'save', 'delete'] as $method) {
			$reflection = new ReflectionMethod(OwnComponentController::class, $method);
			$this->assertSame(Admin::class, ($reflection->getAttributes(AuthorizedAdminSetting::class)[0]->getArguments()['settings'] ?? null), $method);
			$this->assertSame([], $reflection->getAttributes(NoAdminRequired::class));
		}

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ['name' => 'Groot', 'html' => str_repeat('a', 70000), 'css' => ''][$key] ?? $default);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$controller = new OwnComponentController('thematiq', $request, $this->service, $l);

		$response = $controller->save();
		$this->assertSame(400, $response->getStatus());
		$this->assertStringContainsString('64 KB', $response->getData()['error']);
		$this->assertSame([], $this->service->list());
		$this->assertSame(404, $controller->delete(slug: 'nope')->getStatus());
	}//end testControllerPosture()
}//end class
