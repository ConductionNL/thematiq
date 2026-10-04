<?php

/**
 * Unit tests for CssParserService::parseDarkBlock().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use PHPUnit\Framework\TestCase;

/**
 * Covers the hand-authored dark-block extraction contract (tasks.md#task-1.3,
 * task-5.2): present, absent, extra-braces-nearby, and malformed CSS.
 */
class CssParserServiceTest extends TestCase {

	/**
	 * The parser under test.
	 *
	 * @var CssParserService
	 */
	private CssParserService $parser;

	/**
	 * Set up the parser.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->parser = new CssParserService();
	}//end setUp()

	/**
	 * A present dark block yields its declarations.
	 */
	public function testDarkBlockPresent(): void {
		$css = ":root {\n\t--nldesign-color-primary: #154273;\n}\n\n"
			. "@media (prefers-color-scheme: dark) {\n"
			. "\t:root {\n"
			. "\t\t--nldesign-color-primary: #4844AD;\n"
			. "\t\t--nldesign-color-background: #171717;\n"
			. "\t}\n"
			. '}';

		$result = $this->parser->parseDarkBlock(css: $css);

		$this->assertSame(
			[
				'--nldesign-color-primary' => '#4844AD',
				'--nldesign-color-background' => '#171717',
			],
			$result
		);
	}//end testDarkBlockPresent()

	/**
	 * A token set with no dark block returns an empty map — never throws.
	 */
	public function testDarkBlockAbsent(): void {
		$css = ":root {\n\t--nldesign-color-primary: #154273;\n}";

		$this->assertSame([], $this->parser->parseDarkBlock(css: $css));
	}//end testDarkBlockAbsent()

	/**
	 * Unrelated brace blocks elsewhere in the file (a light `:root {}` block,
	 * another `@media` rule) do not confuse extraction of the dark block.
	 */
	public function testDarkBlockWithNestedBracesElsewhereInFile(): void {
		$css = ":root {\n\t--nldesign-color-primary: #154273;\n}\n\n"
			. "@media (min-width: 768px) {\n\t.foo { color: red; }\n}\n\n"
			. "@media (prefers-color-scheme: dark) {\n"
			. "\t:root {\n"
			. "\t\t--nldesign-color-primary: #4844AD;\n"
			. "\t}\n"
			. '}';

		$result = $this->parser->parseDarkBlock(css: $css);

		$this->assertSame(['--nldesign-color-primary' => '#4844AD'], $result);
	}//end testDarkBlockWithNestedBracesElsewhereInFile()

	/**
	 * Malformed CSS (unclosed dark block) degrades to an empty map, never an exception.
	 */
	public function testMalformedDarkBlockDegradesToEmpty(): void {
		$css = "@media (prefers-color-scheme: dark) {\n\t:root {\n\t\t--nldesign-color-primary: #4844AD;";

		$this->assertSame([], $this->parser->parseDarkBlock(css: $css));
	}//end testMalformedDarkBlockDegradesToEmpty()

	/**
	 * Empty input degrades to an empty map.
	 */
	public function testEmptyInputDegradesToEmpty(): void {
		$this->assertSame([], $this->parser->parseDarkBlock(css: ''));
	}//end testEmptyInputDegradesToEmpty()

	/**
	 * A comment inside `:root` that holds braces does not end the block early. defaults.css
	 * carries `{normal,bold}` in a comment at line 156, which cut every token after it.
	 *
	 * @return void
	 */
	public function testBracesInACommentDoNotEndTheRootBlock(): void {
		$css = ":root {\n\t--a: 1;\n\t/* the {normal,bold} weights */\n\t--b: 2;\n}\n";

		$this->assertSame(['--a' => '1', '--b' => '2'], $this->parser->parseRootBlock(css: $css));
	}//end testBracesInACommentDoNotEndTheRootBlock()

	/**
	 * The real defaults layer is read whole: its last `:root` tokens are present.
	 *
	 * @return void
	 */
	public function testDefaultsLayerIsReadWhole(): void {
		$root = $this->parser->parseRootBlock(css: (string)file_get_contents(\dirname(__DIR__, 3) . '/css/systems/nldesign/defaults.css'));

		$this->assertSame('0', ($root['--nldesign-border-radius'] ?? null));
		$this->assertSame('4px', ($root['--nldesign-border-radius-large'] ?? null));
	}//end testDefaultsLayerIsReadWhole()

	/**
	 * A literal value is returned unchanged, and `unresolved` stays null.
	 *
	 * @return void
	 */
	public function testALiteralResolvesToItself(): void {
		$this->assertSame(
			['value' => '#21468B', 'unresolved' => null],
			$this->parser->resolveVarChain(value: ' #21468B ', declarations: [])
		);
		$this->assertSame(
			['value' => 'rgba(0, 0, 0, .5)', 'unresolved' => null],
			$this->parser->resolveVarChain(value: 'rgba(0, 0, 0, .5)', declarations: [])
		);
	}//end testALiteralResolvesToItself()

	/**
	 * A `var()` chain is followed to its literal, two hops deep — the longest
	 * chain any shipped set has (conduction-new's
	 * `--nldesign-color-primary` -> `--conduction-color-brand-primary` ->
	 * `--c-blue-cobalt`).
	 *
	 * @return void
	 */
	public function testAVarChainIsFollowedToItsLiteral(): void {
		$declarations = [
			'--nldesign-color-primary' => 'var(--conduction-color-brand-primary)',
			'--conduction-color-brand-primary' => 'var(--c-blue-cobalt)',
			'--c-blue-cobalt' => '#21468B',
		];

		$this->assertSame(
			['value' => '#21468B', 'unresolved' => null],
			$this->parser->resolveVarChain(
				value: $declarations['--nldesign-color-primary'],
				declarations: $declarations
			)
		);
	}//end testAVarChainIsFollowedToItsLiteral()

	/**
	 * A fallback is taken only when the referenced token is absent, which is
	 * what a browser does — the declared value wins over the fallback.
	 *
	 * @return void
	 */
	public function testAFallbackIsTakenOnlyWhenTheTokenIsAbsent(): void {
		$this->assertSame(
			['value' => '#fff', 'unresolved' => null],
			$this->parser->resolveVarChain(value: 'var(--missing, #fff)', declarations: [])
		);
		$this->assertSame(
			['value' => '#000000', 'unresolved' => null],
			$this->parser->resolveVarChain(
				value: 'var(--present, #fff)',
				declarations: ['--present' => '#000000']
			)
		);
	}//end testAFallbackIsTakenOnlyWhenTheTokenIsAbsent()

	/**
	 * A reference nothing declares, and a cycle, are reported unresolved
	 * rather than guessed — the caller must be able to tell "not a colour"
	 * from a colour.
	 *
	 * @return void
	 */
	public function testAnUnresolvableChainIsReportedNotGuessed(): void {
		$this->assertSame(
			['value' => null, 'unresolved' => '--nowhere'],
			$this->parser->resolveVarChain(value: 'var(--nowhere)', declarations: [])
		);

		$cycle = ['--a' => 'var(--b)', '--b' => 'var(--a)'];
		$result = $this->parser->resolveVarChain(value: $cycle['--a'], declarations: $cycle);

		$this->assertNull($result['value'], 'A cycle must not resolve to a value.');
		$this->assertNotNull($result['unresolved'], 'A cycle must name the token it stopped on.');
	}//end testAnUnresolvableChainIsReportedNotGuessed()
}//end class
