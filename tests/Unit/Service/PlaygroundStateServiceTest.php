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
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCP\App\IAppManager;
use OCP\IL10N;
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
		?StockTokensService $stockTokens = null,
		?IL10N $l10n = null,
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

		return new PlaygroundStateService(
			$appManager,
			$previewValues,
			$converter,
			($stockTokens ?? $this->createMock(StockTokensService::class)),
			($l10n ?? $this->identityL10n())
		);
	}//end build()

	/**
	 * An IL10N that answers with the English source, as an English locale does.
	 *
	 * @return IL10N The stub.
	 */
	private function identityL10n(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text): string => $text);

		return $l10n;
	}//end identityL10n()

	/**
	 * An IL10N that marks every string it is asked for, and remembers them.
	 *
	 * @param array<int, string> $asked Filled with every string passed to t().
	 *
	 * @return IL10N The stub.
	 */
	private function markingL10n(array &$asked): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			function (string $text) use (&$asked): string {
				$asked[] = $text;

				return 'NL:' . $text;
			}
		);

		return $l10n;
	}//end markingL10n()

	/**
	 * The panel's own words reach the admin in their language: every title,
	 * subtitle, paints note, token-less fact, reason and version note goes
	 * through IL10N, and the data around them (ids, token names, codes) does
	 * not.
	 */
	public function testTheInventoryChromeIsTranslatedAndItsDataIsNot(): void {
		$asked = [];
		$state = $this->build(l10n: $this->markingL10n($asked))->getInitialState(tokenSetId: 'nextcloud');
		$inventory = $state['playgroundInventory'];

		foreach ($inventory['components'] as $component) {
			$this->assertStringStartsWith('NL:', $component['title']);
			$this->assertStringStartsWith('NL:', $component['subtitle']);
			$this->assertStringStartsNotWith('NL:', $component['id']);
			foreach ($component['tokens'] ?? [] as $token) {
				$this->assertStringStartsWith('NL:', $token['paints'], $component['id']);
				$this->assertStringStartsWith('--', $token['name']);
			}
			foreach ($component['fixed'] ?? [] as $fixed) {
				$this->assertStringStartsWith('NL:', $fixed['what']);
				$this->assertStringStartsWith('NL:', $fixed['why']);
				$this->assertStringStartsNotWith('NL:', $fixed['code']);
			}
			foreach ($component['versionNotes'] ?? [] as $note) {
				$this->assertStringStartsWith('NL:', $note['text']);
			}
		}

		$this->assertSame(['derived-by-nextcloud' => 'NL:Calculated by Nextcloud.'], $state['playgroundReasons']);
	}//end testTheInventoryChromeIsTranslatedAndItsDataIsNot()

	/**
	 * Every chrome string the inventory and the mapping table carry has a key
	 * in the catalogue and a Dutch translation (ADR-007: Dutch required).
	 *
	 * The strings live in data files, so test:l10n, which scans t() calls in
	 * js/, cannot see them. This is the guard that can: a new title, paints
	 * note or reason without an l10n/en.json key and an l10n/nl.json value
	 * fails here.
	 */
	public function testEveryChromeStringIsInTheCatalogueWithADutchTranslation(): void {
		$root = __DIR__ . '/../../..';
		$mapping = json_decode((string)file_get_contents($root . '/scripts/mapping/nlds-to-nextcloud.json'), true);
		$converter = $this->createMock(TokenSetConverterService::class);
		$converter->method('getReasons')->willReturn($mapping['reasons']);

		$asked = [];
		$this->build(converter: $converter, l10n: $this->markingL10n($asked))->getInitialState(tokenSetId: 'nextcloud');
		$asked = array_values(array_unique($asked));

		$english = json_decode((string)file_get_contents($root . '/l10n/en.json'), true)['translations'];
		$dutch = json_decode((string)file_get_contents($root . '/l10n/nl.json'), true)['translations'];

		$this->assertGreaterThan(200, count($asked));
		$missing = [];
		foreach ($asked as $text) {
			if (isset($english[$text]) === false || is_string($dutch[$text] ?? null) === false || trim($dutch[$text]) === '') {
				$missing[] = $text;
			}
		}

		$this->assertSame([], $missing, 'Chrome strings with no l10n/en.json key or no Dutch translation in l10n/nl.json.');
	}//end testEveryChromeStringIsInTheCatalogueWithADutchTranslation()

	/**
	 * Exactly the keys js/playground.js reads, and no others.
	 */
	public function testItPublishesTheKeysTheInstrumentReads(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame(
			[
				'playgroundInventory',
				'playgroundReasons',
				'playgroundTokens',
				'playgroundExportTokens',
				'playgroundTokenSources',
				'playgroundVersion',
				'playgroundSet',
			],
			array_keys($state)
		);
	}//end testItPublishesTheKeysTheInstrumentReads()

	/**
	 * What an export writes is what the set DECLARES, never the map the
	 * instrument draws with.
	 *
	 * `getResolvedTokens()` merges `defaults.css` underneath the set so every
	 * token has a value to show — 200 of them, 115 component tokens carrying
	 * Rijkshuisstijl values. Exporting from that map wrote all of them into a
	 * theme whose author had changed one colour, which is the whole reason the
	 * two keys are separate.
	 */
	public function testTheExportBaseIsWhatTheSetDeclares(): void {
		$previewValues = $this->createMock(TokenSetPreviewService::class);
		$previewValues->method('getResolvedTokens')->willReturn(
			[
				'--nldesign-color-primary' => '#154273',
				'--nldesign-component-button-disabled-color' => '#696969',
			]
		);
		$previewValues->method('getDeclaredTokens')->willReturn(
			['--nldesign-color-primary' => '#154273']
		);
		$previewValues->method('getTokenSources')->willReturn([]);

		$state = $this->build($previewValues)->getInitialState(tokenSetId: 'rijkshuisstijl');

		$this->assertSame(['--nldesign-color-primary' => '#154273'], $state['playgroundExportTokens']);
		$this->assertArrayHasKey('--nldesign-component-button-disabled-color', $state['playgroundTokens']);
	}//end testTheExportBaseIsWhatTheSetDeclares()

	/**
	 * The stock set's values come from the RUNNING instance.
	 *
	 * `css/tokens/nextcloud.css` is a snapshot of one Nextcloud version, kept as
	 * a fallback — saving "the Nextcloud theme plus my change" has to write what
	 * this server is actually wearing.
	 */
	public function testTheStockSetExportsWhatTheInstanceIsWearing(): void {
		$previewValues = $this->createMock(TokenSetPreviewService::class);
		$previewValues->method('getResolvedTokens')->willReturn([]);
		$previewValues->method('getDeclaredTokens')->willReturn(
			['--nldesign-color-primary' => '#stale-snapshot']
		);
		$previewValues->method('getTokenSources')->willReturn([]);

		$stockTokens = $this->createMock(StockTokensService::class);
		$stockTokens->method('getTokens')->willReturn(['--nldesign-color-primary' => '#00679e']);

		$state = $this->build($previewValues, null, $stockTokens)
			->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame(['--nldesign-color-primary' => '#00679e'], $state['playgroundExportTokens']);
	}//end testTheStockSetExportsWhatTheInstanceIsWearing()

	/**
	 * When the instance cannot be read, the shipped snapshot answers rather than
	 * nothing — an export with no tokens is worse than one slightly behind.
	 */
	public function testTheStockSetFallsBackToTheShippedSnapshot(): void {
		$previewValues = $this->createMock(TokenSetPreviewService::class);
		$previewValues->method('getResolvedTokens')->willReturn([]);
		$previewValues->method('getDeclaredTokens')->willReturn(
			['--nldesign-color-primary' => '#0082c9']
		);
		$previewValues->method('getTokenSources')->willReturn([]);

		$stockTokens = $this->createMock(StockTokensService::class);
		$stockTokens->method('getTokens')->willReturn([]);

		$state = $this->build($previewValues, null, $stockTokens)
			->getInitialState(tokenSetId: 'nextcloud');

		$this->assertSame(['--nldesign-color-primary' => '#0082c9'], $state['playgroundExportTokens']);
	}//end testTheStockSetFallsBackToTheShippedSnapshot()

	/**
	 * The published set id is the one asked for, which is the set the page is
	 * WEARING — a session preview changes it, and an export is named after it.
	 */
	public function testItPublishesTheSetItWasAskedFor(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'openwoo');

		$this->assertSame('openwoo', $state['playgroundSet']);
	}//end testItPublishesTheSetItWasAskedFor()

	/**
	 * The version reaches the instrument as a number it can compare.
	 *
	 * The header specimen picks its markup by major version, so a string like
	 * "34.0.4" or a full array would silently fail every comparison and leave
	 * the switch opening on the wrong shape.
	 */
	public function testItPublishesTheServerMajorAsAnInteger(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'nextcloud');

		$this->assertIsInt($state['playgroundVersion']);
	}//end testItPublishesTheServerMajorAsAnInteger()

	/**
	 * Without a Nextcloud bootstrap the version is zero, not a crash.
	 *
	 * Zero is not a version the switch offers, so the instrument falls back to
	 * the newest header it knows — which is the right answer for an admin who
	 * is asking what an upgrade looks like anyway.
	 */
	public function testItSurvivesHavingNoServerToAsk(): void {
		$state = $this->build()->getInitialState(tokenSetId: 'nextcloud');

		$this->assertGreaterThanOrEqual(0, $state['playgroundVersion']);
	}//end testItSurvivesHavingNoServerToAsk()

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
			$this->createMock(TokenSetConverterService::class),
			$this->createMock(StockTokensService::class),
			$this->identityL10n()
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
