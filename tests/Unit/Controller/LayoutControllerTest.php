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

		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->stored[$key] = $value;
			}
		);

		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturnCallback(
			static function (string $tokenSetId): array {
				if ($tokenSetId === 'zuiddrecht') {
					return ['layout' => ['workplace_layout' => 'light', 'brand_stripe' => true]];
				}

				return [];
			}
		);

		$active = $this->createMock(originalClassName: ActiveTokenSetService::class);
		$active->method('getActive')->willReturn('zuiddrecht');

		$auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$auditService->method('log')->willReturnCallback(
			function (string $action, array $context = []): void {
				$this->audit[] = ['action' => $action, 'context' => $context];
			}
		);

		$this->controller = new LayoutController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			layoutOptions: new LayoutOptionsService(config: $config, designSystemService: $designSystems),
			activeTokenSet: $active,
			auditService: $auditService
		);
	}//end setUp()

	/**
	 * A choice is stored, audited, and answered with what the page resolves to.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 *
	 * @return void
	 */
	public function testAChoiceIsStoredAndAudited(): void {
		$response = $this->controller->save(workplaceLayout: 'default', brandStripe: '0');

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'default', actual: $this->stored['workplace_layout']);
		$this->assertSame(expected: '0', actual: $this->stored['brand_stripe']);
		$this->assertSame(
			expected: [
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
			actual: $response->getData()
		);
		$this->assertSame(
			expected: [
				['action' => 'toggle_changed', 'context' => ['key' => 'workplace_layout', 'old' => '', 'new' => 'default']],
				['action' => 'toggle_changed', 'context' => ['key' => 'brand_stripe', 'old' => '', 'new' => '0']],
			],
			actual: $this->audit
		);
	}//end testAChoiceIsStoredAndAudited()

	/**
	 * The empty choice follows the theme, and the answer says what that is.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testTheEmptyChoiceAnswersWithTheSetsDefaults(): void {
		$response = $this->controller->save(workplaceLayout: '', brandStripe: '');

		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => null,
				'navigationActiveStyle' => 'default',
				'brandStripePlacement' => 'header-and-login',
				'loginWatermark' => true,
				'headerStyle' => 'default',
			],
			actual: $response->getData()['resolved']
		);
		$this->assertSame(expected: [], actual: $this->audit, message: 'Storing what was already stored is not a change.');
	}//end testTheEmptyChoiceAnswersWithTheSetsDefaults()

	/**
	 * The newer options are stored and audited with the two older ones,
	 * and the answer resolves them for the active set.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 *
	 * @return void
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

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: '300', actual: $this->stored['navigation_width']);
		$this->assertSame(expected: 'soft', actual: $this->stored['navigation_active_style']);
		$this->assertSame(expected: 'login', actual: $this->stored['brand_stripe_placement']);
		$this->assertSame(expected: '0', actual: $this->stored['login_watermark']);
		$this->assertSame(expected: 'workplace', actual: $this->stored['header_style']);
		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => 300,
				'navigationActiveStyle' => 'soft',
				'brandStripePlacement' => 'login',
				'loginWatermark' => false,
				'headerStyle' => 'workplace',
			],
			actual: $response->getData()['resolved']
		);
		$this->assertSame(
			expected: [
				['action' => 'toggle_changed', 'context' => ['key' => 'navigation_width', 'old' => '', 'new' => '300']],
				['action' => 'toggle_changed', 'context' => ['key' => 'navigation_active_style', 'old' => '', 'new' => 'soft']],
				['action' => 'toggle_changed', 'context' => ['key' => 'brand_stripe_placement', 'old' => '', 'new' => 'login']],
				['action' => 'toggle_changed', 'context' => ['key' => 'login_watermark', 'old' => '', 'new' => '0']],
				['action' => 'toggle_changed', 'context' => ['key' => 'header_style', 'old' => '', 'new' => 'workplace']],
			],
			actual: $this->audit
		);
	}//end testTheNewerChoicesAreStoredAndAudited()

	/**
	 * One unknown value refuses the whole request: nothing is stored, nothing
	 * is audited.
	 *
	 * @param string $layout The workplace layout sent.
	 * @param string $stripe The brand stripe sent.
	 * @param array $newer The newer choices sent alongside, from the provider.
	 *
	 * @return void
	 *
	 * @dataProvider unknownValueProvider
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testAnUnknownValueStoresNothing(string $layout, string $stripe, array $newer = []): void {
		$response = $this->controller->save(...(['workplaceLayout' => $layout, 'brandStripe' => $stripe] + $newer));

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $this->stored);
		$this->assertSame(expected: [], actual: $this->audit);
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
	 *
	 * @return void
	 */
	public function testTheEndpointIsAdminOnly(): void {
		$attributes = (new ReflectionMethod(LayoutController::class, 'save'))->getAttributes(AuthorizedAdminSetting::class);

		$this->assertCount(expectedCount: 1, haystack: $attributes);
		$this->assertSame(expected: [Admin::class], actual: array_values($attributes[0]->getArguments()));
	}//end testTheEndpointIsAdminOnly()
}//end class
