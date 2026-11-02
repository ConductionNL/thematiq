<?php

/**
 * Golden test for Admin::getForm().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Settings
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Settings;

use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\PlaygroundStateService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Renders the admin form for seeded instances: each flag on, off or unset, the current token set
 * known, unknown or of another design system, a signed-in user with or without a preview (of a
 * known or unknown set), an icon pack override that matches or not, the mock switch, and an
 * environment marker. Records the template, its parameters, and every initial-state key in the
 * order it is published, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Settings\Admin
 * @uses   \OCA\Thematiq\Service\TokenSetSelectionPolicy
 */
class AdminFormGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/admin-form.golden.json';

	private const TOKEN_SETS = [
		['id' => 'nextcloud', 'name' => 'Nextcloud', 'design_system' => 'nldesign'],
		['id' => 'lasuite', 'name' => 'La Suite', 'design_system' => 'lasuite'],
		['id' => 'summer-breeze', 'name' => 'Summer Breeze', 'design_system' => 'summer-breeze'],
	];

	private const FLAGS = ['hide_slogan', 'show_menu_labels', 'primary_drives_components', 'confirm_save_stock', 'confirm_save_theme', 'dark_variants', 'marianne_enabled'];

	/**
	 * Every seeded instance renders as recorded.
	 *
	 * @return void
	 */
	public function testFormMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 120; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], $name);
		}
	}//end testFormMatchesTheGoldenRecording()

	/**
	 * One seeded render, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$values = [];
		foreach (self::FLAGS as $flag) {
			$roll = $random->getInt(0, 2);
			if ($roll < 2) {
				$values[$flag] = (string)$roll;
			}
		}

		$values['token_set'] = ['nextcloud', 'lasuite', 'summer-breeze', 'gone'][$random->getInt(0, 3)];
		if ($random->getInt(0, 4) === 0) {
			unset($values['token_set']);
		}

		$values['icon_pack'] = ['', 'remixicon', ' remixicon ', 'lucide'][$random->getInt(0, 3)];
		foreach (['workplace_layout', 'brand_stripe', 'header_style'] as $key) {
			if ($random->getInt(0, 1) === 0) {
				$values[$key] = 'x-' . $key;
			}
		}

		$environment = ['', ' Test ', 'production'][$random->getInt(0, 2)];
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturnCallback(
			static fn (string $key, string $default = '') => ($key === 'thematiq.environment' ? $environment : $default)
		);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => ($values[$key] ?? $default)
		);

		$tokenSetService = $this->createMock(TokenSetService::class);
		$tokenSetService->method('getAvailableTokenSets')->willReturn(self::TOKEN_SETS);
		$tokenSetService->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$email = $this->createMock(EmailThemingService::class);
		$email->method('getState')->willReturn(['state' => ['enabled', 'disabled'][$random->getInt(0, 1)]]);
		$email->method('getFooterConfig')->willReturn(['orgName' => 'Zuid', 'accessibilityUrl' => '', 'privacyUrl' => '']);

		$preview = [null, ['tokenSet' => 'lasuite'], ['tokenSet' => 'gone']][$random->getInt(0, 2)];
		$previewService = $this->createMock(ThemePreviewService::class);
		$previewService->method('getActivePreview')->willReturn($preview);

		$userSession = $this->createMock(IUserSession::class);
		if ($random->getInt(0, 4) !== 0) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('admin');
			$userSession->method('getUser')->willReturn($user);
		}

		$designSystems = $this->createMock(DesignSystemService::class);
		$packs = [['remixicon'], ['lucide', 'remixicon'], []][$random->getInt(0, 2)];
		$designSystems->method('resolveActiveIconPacks')->willReturnCallback(
			static fn (string $tokenSetId): array => ($tokenSetId === 'lasuite') ? ['lucide'] : $packs
		);
		$designSystems->method('getDesignSystems')->willReturn(
			[
				'nldesign' => ['id' => 'nldesign', 'documentation_url' => 'https://nldesignsystem.nl'],
				'lasuite' => ['id' => 'lasuite', 'documentation_url' => 'http://insecure.example'],
				'none' => ['id' => 'none'],
			]
		);

		$published = [];
		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			static function (string $key, $value) use (&$published): void {
				$published[] = [$key, $value];
			}
		);

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturn(['1', null, '0'][$random->getInt(0, 2)]);

		$playground = $this->createMock(PlaygroundStateService::class);
		$playground->method('getInitialState')->willReturnCallback(
			static fn (string $tokenSetId): array => ['playgroundSet' => $tokenSetId, 'playgroundVersion' => 34]
		);

		$admin = new Admin($config, $tokenSetService, $email, $previewService, $userSession, $designSystems, $initialState, $request, $playground);
		$response = $admin->getForm();

		return (string)json_encode(
			['template' => $response->getTemplateName(), 'params' => $response->getParams(), 'published' => $published],
			JSON_UNESCAPED_SLASHES
		);
	}//end runCase()
}//end class
