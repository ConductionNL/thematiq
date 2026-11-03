<?php

/**
 * TokenSetConverterService, input by input (nlds-theme-converter task 5.3).
 *
 * Runs the real service against the app's own mapping table and vocabulary: no stubbed table,
 * no stubbed parser. The parity suite pins whole outputs; this one names each rule of the
 * token-set-converter spec on the PHP side, so a failure says which promise broke.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
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
 * Unit tests for TokenSetConverterService.
 */
class TokenSetConverterServiceTest extends TestCase {

	private TokenSetConverterService $converter;

	/**
	 * Build the converter against the app's own mapping table.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Convert content as slug `demo`.
	 *
	 * @param string $content The input.
	 * @param string $slug The slug.
	 *
	 * @return array<string, mixed> The result plus `decl`, the emitted declarations.
	 */
	private function convert(string $content, string $slug = 'demo'): array {
		$result = $this->converter->convert(content: $content, slug: $slug, displayName: 'Demo');
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $result['css']);
		preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
		$result['decl'] = [];
		foreach ($matches as $match) {
			$result['decl'][$match[1]] = trim($match[2]);
		}

		return $result;
	}//end convert()

	/**
	 * A theme block from a name => value map.
	 *
	 * @param array<string, string> $tokens The declarations.
	 *
	 * @return string
	 */
	private function theme(array $tokens): string {
		$lines = [];
		foreach ($tokens as $name => $value) {
			$lines[] = "\t" . $name . ': ' . $value . ';';
		}

		return ".demo-theme {\n" . implode("\n", $lines) . "\n}\n";
	}//end theme()

	/**
	 * The report entry for a source token.
	 *
	 * @param array<string, mixed> $result The conversion result.
	 * @param string $source The source token.
	 *
	 * @return array<string, mixed>|null
	 */
	private function entry(array $result, string $source): ?array {
		foreach ($result['report'] as $item) {
			if ($item['source'] === $source) {
				return $item;
			}
		}

		return null;
	}//end entry()

	/**
	 * Input A: a class-scoped block, its var() chain resolved to a literal.
	 *
	 * @return void
	 */
	public function testBuiltThemeCssIsInputAWithItsChainResolved(): void {
		$result = $this->convert(
			content: '.openwoo-theme { --utrecht-button-primary-action-background-color: var(--openwoo-color-primary); --openwoo-color-primary: #23845c; }',
			slug: 'openwoo'
		);

		$this->assertSame(expected: 'A', actual: $result['inputKind']);
		$this->assertSame(expected: '#23845c', actual: $result['decl']['--nldesign-color-primary']);
		$this->assertStringNotContainsString(needle: 'var(--openwoo-color-primary)', haystack: $result['css']);
	}//end testBuiltThemeCssIsInputAWithItsChainResolved()

	/**
	 * Input B: a DTCG document goes through DesignTokensMapper.
	 *
	 * @return void
	 */
	public function testADtcgDocumentIsInputB(): void {
		$result = $this->convert(content: '{"color": {"primary": {"$value": "#154273", "$type": "color"}}}');

		$this->assertSame(expected: 'B', actual: $result['inputKind']);
		$this->assertSame(expected: '#154273', actual: $result['decl']['--nldesign-color-primary']);
	}//end testADtcgDocumentIsInputB()

	/**
	 * Input C: a Style Dictionary tree, its alias resolved rather than emitted as braces.
	 *
	 * @return void
	 */
	public function testAStyleDictionaryTreeIsInputCWithItsAliasResolved(): void {
		$result = $this->convert(content: (string)json_encode([
			'brand' => ['red' => ['500' => ['value' => '#c8102e']]],
			'utrecht' => ['button' => ['primary-action' => ['background-color' => ['value' => '{brand.red.500}']]]],
		]));

		$this->assertSame(expected: 'C', actual: $result['inputKind']);
		$this->assertSame(expected: '#c8102e', actual: $result['decl']['--nldesign-color-primary']);
		$this->assertSame(expected: '#c8102e', actual: $result['decl']['--demo-brand-red-500']);
		$this->assertStringNotContainsString(needle: '{brand', haystack: $result['css']);
	}//end testAStyleDictionaryTreeIsInputCWithItsAliasResolved()

	/**
	 * Input D: an existing set runs add-only.
	 *
	 * @return void
	 */
	public function testAnExistingSetIsInputDAndKeepsItsValues(): void {
		$result = $this->convert(content: ':root { --nldesign-color-primary: #1b3d6b; --nldesign-animation-quick: 100ms; }');

		$this->assertSame(expected: 'D', actual: $result['inputKind']);
		$this->assertSame(expected: '#1b3d6b', actual: $result['decl']['--nldesign-color-primary']);
		$this->assertSame(expected: '100ms', actual: $result['decl']['--nldesign-animation-quick']);
		$this->assertSame(expected: 'kept-existing-value', actual: $this->entry(result: $result, source: '--nldesign-animation-quick')['reason']);
	}//end testAnExistingSetIsInputDAndKeepsItsValues()

	/**
	 * Content matching no accepted shape is refused with a 422 and nothing to store.
	 *
	 * @return void
	 */
	public function testUnrecognisedContentIsRefused(): void {
		try {
			$this->converter->convert(content: 'this is not a theme at all', slug: 'demo', displayName: 'Demo');
			$this->fail(message: 'Unrecognised content was converted.');
		} catch (RuntimeException $exception) {
			$this->assertSame(expected: 422, actual: $exception->getCode());
		}
	}//end testUnrecognisedContentIsRefused()

	/**
	 * The contrast guard darkens a primary that fails its declared text colour.
	 *
	 * @return void
	 */
	public function testTheContrastGuardDarkensAFailingPrimary(): void {
		$result = $this->convert(content: $this->theme(tokens: [
			'--utrecht-button-primary-action-background-color' => '#7fb3e0',
			'--utrecht-button-primary-action-color' => '#ffffff',
		]));

		$contrast = new ContrastService();
		$ratio = $contrast->ratio(
			first: $contrast->parseColor(value: $result['decl']['--nldesign-color-primary']),
			second: $contrast->parseColor(value: $result['decl']['--nldesign-color-primary-text'])
		);

		$this->assertGreaterThanOrEqual(expected: 4.5, actual: $ratio);
		$primary = null;
		foreach ($result['report'] as $item) {
			if ($item['target'] === '--nldesign-color-primary') {
				$primary = $item;
			}
		}

		$this->assertSame(expected: 'contrast-adjusted', actual: $primary['reason']);
		$this->assertSame(expected: '#7fb3e0', actual: $primary['original']);
	}//end testTheContrastGuardDarkensAFailingPrimary()

	/**
	 * A theme without an action colour gets none: the action rules have no fallback from the primary.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/action-colour/spec.md#requirement-an-imported-theme-may-bring-an-action-colour
	 */
	public function testAThemeWithoutAnActionColourGetsNone(): void {
		$result = $this->convert(content: $this->theme(tokens: [
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-button-primary-action-color' => '#ffffff',
			'--utrecht-button-primary-action-hover-background-color' => '#0f2f52',
		]));

		$this->assertSame(expected: '#154273', actual: $result['decl']['--nldesign-color-primary']);
		foreach (array_keys($result['decl']) as $name) {
			$this->assertStringNotContainsString(
				needle: 'color-action',
				haystack: $name,
				message: 'the Utrecht button colour is the primary, not an action colour'
			);
		}
	}//end testAThemeWithoutAnActionColourGetsNone()

	/**
	 * A theme that names an action colour gets it, with a readable text and its own hover when it names one.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/action-colour/spec.md#requirement-an-imported-theme-may-bring-an-action-colour
	 */
	public function testAThemesActionColourIsConverted(): void {
		$bare = $this->convert(content: $this->theme(tokens: [
			'--demo-color-primary' => '#1a67a3',
			'--demo-color-action' => '#116329',
		]));

		$this->assertSame(expected: '#1a67a3', actual: $bare['decl']['--nldesign-color-primary']);
		$this->assertSame(expected: '#116329', actual: $bare['decl']['--nldesign-color-action']);
		$this->assertSame(
			expected: '#ffffff',
			actual: $bare['decl']['--nldesign-color-action-text'],
			message: 'a dark green: the text is derived white, by the rule the primary text uses'
		);
		$this->assertArrayNotHasKey(
			key: '--nldesign-color-action-hover',
			array: $bare['decl'],
			message: 'the stylesheet darkens the action colour itself'
		);

		$full = $this->convert(content: $this->theme(tokens: [
			'--demo-color-primary' => '#1a67a3',
			'--demo-color-action' => '#ffd43b',
			'--demo-color-action-hover' => '#fcc419',
			'--demo-color-action-text' => '#212529',
		]));

		$this->assertSame(expected: '#ffd43b', actual: $full['decl']['--nldesign-color-action']);
		$this->assertSame(expected: '#fcc419', actual: $full['decl']['--nldesign-color-action-hover']);
		$this->assertSame(expected: '#212529', actual: $full['decl']['--nldesign-color-action-text']);

		$pale = $this->convert(content: $this->theme(tokens: ['--demo-color-primary' => '#1a67a3', '--demo-color-action' => '#ffd43b']));
		$this->assertSame(
			expected: '#000000',
			actual: $pale['decl']['--nldesign-color-action-text'],
			message: 'white does not read on yellow, so the text is derived black'
		);
	}//end testAThemesActionColourIsConverted()

	/**
	 * A DTCG document brings its action colour by path.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/action-colour/spec.md#requirement-an-imported-theme-may-bring-an-action-colour
	 */
	public function testADtcgDocumentBringsItsActionColour(): void {
		$result = $this->convert(content: (string)json_encode([
			'color' => [
				'primary' => ['$value' => '#1a67a3', '$type' => 'color'],
				'action' => ['$value' => '#1f883d', '$type' => 'color'],
				'action-hover' => ['$value' => '#197032', '$type' => 'color'],
				'on-action' => ['$value' => '#ffffff', '$type' => 'color'],
			],
		]));

		$this->assertSame(expected: '#1a67a3', actual: $result['decl']['--nldesign-color-primary']);
		$this->assertSame(expected: '#1f883d', actual: $result['decl']['--nldesign-color-action']);
		$this->assertSame(expected: '#197032', actual: $result['decl']['--nldesign-color-action-hover']);
		$this->assertSame(expected: '#ffffff', actual: $result['decl']['--nldesign-color-action-text']);
	}//end testADtcgDocumentBringsItsActionColour()

	/**
	 * The never policy refuses layout, type scale and clickable areas, and keeps components.
	 *
	 * @return void
	 */
	public function testTheNeverPolicySkipsLayoutAndKeepsComponents(): void {
		$result = $this->convert(content: $this->theme(tokens: [
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-page-max-inline-size' => '1200px',
			'--utrecht-document-font-size' => '18px',
			'--utrecht-button-padding-block-start' => '12px',
			'--utrecht-accordion-button-background-color' => '#eeeeee',
		]));

		$this->assertSame(
			expected: 'layout-fixed-by-nextcloud',
			actual: $this->entry(result: $result, source: '--utrecht-page-max-inline-size')['reason']
		);
		$this->assertSame(
			expected: 'typography-scale-locked',
			actual: $this->entry(result: $result, source: '--utrecht-document-font-size')['reason']
		);
		$this->assertSame(
			expected: 'clickable-area-locked',
			actual: $this->entry(result: $result, source: '--utrecht-button-padding-block-start')['reason']
		);
		$this->assertArrayNotHasKey(key: '--utrecht-page-max-inline-size', array: $result['decl']);
		$this->assertSame(
			expected: ['action' => 'kept', 'reason' => 'kept-for-nlds-components'],
			actual: array_intersect_key(
				$this->entry(result: $result, source: '--utrecht-accordion-button-background-color'),
				['action' => 1, 'reason' => 1]
			)
		);
	}//end testTheNeverPolicySkipsLayoutAndKeepsComponents()

	/**
	 * A var() chain that leaves the input is reported, never emitted.
	 *
	 * @return void
	 */
	public function testAnUnresolvedVarIsReportedAndNotEmitted(): void {
		$result = $this->convert(content: $this->theme(tokens: [
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-link-color' => 'var(--some-foreign-token)',
		]));

		$this->assertSame(expected: 'unresolved-var', actual: $this->entry(result: $result, source: '--utrecht-link-color')['reason']);
		$this->assertStringNotContainsString(needle: '--some-foreign-token', haystack: $result['css']);
	}//end testAnUnresolvedVarIsReportedAndNotEmitted()

	/**
	 * An external url() is dropped by the converter, so the validator never sees it.
	 *
	 * @return void
	 */
	public function testAnExternalUrlIsDroppedBeforeTheValidator(): void {
		$result = $this->convert(content: $this->theme(tokens: [
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-page-header-background-image' => 'url("https://example.com/header.png")',
		]));

		$this->assertSame(
			expected: 'external-url-blocked',
			actual: $this->entry(result: $result, source: '--utrecht-page-header-background-image')['reason']
		);
		$this->assertStringNotContainsString(needle: 'example.com', haystack: $result['css']);
	}//end testAnExternalUrlIsDroppedBeforeTheValidator()

	/**
	 * Every non-applied entry carries a reason the table has copy for.
	 *
	 * @return void
	 */
	public function testEveryReasonHasCopyInTheTable(): void {
		$result = $this->convert(content: (string)file_get_contents(\dirname(__DIR__) . '/fixtures/converter/theme-css.input.css'), slug: 'parity');
		$reasons = $this->converter->getReasons();

		foreach ($result['report'] as $item) {
			if ($item['action'] === 'applied' && $item['reason'] === null) {
				continue;
			}

			$this->assertArrayHasKey(key: (string)$item['reason'], array: $reasons, message: (string)$item['source']);
		}
	}//end testEveryReasonHasCopyInTheTable()
}//end class
