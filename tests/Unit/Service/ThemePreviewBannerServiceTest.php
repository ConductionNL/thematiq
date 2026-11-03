<?php

/**
 * Unit tests for ThemePreviewBannerService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-banner
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ThemePreviewBannerService;
use OCA\Thematiq\Service\ThemePreviewService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the theme-preview banner injector.
 *
 * `emitPreviewAssets()` delegates to `\OCP\Util`, which needs a full Nextcloud
 * bootstrap this suite does not have, so it is overridden via a partial mock
 * and its invocation counted instead.
 */
class ThemePreviewBannerServiceTest extends TestCase {

	/**
	 * The theme preview service mock.
	 *
	 * @var ThemePreviewService&MockObject
	 */
	private $previewService;

	/**
	 * The user session mock.
	 *
	 * @var IUserSession&MockObject
	 */
	private $userSession;

	/**
	 * The initial state mock (preview banner payload).
	 *
	 * @var IInitialState&MockObject
	 */
	private $initialState;

	/**
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->previewService = $this->createMock(originalClassName: ThemePreviewService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->initialState = $this->createMock(originalClassName: IInitialState::class);
	}//end setUp()

	/**
	 * Build the service under test with `emitPreviewAssets()` stubbed.
	 *
	 * @param integer $assetCalls Populated with the emitPreviewAssets() call count.
	 *
	 * @return ThemePreviewBannerService&MockObject The service under test.
	 */
	private function buildService(int &$assetCalls): ThemePreviewBannerService {
		$service = $this->getMockBuilder(className: ThemePreviewBannerService::class)
			->setConstructorArgs([$this->previewService, $this->userSession, $this->initialState])
			->onlyMethods(['emitPreviewAssets'])
			->getMock();

		$service->method('emitPreviewAssets')->willReturnCallback(
			function () use (&$assetCalls) {
				$assetCalls++;
			}
		);

		return $service;
	}//end buildService()

	/**
	 * An active preview emits the banner assets and provides its initial state.
	 */
	public function testActivePreviewEmitsAssetsAndState(): void {
		$this->previewService->method('resolveEffectiveTokenSet')->willReturn(
			[
				'previewActive' => true,
				'expiresAt' => 1234,
			]
		);

		$this->initialState->expects($this->once())->method('provideInitialState')->with(
			'preview',
			[
				'tokenSet' => 'rijkshuisstijl',
				'name' => 'Rijkshuisstijl',
				'expiresAt' => 1234,
			]
		);

		$assetCalls = 0;
		$service = $this->buildService(assetCalls: $assetCalls);
		$service->inject(tokenSet: 'rijkshuisstijl', tokenSetMeta: ['name' => 'Rijkshuisstijl']);

		$this->assertSame(expected: 1, actual: $assetCalls);
	}//end testActivePreviewEmitsAssetsAndState()

	/**
	 * Without an active preview nothing at all is emitted.
	 */
	public function testInactivePreviewEmitsNothing(): void {
		$this->previewService->method('resolveEffectiveTokenSet')->willReturn(['previewActive' => false]);
		$this->initialState->expects($this->never())->method('provideInitialState');

		$assetCalls = 0;
		$service = $this->buildService(assetCalls: $assetCalls);
		$service->inject(tokenSet: 'nextcloud', tokenSetMeta: []);

		$this->assertSame(expected: 0, actual: $assetCalls);
	}//end testInactivePreviewEmitsNothing()

	/**
	 * A throwing resolver fails open: no assets, no state, no exception.
	 */
	public function testResolverFailureFailsOpen(): void {
		$this->previewService->method('resolveEffectiveTokenSet')
			->willThrowException(new \RuntimeException('boom'));
		$this->initialState->expects($this->never())->method('provideInitialState');

		$assetCalls = 0;
		$service = $this->buildService(assetCalls: $assetCalls);
		$service->inject(tokenSet: 'nextcloud', tokenSetMeta: []);

		$this->assertSame(expected: 0, actual: $assetCalls);
	}//end testResolverFailureFailsOpen()

	/**
	 * The token set id is used as the display name when the metadata has none.
	 */
	public function testMissingNameFallsBackToTokenSetId(): void {
		$this->previewService->method('resolveEffectiveTokenSet')->willReturn(['previewActive' => true]);

		$this->initialState->expects($this->once())->method('provideInitialState')->with(
			'preview',
			[
				'tokenSet' => 'nextcloud',
				'name' => 'nextcloud',
				'expiresAt' => null,
			]
		);

		$assetCalls = 0;
		$service = $this->buildService(assetCalls: $assetCalls);
		$service->inject(tokenSet: 'nextcloud', tokenSetMeta: []);

		$this->assertSame(expected: 1, actual: $assetCalls);
	}//end testMissingNameFallsBackToTokenSetId()
}//end class
