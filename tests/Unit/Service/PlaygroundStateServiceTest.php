<?php

/**
 * Unit tests for PlaygroundStateService — what the component playground reads
 * at boot, and what happens when a piece of it is missing.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\PlaygroundStateService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The instrument reads everything it renders through
 * `OCP.InitialState.loadState()`, and a key that goes missing does not crash
 * it: it simply does not build, and the token editor stays the plain four-tab
 * list it was. That is a good failure mode and a bad thing to discover by
 * looking, which is why the assembly is pinned here.
 */
class PlaygroundStateServiceTest extends TestCase {

	/**
	 * Build the service with the app path pointing at this repository, so the
	 * inventory read is the real shipped file.
	 *
	 * @param TokenSetPreviewService|null   $previewValues A stub, when the test needs its own.
	 * @param TokenSetConverterService|null $converter     A stub, when the test needs its own.
	 *
	 * @return PlaygroundStateService The service under test.
	 */
	private function build(
		?TokenSetPreviewService $previewValues = null,
		?TokenSetConverterService $converter = null,
	): PlaygroundStateService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn((string)realpath(__DIR__ . '/../../..'));

		if ($previewValues === null) {
			$previewValues = $this->createMock(TokenSetPreviewService::class);
			$previewValues->method('getResolvedTokens')->willReturn(['--nldesign-color-primary' => '#154273']);
			$previewValues->method('getTokenSources')->willReturn(['--color-primary' => '--nldesign-color-primary']);
		}

		if ($converter === null) {
			$converter = $this->createMock(TokenSetConverterService::class);
			$converter->method('getReasons')->willReturn(['derived-by-nextcloud' => 'Calculated by Nextcloud.']);
		}

		return new PlaygroundStateService($appManager, $previewValues, $converter);
	}//end build()

	/**
	 * Exactly the four keys js/playground.js reads, and no others.
	 */
	public function testItPublishesTheFourKeysTheInstrumentReads(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame(
			[
				'playgroundInventory',
				'playgroundReasons',
				'playgroundTokens',
				'playgroundTokenSources',
			],
			array_keys($state)
		);
	}//end testItPublishesTheFourKeysTheInstrumentReads()

	/**
	 * The inventory is the shipped file, decoded — the service adds nothing to
	 * it and drops nothing from it.
	 */
	public function testTheInventoryIsTheShippedFile(): void {
		$shipped = json_decode(
			(string)file_get_contents(__DIR__ . '/../../../js/playground/components.json'),
			true
		);

		$this->assertSame($shipped, $this->build()->getInventory());
		$this->assertNotEmpty($shipped['components']);
	}//end testTheInventoryIsTheShippedFile()

	/**
	 * Every component names a tab the inventory itself declares. A chip under a
	 * tab that does not exist is a chip no admin can ever reach.
	 */
	public function testEveryComponentSitsUnderADeclaredTab(): void {
		$inventory = $this->build()->getInventory();
		$tabs = array_column($inventory['tabs'], 'id');

		foreach ($inventory['components'] as $component) {
			$this->assertContains(
				$component['tab'],
				$tabs,
				$component['id'] . ' names a tab the inventory does not declare.'
			);
		}
	}//end testEveryComponentSitsUnderADeclaredTab()

	/**
	 * The tabs are the token editor's own four, in its own order: the instrument
	 * MOVES that strip rather than drawing a second one, so a tab here that the
	 * editor does not render is a chip row with nothing above it.
	 */
	public function testTheTabsAreTheTokenEditorsOwn(): void {
		$inventory = $this->build()->getInventory();

		$this->assertSame(
			['login', 'content', 'status', 'typography'],
			array_column($inventory['tabs'], 'id')
		);
	}//end testTheTabsAreTheTokenEditorsOwn()

	/**
	 * The reason vocabulary is the converter's, so a row with no token and a
	 * token an import had to skip are explained in the same words.
	 */
	public function testTheReasonVocabularyIsTheConvertersOwn(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame(['derived-by-nextcloud' => 'Calculated by Nextcloud.'], $state['playgroundReasons']);
	}//end testTheReasonVocabularyIsTheConvertersOwn()

	/**
	 * A missing mapping table costs the explanatory sentences and nothing else.
	 * The instrument still builds, and every row still names its reason CODE.
	 */
	public function testAMissingMappingTableCostsOnlyTheSentences(): void {
		$converter = $this->createMock(TokenSetConverterService::class);
		$converter->method('getReasons')->willThrowException(new RuntimeException('missing'));

		$state = $this->build(converter: $converter)->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame([], $state['playgroundReasons']);
		$this->assertNotEmpty($state['playgroundInventory']['components']);
	}//end testAMissingMappingTableCostsOnlyTheSentences()

	/**
	 * An unreadable inventory yields an empty component list rather than an
	 * exception: the instrument then does not build, and the token editor it
	 * would have rebuilt keeps working.
	 */
	public function testAnUnreadableInventoryYieldsAnEmptyOne(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(sys_get_temp_dir() . '/thematiq-does-not-exist');

		$service = new PlaygroundStateService(
			$appManager,
			$this->createMock(TokenSetPreviewService::class),
			$this->createMock(TokenSetConverterService::class)
		);

		$this->assertSame(['version' => 0, 'tabs' => [], 'components' => []], $service->getInventory());
	}//end testAnUnreadableInventoryYieldsAnEmptyOne()

	/**
	 * The token values describe the set that was asked for, and the source map
	 * comes from the stylesheet that actually makes the connection.
	 */
	public function testItResolvesTheSetItWasAskedFor(): void {
		$previewValues = $this->createMock(TokenSetPreviewService::class);
		$previewValues->expects($this->once())
			->method('getResolvedTokens')
			->with('custom-openwoo')
			->willReturn(['--nldesign-color-primary' => '#a90061']);
		$previewValues->method('getTokenSources')->willReturn(['--color-primary' => '--nldesign-color-primary']);

		$state = $this->build(previewValues: $previewValues)->getInitialState(tokenSetId: 'custom-openwoo');

		$this->assertSame(['--nldesign-color-primary' => '#a90061'], $state['playgroundTokens']);
		$this->assertSame(['--color-primary' => '--nldesign-color-primary'], $state['playgroundTokenSources']);
	}//end testItResolvesTheSetItWasAskedFor()
}//end class
