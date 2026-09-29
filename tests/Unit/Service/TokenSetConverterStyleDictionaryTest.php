<?php

/**
 * Unit tests for TokenSetConverterService's Style Dictionary input: the
 * `{"value": …}` document shape, as distinct from W3C DTCG's `$value`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Style Dictionary is one of the four shapes the importer accepts, and it is
 * the one distinguished from another by a single character: a DTCG document
 * marks its leaves `$value`, a Style Dictionary one marks them `value`. The
 * kind is decided from the CONTENT, never from the file name, so a
 * `tokens.json` that is really DTCG must still be read as DTCG — which is what
 * the detection assertion here is for.
 *
 * The rest is the leaf walk. A Style Dictionary tree carries no vocabulary of
 * its own: the path to a leaf IS the token name, so `color.Brand Primary`
 * becomes `--<slug>-color-brand-primary`. Three rules govern that, and each
 * has a way of going wrong that no colour check would catch:
 *
 *  - a name already in a COMPONENT vocabulary keeps it and is NOT re-prefixed,
 *    or the component layer that reads `--utrecht-*` stops finding it;
 *  - Style Dictionary metadata keys (`$schema`, `$description`) are not tokens
 *    and must not become declarations;
 *  - a `value` that is not a scalar is a group that happens to use the word,
 *    not a leaf, and must not be flattened into a declaration.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
class TokenSetConverterStyleDictionaryTest extends TestCase {

	/**
	 * The converter under test.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Build the converter against the repository root, which holds its
	 * mapping table and the two vocabulary stylesheets.
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Convert a decoded JSON document.
	 *
	 * @param array<string, mixed> $document The token document.
	 *
	 * @return array<string, mixed> The conversion result.
	 */
	private function convert(array $document): array {
		return $this->converter->convert(
			content: json_encode($document),
			slug: 'voorbeeld',
			displayName: 'Voorbeeld',
			sourceName: 'tokens.json',
			assetName: 'custom-voorbeeld'
		);
	}//end convert()

	/**
	 * The path to a leaf becomes the token name: segments are lowercased,
	 * non-alphanumerics collapse to single dashes, and the whole thing is
	 * prefixed with the set's own slug so it cannot collide with the app
	 * vocabulary.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testTheLeafPathBecomesASlugPrefixedTokenName(): void {
		$result = $this->convert(
			[
				'color' => [
					'Brand Primary' => ['value' => '#154273', 'type' => 'color'],
					'nested' => ['deep' => ['value' => '#ffffff']],
				],
			]
		);

		$this->assertSame('C', $result['inputKind']);
		$this->assertSame(2, $result['imported']);
		$this->assertStringContainsString('--voorbeeld-color-brand-primary: #154273;', $result['css']);
		$this->assertStringContainsString('--voorbeeld-color-nested-deep: #ffffff;', $result['css']);
	}//end testTheLeafPathBecomesASlugPrefixedTokenName()

	/**
	 * A path that already starts with a component vocabulary this app emits
	 * verbatim keeps that name. Prefixing it with the set's slug would move it
	 * out of the vocabulary `css/systems/nldesign/utrecht-bridge.css` reads,
	 * leaving a declaration nothing consumes.
	 *
	 * @param string $prefix The component vocabulary prefix, without dashes.
	 *
	 * @dataProvider componentPrefixProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAComponentVocabularyIsNotReprefixed(string $prefix): void {
		$result = $this->convert([$prefix => ['button' => ['border-radius' => ['value' => '4px']]]]);

		$this->assertStringContainsString('--' . $prefix . '-button-border-radius: 4px;', $result['css']);
		$this->assertStringNotContainsString('--voorbeeld-' . $prefix . '-button', $result['css']);
	}//end testAComponentVocabularyIsNotReprefixed()

	/**
	 * The component vocabularies, derived from the constant so a new one added
	 * there is exercised here without editing this list.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function componentPrefixProvider(): array {
		$cases = [];
		foreach (TokenSetConverterService::COMPONENT_PREFIXES as $prefix) {
			$bare = trim($prefix, '-');
			$cases[$bare] = [$bare];
		}

		return $cases;
	}//end componentPrefixProvider()

	/**
	 * `$`-prefixed keys are Style Dictionary metadata, not tokens. Walking
	 * into them would emit declarations named after a schema URL.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testMetadataKeysAreNotTokens(): void {
		$result = $this->convert(
			[
				'$schema' => 'https://example.invalid/style-dictionary.json',
				'$description' => 'Voorbeeld tokens',
				'color' => ['primary' => ['value' => '#154273']],
			]
		);

		$this->assertSame(1, $result['imported']);
		$this->assertStringNotContainsString('schema', $result['css']);
		$this->assertStringNotContainsString('example.invalid', $result['css']);
	}//end testMetadataKeysAreNotTokens()

	/**
	 * A `value` holding an array is a group that happens to use the word, not
	 * a leaf. Flattening it would produce a declaration whose value is the
	 * string "Array".
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testANonScalarValueIsNotALeaf(): void {
		$result = $this->convert(
			[
				'color' => ['primary' => ['value' => '#154273']],
				'shadow' => ['elevation' => ['value' => ['x' => 0, 'y' => 1]]],
			]
		);

		$this->assertSame(1, $result['imported']);
		$this->assertStringNotContainsString('Array', $result['css']);
		$this->assertStringNotContainsString('--voorbeeld-shadow-elevation:', $result['css']);
	}//end testANonScalarValueIsNotALeaf()

	/**
	 * A document carrying `$value` anywhere is DTCG, whatever the file is
	 * called — the shape decides, not the name. The two walks produce
	 * different names for the same tree, so reading one as the other would
	 * silently emit the wrong vocabulary.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testADtcgDocumentNamedTokensJsonIsStillReadAsDtcg(): void {
		$result = $this->convert(['color' => ['primary' => ['$type' => 'color', '$value' => '#154273']]]);

		$this->assertSame('B', $result['inputKind']);
	}//end testADtcgDocumentNamedTokensJsonIsStillReadAsDtcg()

	/**
	 * A JSON document that decodes but holds no leaf of either shape is
	 * refused with the 422 the upload endpoint turns into a message, not with
	 * an empty token set.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testADocumentWithNoLeavesIsRefused(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(422);

		$this->convert(['color' => ['primary' => ['description' => 'no value here']]]);
	}//end testADocumentWithNoLeavesIsRefused()

	/**
	 * The two DTCG-only diagnostic accessors report on the LAST conversion and
	 * are reset by the next one: they are read by the upload endpoint after
	 * `convert()` returns, so a stale warning from a previous upload would be
	 * attributed to the wrong document.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testDiagnosticsDescribeTheLastConversionOnly(): void {
		$deprecated = $this->convert(
			[
				'color' => [
					'primary' => [
						'$type' => 'color',
						'$value' => '#154273',
						'$deprecated' => 'Use color.brand.primary instead',
					],
				],
			]
		);

		$this->assertSame('B', $deprecated['inputKind']);
		$warnings = $this->converter->getImportWarnings();
		$this->assertCount(1, $warnings);
		$this->assertSame('color.primary', $warnings[0]['path']);
		$this->assertIsArray($this->converter->getMapperErrors());

		// A Style Dictionary document carries no DTCG diagnostics at all, so
		// the previous document's warning must not survive into it.
		$this->convert(['color' => ['primary' => ['value' => '#154273']]]);
		$this->assertSame([], $this->converter->getImportWarnings());
		$this->assertSame([], $this->converter->getMapperErrors());
	}//end testDiagnosticsDescribeTheLastConversionOnly()
}//end class
