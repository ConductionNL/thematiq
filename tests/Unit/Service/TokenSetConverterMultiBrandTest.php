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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
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
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-multi-brand-source-is-recognised-by-its-content
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
	 * @param string $key The brand.
	 *
	 * @return array<string, mixed> The converter result, plus the parsed declarations under `declarations`.
	 */
	private function convertBrand(string $content, string $key): array {
		$cut = (new MultiBrandSource())->cut(content: $content, key: $key);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$result = (new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))
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
		$brands = (new MultiBrandSource())->detectBrands(content: $this->fixture(name: 'three-themes.tokens.json'));

		$this->assertSame(expected: ['noord', 'zuid', 'oost'], actual: array_column($brands, 'key'));
		$this->assertSame(expected: ['Noord', 'Zuid', 'Oost'], actual: array_column($brands, 'name'));
		$this->assertSame(expected: 'brand', actual: $brands[0]['group']);
		$this->assertSame(expected: 3, actual: $brands[0]['tokenCount'], message: 'common holds two, the brand set one');
	}//end testDetectsTokensStudioThemes()

	/**
	 * Two brand classes in built CSS.
	 *
	 * @return void
	 */
	public function testDetectsBrandClasses(): void {
		$brands = (new MultiBrandSource())->detectBrands(content: $this->fixture(name: 'two-brand-classes.css'));

		$this->assertSame(
			expected: [['key' => 'noord', 'name' => 'Noord', 'tokenCount' => 2], ['key' => 'zuid', 'name' => 'Zuid', 'tokenCount' => 2]],
			actual: $brands
		);
	}//end testDetectsBrandClasses()

	/**
	 * `:root`, a modifier and a compound selector are not brands, so one brand class is one brand.
	 *
	 * @return void
	 */
	public function testRootAndModifierBlocksAreNotBrands(): void {
		$css = ":root { --a-b: 1px; }\n.noord-theme { --a-c: red; }\n.zuid-theme--dark { --a-c: blue; }\n.zuid-theme .x { --a-c: green; }\n";

		$this->assertSame(expected: [], actual: (new MultiBrandSource())->detectBrands(content: $css));
	}//end testRootAndModifierBlocksAreNotBrands()

	/**
	 * A plain document, or a Tokens Studio file with one theme, is one brand.
	 *
	 * @return void
	 */
	public function testSingleBrandReturnsOne(): void {
		$source = new MultiBrandSource();

		$this->assertSame(expected: [], actual: $source->detectBrands(content: '{"color":{"primary":{"$type":"color","$value":"#154273"}}}'));
		$this->assertSame(expected: [], actual: $source->detectBrands(content: '{"$themes":[{"id":"a","name":"A","selectedTokenSets":{}}]}'));
		$this->assertSame(expected: [], actual: $source->detectBrands(content: ':root { --nldesign-color-primary: #154273; }'));
	}//end testSingleBrandReturnsOne()

	/**
	 * A CSS brand gets the shared blocks first and its own block winning.
	 *
	 * @return void
	 */
	public function testBrandInheritsSharedBlocks(): void {
		$cut = (new MultiBrandSource())->cut(content: $this->fixture(name: 'two-brand-classes.css'), key: 'zuid');

		$this->assertStringContainsString(needle: '--voorbeeld-radius: 4px', haystack: $cut['content']);
		$this->assertStringContainsString(needle: '--voorbeeld-color-primary: #c8102e', haystack: $cut['content']);
		$this->assertStringNotContainsString(needle: '#154273', haystack: $cut['content']);
		$this->assertStringNotContainsString(needle: '#5b9bd5', haystack: $cut['content'], message: 'the other brand\'s dark modifier stays out');
		$this->assertLessThan(expected: strpos($cut['content'], '.zuid-theme'), actual: strpos($cut['content'], ':root'));

		$values = $this->convertBrand(content: $this->fixture(name: 'two-brand-classes.css'), key: 'zuid')['css'];
		$this->assertStringContainsString(needle: '#c8102e', haystack: (string)$values);
	}//end testBrandInheritsSharedBlocks()

	/**
	 * Tokens Studio: the brand's own set wins over common, a disabled set stays out, and a
	 * source set resolves aliases without being emitted.
	 *
	 * @return void
	 */
	public function testLaterTokenSetWins(): void {
		$source = $this->fixture(name: 'three-themes.tokens.json');
		$noord = $this->convertBrand(content: $source, key: 'noord');
		$zuid = $this->convertBrand(content: $source, key: 'zuid');

		$this->assertSame(expected: '#154273', actual: strtolower($noord['declarations']['--nldesign-color-primary']));
		$this->assertSame(expected: '#c8102e', actual: strtolower($zuid['declarations']['--nldesign-color-primary']));
		$this->assertSame(expected: '#ffffff', actual: strtolower((string)($noord['declarations']['--nldesign-color-primary-text'] ?? '')));

		$cut = (new MultiBrandSource())->cut(content: $source, key: 'noord');
		$this->assertContains(needle: 'color.blue', haystack: $cut['referenceOnlyPaths']);
		$this->assertNotContains(needle: 'color.primary', haystack: $cut['referenceOnlyPaths']);
		$skipped = array_column(array_filter($noord['report'], static fn (array $e): bool => $e['action'] === 'skipped'), 'source');
		$this->assertNotContains(needle: 'color.blue', haystack: $skipped, message: 'a source-only token is not reported as skipped');
	}//end testLaterTokenSetWins()

	/**
	 * The legacy `value` form goes to the Style Dictionary input.
	 *
	 * @return void
	 */
	public function testLegacyFormatRoutesToInputC(): void {
		$result = $this->convertBrand(content: $this->fixture(name: 'three-themes-legacy.tokens.json'), key: 'zuid');

		$this->assertSame(expected: 'C', actual: $result['inputKind']);
		$this->assertStringContainsString(needle: '#c8102e', haystack: (string)$result['css']);
		$this->assertStringNotContainsString(
			needle: '#2e7d32',
			haystack: (string)$result['css'],
			message: 'core is source-only: its green is not emitted'
		);
	}//end testLegacyFormatRoutesToInputC()

	/**
	 * Task 2.4: expected.json is what PHP returns; tests/vitest/tokenConverterMultiBrand.spec.js
	 * holds the JS mirror to the same file.
	 *
	 * @return void
	 */
	public function testParityFixtureMatchesPhp(): void {
		$expected = json_decode($this->fixture(name: 'expected.json'), true);
		$source = new MultiBrandSource();
		foreach ($expected as $file => $answer) {
			$content = $this->fixture(name: $file);
			$brands = $source->detectBrands(content: $content);
			$cuts = [];
			foreach ($brands as $brand) {
				$cuts[$brand['key']] = $source->cut(content: $content, key: $brand['key']);
			}

			$this->assertSame(
				expected: $answer,
				actual: json_decode((string)json_encode(['brands' => $brands, 'cuts' => $cuts]), true),
				message: $file
			);
		}
	}//end testParityFixtureMatchesPhp()
}//end class
