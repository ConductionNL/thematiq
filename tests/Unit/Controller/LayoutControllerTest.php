<?php

/**
 * Unit tests for LayoutController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Controller
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\LayoutController;
use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * `POST /settings/layout` through the REAL LayoutOptionsService, so what is
 * asserted is the stored value and the resolved answer, not a mock's echo.
 */
class LayoutControllerTest extends TestCase {

	/**
	 * The stored app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The audit entries the controller wrote.
	 *
	 * @var array<int, array{action: string, context: array<string, mixed>}>
	 */
	private array $audit = [];

	/**
	 * The controller under test.
	 *
	 * @var LayoutController
	 */
	private LayoutController $controller;

	/**
	 * Build the controller on a remembering config mock, with `zuiddrecht`
	 * (which carries both layout defaults) as the active set.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];
		$this->audit = [];

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->stored[$key] = $value;
			}
		);

		$designSystems = $this->createMock(DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturnCallback(
			static function (string $tokenSetId): array {
				if ($tokenSetId === 'zuiddrecht') {
					return ['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]];
				}

				return [];
			}
		);

		$active = $this->createMock(ActiveTokenSetService::class);
		$active->method('getActive')->willReturn('zuiddrecht');

		$auditService = $this->createMock(ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = ['action' => $action, 'context' => $context];
			}
		);

		$this->controller = new LayoutController(
			'thematiq',
			$this->createMock(IRequest::class),
			new LayoutOptionsService(config: $config, designSystemService: $designSystems),
			$active,
			$auditService
		);
	}//end setUp()

	/**
	 * A choice is stored, audited, and answered with what the page resolves to.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testAChoiceIsStoredAndAudited(): void {
		$response = $this->controller->save(workplaceLayout: 'default', brandStripe: '0');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('default', $this->stored['workplace_layout']);
		$this->assertSame('0', $this->stored['brand_stripe']);
		$this->assertSame(
			[
				'status' => 'ok',
				'workplaceLayout' => 'default',
				'brandStripe' => '0',
				'navigationWidth' => '',
				'navigationActiveStyle' => '',
				'brandStripePlacement' => '',
				'loginWatermark' => '',
				'headerStyle' => '',
				'resolved' => [
					'workplaceLayout' => 'default',
					'brandStripe' => false,
					'navigationWidth' => null,
					'navigationActiveStyle' => 'default',
					'brandStripePlacement' => 'header-and-login',
					'loginWatermark' => true,
					'headerStyle' => 'default',
				],
			],
			$response->getData()
		);
		$this->assertSame(
			[
				['action' => 'toggle_changed', 'context' => ['key' => 'workplace_layout', 'old' => '', 'new' => 'default']],
				['action' => 'toggle_changed', 'context' => ['key' => 'brand_stripe', 'old' => '', 'new' => '0']],
			],
			$this->audit
		);
	}//end testAChoiceIsStoredAndAudited()

	/**
	 * The empty choice follows the theme, and the answer says what that is.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function testTheEmptyChoiceAnswersWithTheSetsDefaults(): void {
		$response = $this->controller->save(workplaceLayout: '', brandStripe: '');

		$this->assertSame(
			[
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => null,
				'navigationActiveStyle' => 'default',
				'brandStripePlacement' => 'header-and-login',
				'loginWatermark' => true,
				'headerStyle' => 'default',
			],
			$response->getData()['resolved']
		);
		$this->assertSame([], $this->audit, 'Storing what was already stored is not a change.');
	}//end testTheEmptyChoiceAnswersWithTheSetsDefaults()

	/**
	 * The newer options are stored and audited with the two older ones,
	 * and the answer resolves them for the active set.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function testTheNewerChoicesAreStoredAndAudited(): void {
		$response = $this->controller->save(
			workplaceLayout: '',
			brandStripe: '',
			navigationWidth: '300',
			navigationActiveStyle: 'soft',
			brandStripePlacement: 'login',
			loginWatermark: '0',
			headerStyle: 'workplace'
		);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('300', $this->stored['navigation_width']);
		$this->assertSame('soft', $this->stored['navigation_active_style']);
		$this->assertSame('login', $this->stored['brand_stripe_placement']);
		$this->assertSame('0', $this->stored['login_watermark']);
		$this->assertSame('workplace', $this->stored['header_style']);
		$this->assertSame(
			[
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => 300,
				'navigationActiveStyle' => 'soft',
				'brandStripePlacement' => 'login',
				'loginWatermark' => false,
				'headerStyle' => 'workplace',
			],
			$response->getData()['resolved']
		);
		$this->assertSame(
			[
				['action' => 'toggle_changed', 'context' => ['key' => 'navigation_width', 'old' => '', 'new' => '300']],
				['action' => 'toggle_changed', 'context' => ['key' => 'navigation_active_style', 'old' => '', 'new' => 'soft']],
				['action' => 'toggle_changed', 'context' => ['key' => 'brand_stripe_placement', 'old' => '', 'new' => 'login']],
				['action' => 'toggle_changed', 'context' => ['key' => 'login_watermark', 'old' => '', 'new' => '0']],
				['action' => 'toggle_changed', 'context' => ['key' => 'header_style', 'old' => '', 'new' => 'workplace']],
			],
			$this->audit
		);
	}//end testTheNewerChoicesAreStoredAndAudited()

	/**
	 * One unknown value refuses the whole request: nothing is stored, nothing
	 * is audited.
	 *
	 * @param string $layout The workplace layout sent.
	 * @param string $stripe The brand stripe sent.
	 *
	 * @return void
	 *
	 * @dataProvider unknownValueProvider
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testAnUnknownValueStoresNothing(string $layout, string $stripe, array $newer = []): void {
		$response = $this->controller->save(...(['workplaceLayout' => $layout, 'brandStripe' => $stripe] + $newer));

		$this->assertSame(400, $response->getStatus());
		$this->assertSame([], $this->stored);
		$this->assertSame([], $this->audit);
	}//end testAnUnknownValueStoresNothing()

	/**
	 * Requests with one value outside the allowed three.
	 *
	 * @return array<string, array{0: string, 1: string, 2?: array<string, string>}>
	 */
	public static function unknownValueProvider(): array {
		return [
			'unknown layout' => ['wide', '1'],
			'unknown stripe beside a good layout' => ['light', 'true'],
			'a width outside the range beside good values' => ['light', '1', ['navigationWidth' => '50']],
			'an unknown entry style' => ['', '', ['navigationActiveStyle' => 'bold']],
			'an unknown placement' => ['', '', ['brandStripePlacement' => 'footer']],
			'a watermark that is not a flag' => ['', '', ['loginWatermark' => 'yes']],
			'an unknown header style' => ['', '', ['headerStyle' => 'dark']],
		];
	}//end unknownValueProvider()

	/**
	 * The endpoint is delegated-admin only, like every other settings write.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testTheEndpointIsAdminOnly(): void {
		$attributes = (new ReflectionMethod(LayoutController::class, 'save'))->getAttributes(AuthorizedAdminSetting::class);

		$this->assertCount(1, $attributes);
		$this->assertSame([Admin::class], array_values($attributes[0]->getArguments()));
	}//end testTheEndpointIsAdminOnly()
}//end class
