<?php

/**
 * MultiBrandSource with the real converter: detecting brands in a Tokens Studio document and
 * in built CSS, and cutting one brand out as ordinary input.
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
 * @spec openspec/changes/authoring-multi-brand-token-source/tasks.md#task-2.1
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\MultiBrandSource;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Over the fixtures in tests/Unit/fixtures/multi-brand.
 *
 * @spec openspec/changes/authoring-multi-brand-token-source/tasks.md#task-2.1
 */
final class TokenSetConverterMultiBrandTest extends TestCase {

	/**
	 * A fixture's content.
	 *
	 * @param string $name The file name.
	 *
	 * @return string
	 */
	private function fixture(string $name): string {
		return (string)file_get_contents(\dirname(__DIR__) . '/fixtures/multi-brand/' . $name);
	}//end fixture()

	/**
	 * Convert one brand through the real converter.
	 *
	 * @param string $content The source.
	 * @param string $key     The brand.
	 *
	 * @return array<string, mixed> The converter result, plus the parsed declarations under `declarations`.
	 */
	private function convertBrand(string $content, string $key): array {
		$cut        = (new MultiBrandSource())->cut(content: $content, key: $key);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$result = (new TokenSetConverterService($appManager, new CssParserService(), new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class)))
			->convert(content: $cut['content'], slug: 'voorbeeld-' . $key, displayName: 'Voorbeeld ' . $key, referenceOnlyPaths: $cut['referenceOnlyPaths']);
		$result['declarations'] = (new CssParserService())->parseRootBlock(css: (string)$result['css']);

		return $result;
	}//end convertBrand()

	/**
	 * Three themes in a Tokens Studio document, with their names and group.
	 *
	 * @return void
	 */
	public function testDetectsTokensStudioThemes(): void {
		$brands = (new MultiBrandSource())->detectBrands(content: $this->fixture('three-themes.tokens.json'));

		$this->assertSame(['noord', 'zuid', 'oost'], array_column($brands, 'key'));
		$this->assertSame(['Noord', 'Zuid', 'Oost'], array_column($brands, 'name'));
		$this->assertSame('brand', $brands[0]['group']);
		$this->assertSame(3, $brands[0]['tokenCount'], 'common holds two, the brand set one');
	}//end testDetectsTokensStudioThemes()

	/**
	 * Two brand classes in built CSS.
	 *
	 * @return void
	 */
	public function testDetectsBrandClasses(): void {
		$brands = (new MultiBrandSource())->detectBrands(content: $this->fixture('two-brand-classes.css'));

		$this->assertSame([['key' => 'noord', 'name' => 'Noord', 'tokenCount' => 2], ['key' => 'zuid', 'name' => 'Zuid', 'tokenCount' => 2]], $brands);
	}//end testDetectsBrandClasses()

	/**
	 * `:root`, a modifier and a compound selector are not brands, so one brand class is one brand.
	 *
	 * @return void
	 */
	public function testRootAndModifierBlocksAreNotBrands(): void {
		$css = ":root { --a-b: 1px; }\n.noord-theme { --a-c: red; }\n.zuid-theme--dark { --a-c: blue; }\n.zuid-theme .x { --a-c: green; }\n";

		$this->assertSame([], (new MultiBrandSource())->detectBrands(content: $css));
	}//end testRootAndModifierBlocksAreNotBrands()

	/**
	 * A plain document, or a Tokens Studio file with one theme, is one brand.
	 *
	 * @return void
	 */
	public function testSingleBrandReturnsOne(): void {
		$source = new MultiBrandSource();

		$this->assertSame([], $source->detectBrands(content: '{"color":{"primary":{"$type":"color","$value":"#154273"}}}'));
		$this->assertSame([], $source->detectBrands(content: '{"$themes":[{"id":"a","name":"A","selectedTokenSets":{}}]}'));
		$this->assertSame([], $source->detectBrands(content: ':root { --nldesign-color-primary: #154273; }'));
	}//end testSingleBrandReturnsOne()

	/**
	 * A CSS brand gets the shared blocks first and its own block winning.
	 *
	 * @return void
	 */
	public function testBrandInheritsSharedBlocks(): void {
		$cut = (new MultiBrandSource())->cut(content: $this->fixture('two-brand-classes.css'), key: 'zuid');

		$this->assertStringContainsString('--voorbeeld-radius: 4px', $cut['content']);
		$this->assertStringContainsString('--voorbeeld-color-primary: #c8102e', $cut['content']);
		$this->assertStringNotContainsString('#154273', $cut['content']);
		$this->assertStringNotContainsString('#5b9bd5', $cut['content'], 'the other brand\'s dark modifier stays out');
		$this->assertLessThan(strpos($cut['content'], '.zuid-theme'), strpos($cut['content'], ':root'));

		$values = $this->convertBrand($this->fixture('two-brand-classes.css'), 'zuid')['css'];
		$this->assertStringContainsString('#c8102e', (string)$values);
	}//end testBrandInheritsSharedBlocks()

	/**
	 * Tokens Studio: the brand's own set wins over common, a disabled set stays out, and a
	 * source set resolves aliases without being emitted.
	 *
	 * @return void
	 */
	public function testLaterTokenSetWins(): void {
		$source = $this->fixture('three-themes.tokens.json');
		$noord  = $this->convertBrand($source, 'noord');
		$zuid   = $this->convertBrand($source, 'zuid');

		$this->assertSame('#154273', strtolower($noord['declarations']['--nldesign-color-primary']));
		$this->assertSame('#c8102e', strtolower($zuid['declarations']['--nldesign-color-primary']));
		$this->assertSame('#ffffff', strtolower((string)($noord['declarations']['--nldesign-color-primary-text'] ?? '')));

		$cut = (new MultiBrandSource())->cut(content: $source, key: 'noord');
		$this->assertContains('color.blue', $cut['referenceOnlyPaths']);
		$this->assertNotContains('color.primary', $cut['referenceOnlyPaths']);
		$skipped = array_column(array_filter($noord['report'], static fn (array $e): bool => $e['action'] === 'skipped'), 'source');
		$this->assertNotContains('color.blue', $skipped, 'a source-only token is not reported as skipped');
	}//end testLaterTokenSetWins()

	/**
	 * The legacy `value` form goes to the Style Dictionary input.
	 *
	 * @return void
	 */
	public function testLegacyFormatRoutesToInputC(): void {
		$result = $this->convertBrand($this->fixture('three-themes-legacy.tokens.json'), 'zuid');

		$this->assertSame('C', $result['inputKind']);
		$this->assertStringContainsString('#c8102e', (string)$result['css']);
		$this->assertStringNotContainsString('#2e7d32', (string)$result['css'], 'core is source-only: its green is not emitted');
	}//end testLegacyFormatRoutesToInputC()
}//end class
