<?php

/**
 * Unit tests for Settings\AdminBootReads: the Theming admin page's first
 * reads, answered in its render.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\Controller\BrandFormController;
use OCA\Thematiq\Controller\ConfigSourceController;
use OCA\Thematiq\Controller\CustomCssController;
use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Controller\FontController;
use OCA\Thematiq\Controller\LayerController;
use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Controller\OwnComponentController;
use OCA\Thematiq\Controller\OwnTokenController;
use OCA\Thematiq\Controller\ScheduledSwitchController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Controller\TokenDeprecationController;
use OCA\Thematiq\Controller\TokenSetLicenceController;
use OCA\Thematiq\Settings\AdminBootReads;
use OCP\AppFramework\Http\JSONResponse;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Every read the page makes as it opens is answered by the controller action
 * its route serves, keyed by the path the page asks; a read that fails or does
 * not answer 200 is left for the page to fetch.
 */
class AdminBootReadsTest extends TestCase {

	/**
	 * The plain reads: controller => [action, path].
	 *
	 * @var array<int, array{0: class-string, 1: string, 2: string}>
	 */
	private const PLAIN = [
		[ScheduledSwitchController::class, 'index', '/settings/scheduled-switches'],
		[SettingsController::class, 'getAppTheming', '/settings/app-theming'],
		[OwnComponentController::class, 'list', '/settings/playground/components'],
		[OwnTokenController::class, 'list', '/settings/tokens/own'],
		[TokenDeprecationController::class, 'list', '/settings/tokens/deprecations'],
		[CustomCssController::class, 'getCustomCss', '/settings/custom-css'],
		[BrandFormController::class, 'inputs', '/settings/tokensets/from-colours'],
		[CustomTokenSetController::class, 'list', '/settings/tokensets/custom'],
		[TokenSetLicenceController::class, 'index', '/settings/tokensets/licences'],
		[FontController::class, 'list', '/settings/fonts'],
		[FontController::class, 'library', '/settings/fonts/library'],
		[ConfigSourceController::class, 'status', '/settings/config-source'],
	];

	/**
	 * A container that hands out the given controller mocks.
	 *
	 * @param array<class-string, object> $controllers Class => mock.
	 *
	 * @return ContainerInterface
	 */
	private function container(array $controllers): ContainerInterface {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(static fn (string $id): object => $controllers[$id]);

		return $container;
	}//end container()

	/**
	 * Controller mocks whose every boot action answers 200 with a body naming it.
	 *
	 * @return array<class-string, object>
	 */
	private function answeringControllers(): array {
		$actions = [];
		foreach (self::PLAIN as [$class, $action]) {
			$actions[$class][] = $action;
		}

		$controllers = [];
		foreach ($actions as $class => $names) {
			$mock = $this->createMock(originalClassName: $class);
			foreach ($names as $name) {
				$mock->method($name)->willReturn(new JSONResponse(['from' => $class . '::' . $name]));
			}

			$controllers[$class] = $mock;
		}

		$overrides = $this->createMock(originalClassName: OverridesController::class);
		$overrides->method('overridesPayload')->willReturnCallback(static fn (?string $tokenSet): array => ['overridesOf' => $tokenSet]);
		$controllers[OverridesController::class] = $overrides;

		$layer = $this->createMock(originalClassName: LayerController::class);
		$layer->method('getStylesheets')->willReturnCallback(static fn (string $tokenSetId): JSONResponse => new JSONResponse(['layersOf' => $tokenSetId]));
		$controllers[LayerController::class] = $layer;

		return $controllers;
	}//end answeringControllers()

	/**
	 * Each path the page asks carries its action's answer; the set-bound reads name the set.
	 *
	 * @return void
	 */
	public function testEveryBootReadIsAnsweredByItsRoutesAction(): void {
		$reads = (new AdminBootReads(
			container: $this->container(controllers: $this->answeringControllers()),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))->forTokenSet(tokenSetId: 'custom-open woo');

		$this->assertCount(expectedCount: 14, haystack: $reads);
		foreach (self::PLAIN as [$class, $action, $path]) {
			$this->assertSame(expected: ['from' => $class . '::' . $action], actual: $reads[$path]);
		}

		$this->assertSame(expected: ['overridesOf' => 'custom-open woo'], actual: $reads['/settings/overrides?tokenSet=custom-open%20woo']);
		$this->assertSame(expected: ['layersOf' => 'custom-open woo'], actual: $reads['/settings/tokenset-stylesheets/custom-open%20woo']);
	}//end testEveryBootReadIsAnsweredByItsRoutesAction()

	/**
	 * A read that throws or answers an error is left out, and the others still come.
	 *
	 * @return void
	 */
	public function testAFailingReadIsLeftToThePage(): void {
		$controllers = $this->answeringControllers();

		$fonts = $this->createMock(originalClassName: FontController::class);
		$fonts->method('list')->willThrowException(new RuntimeException('disk gone'));
		$fonts->method('library')->willReturn(new JSONResponse(['error' => 'nope'], 500));
		$controllers[FontController::class] = $fonts;

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('debug')->with($this->stringContains('/settings/fonts'));

		$reads = (new AdminBootReads(container: $this->container(controllers: $controllers), logger: $logger))->forTokenSet(tokenSetId: 'nextcloud');

		$this->assertArrayNotHasKey(key: '/settings/fonts', array: $reads);
		$this->assertArrayNotHasKey(key: '/settings/fonts/library', array: $reads);
		$this->assertArrayHasKey(key: '/settings/custom-css', array: $reads);
		$this->assertArrayHasKey(key: '/settings/overrides?tokenSet=nextcloud', array: $reads);
	}//end testAFailingReadIsLeftToThePage()
}//end class
