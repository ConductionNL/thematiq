<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\ScheduledCoreThemingSync;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The sync gives back an image slot an earlier set filled and the new set does not.
 *
 * @spec openspec/specs/platform-properties/spec.md#requirement-the-favicon-is-a-core-image-slot-the-theming-sync-may-fill-req-tplat-003
 */
class ScheduledCoreThemingSyncTest extends TestCase {

	/**
	 * The app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $store = [];

	/**
	 * The theming service double.
	 *
	 * @var ThemingService&MockObject
	 */
	private ThemingService&MockObject $theming;

	/**
	 * A sync over a set with only a logo and a primary colour.
	 *
	 * @return ScheduledCoreThemingSync The sync.
	 */
	private function make(): ScheduledCoreThemingSync {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->store[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->store[$key] = (string)$value;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			function (string $app, string $key): void {
				unset($this->store[$key]);
			}
		);

		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('getAvailableTokenSets')->willReturn(
			[['id' => 'amsterdam', 'theming' => ['primary_color' => '#004699', 'logo' => 'img/logos/amsterdam.svg']]]
		);

		$this->theming = $this->createMock(originalClassName: ThemingService::class);
		$this->theming->method('validateColors')->willReturn(null);
		$this->theming->method('validateImagePaths')->willReturn(null);
		$this->theming->method('applyImages')->willReturn(['logo']);
		$this->theming->method('applyColors')->willReturn(['primary_color']);

		return new ScheduledCoreThemingSync(
			themingService: $this->theming,
			tokenSets: $tokenSets,
			config: $config,
			branding: $this->createMock(originalClassName: BrandingCaptureService::class)
		);
	}//end make()

	/**
	 * The previous set's favicon, synced by thematiq, goes back to Nextcloud's own.
	 *
	 * @return void
	 */
	public function testASlotTheNewSetDoesNotFillIsGivenBack(): void {
		$this->store = ['synced_favicon' => 'img/logos/zuiddrecht-emblem.svg', 'synced_logo' => 'img/logos/zuiddrecht.svg'];
		$sync = $this->make();
		$this->theming->expects($this->once())->method('undoImage')->with('favicon');

		$written = $sync->sync(tokenSetId: 'amsterdam');

		$this->assertSame(expected: ['primary_color', 'logo', 'favicon'], actual: $written);
		$this->assertArrayNotHasKey(key: 'synced_favicon', array: $this->store);
		$this->assertSame(expected: 'img/logos/amsterdam.svg', actual: $this->store['synced_logo']);
	}//end testASlotTheNewSetDoesNotFillIsGivenBack()

	/**
	 * A slot thematiq never filled (an administrator's own upload) stays.
	 *
	 * @return void
	 */
	public function testASlotThematiqNeverFilledStays(): void {
		$this->store = [];
		$sync = $this->make();
		$this->theming->expects($this->never())->method('undoImage');

		$this->assertSame(expected: ['primary_color', 'logo'], actual: $sync->sync(tokenSetId: 'amsterdam'));
	}//end testASlotThematiqNeverFilledStays()
}//end class
