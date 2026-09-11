<?php

/**
 * Unit tests for Settings\Admin::getForm() — the server half of the
 * initial-state contract.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * js/admin.js reads its server data through `OCP.InitialState.loadState()`.
 * If `getForm()` stops publishing one of those keys, the script silently
 * falls back to a default — an empty token-set list, no active preview — and
 * the panel still renders, which is precisely why this needs a test rather
 * than a reviewer's eye.
 *
 * The assertions are on the KEYS AND VALUES handed to `IInitialState`, not on
 * the TemplateResponse parameters. The template still receives these values
 * for the parts it renders server-side, so asserting there would pass even if
 * `provideInitialState()` were deleted outright.
 */
class AdminInitialStateTest extends TestCase {

	/**
	 * Token sets returned by the stub TokenSetService.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const TOKEN_SETS = [
		[
			'id' => 'nextcloud',
			'name' => 'Nextcloud',
			'design_system' => 'nldesign',
		],
		[
			'id' => 'lasuite',
			'name' => 'La Suite',
			'design_system' => 'lasuite',
		],
	];

	/**
	 * Build an Admin with every collaborator stubbed, capturing whatever it
	 * hands to IInitialState.
	 *
	 * @param array<string, mixed>|null $activePreview The preview the session user has, or null.
	 * @param string $iconPackOverride The appconfig `icon_pack` value.
	 * @param array<string, mixed> $captured Filled with key => value as provided.
	 *
	 * @return Admin The system under test.
	 */
	private function buildAdmin(?array $activePreview, string $iconPackOverride, array &$captured): Admin {
		$captured = [];

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use ($iconPackOverride) {
				if ($key === 'icon_pack') {
					return $iconPackOverride;
				}

				if ($key === 'token_set') {
					return 'lasuite';
				}

				return $default;
			}
		);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getAvailableTokenSets')->willReturn(self::TOKEN_SETS);

		$emailThemingService = $this->createMock(EmailThemingService::class);
		$emailThemingService->method('getState')->willReturn(['state' => 'disabled']);
		$emailThemingService->method('getFooterConfig')->willReturn(
			[
				'orgName' => '',
				'accessibilityUrl' => '',
				'privacyUrl' => '',
			]
		);

		$previewService = $this->createMock(ThemePreviewService::class);
		$previewService->method('getActivePreview')->willReturn($activePreview);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$designSystemService = $this->createMock(DesignSystemService::class);
		$designSystemService->method('resolveActiveIconPacks')->willReturn(['remixicon']);

		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			static function (string $key, $value) use (&$captured): void {
				$captured[$key] = $value;
			}
		);

		return new Admin(
			$config,
			$this->createMock(IL10N::class),
			$tokenSetService,
			$emailThemingService,
			$previewService,
			$userSession,
			$designSystemService,
			$initialState,
			$this->createMock(IRequest::class)
		);
	}//end buildAdmin()

	/**
	 * All four keys js/admin.js reads MUST be published, with the values the
	 * script expects. A missing key is not a crash — it is a silent fallback.
	 */
	public function testEveryKeyTheScriptReadsIsProvided(): void {
		$captured = [];
		$admin = $this->buildAdmin(null, '', $captured);

		$response = $admin->getForm();
		$this->assertInstanceOf(TemplateResponse::class, $response);

		$this->assertSame(
			['tokenSets', 'currentTokenSet', 'activePreview', 'iconPackSource'],
			array_keys($captured),
			'js/admin.js reads exactly these four keys; publishing fewer makes it fall back silently.'
		);

		$this->assertSame(self::TOKEN_SETS, $captured['tokenSets']);
		$this->assertSame('lasuite', $captured['currentTokenSet']);
		$this->assertSame('design-system', $captured['iconPackSource']);

		// NOT null. Nextcloud's InitialStateService accepts a scalar, an
		// array or a JsonSerializable and silently DISCARDS anything else,
		// logging `Invalid activePreview data provided`. Passing null there
		// publishes no key at all — and since `loadState`'s fallback is also
		// null, the panel still renders correctly and nothing but the server
		// log records that the contract was broken. This assertion is what
		// stops that from being reintroduced.
		$this->assertSame([], $captured['activePreview']);
	}//end testEveryKeyTheScriptReadsIsProvided()

	/**
	 * An active preview is published with the token set's DISPLAY NAME
	 * resolved from the inventory, which is what the banner renders.
	 */
	public function testActivePreviewIsPublishedWithResolvedName(): void {
		$captured = [];
		$admin = $this->buildAdmin(['tokenSet' => 'lasuite'], '', $captured);

		$admin->getForm();

		$this->assertSame(
			[
				'tokenSet' => 'lasuite',
				'name' => 'La Suite',
			],
			$captured['activePreview']
		);
	}//end testActivePreviewIsPublishedWithResolvedName()

	/**
	 * When the appconfig override alone decides the icon pack, the published
	 * source is `override` — the value js/admin.js uses to STOP re-deriving
	 * the indicator from the dropdown.
	 */
	public function testIconPackSourceReportsAnOverride(): void {
		$captured = [];
		$admin = $this->buildAdmin(null, 'remixicon', $captured);

		$admin->getForm();

		$this->assertSame('override', $captured['iconPackSource']);
	}//end testIconPackSourceReportsAnOverride()

	/**
	 * The template MUST NOT carry the retired `data-*` transports. Asserting
	 * on the item rather than on the panel rendering is deliberate: the panel
	 * renders correctly with BOTH mechanisms present, which is exactly the
	 * state this change exists to leave behind.
	 */
	public function testTemplateShipsNoServerDataAttributes(): void {
		$path = __DIR__ . '/../../../templates/settings/admin.php';
		$this->assertFileExists($path);

		$template = (string)file_get_contents($path);

		foreach (['data-token-sets=', 'data-current-token-set=', 'data-active-preview=', 'data-icon-pack-source='] as $attribute) {
			$this->assertStringNotContainsString(
				$attribute,
				$template,
				$attribute . ' is the transport ADR-004 rules out; it must not come back.'
			);
		}
	}//end testTemplateShipsNoServerDataAttributes()
}//end class
