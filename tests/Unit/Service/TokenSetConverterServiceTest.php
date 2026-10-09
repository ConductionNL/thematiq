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
			'.openwoo-theme { --utrecht-button-primary-action-background-color: var(--openwoo-color-primary); --openwoo-color-primary: #23845c; }',
			'openwoo'
		);

		$this->assertSame('A', $result['inputKind']);
		$this->assertSame('#23845c', $result['decl']['--nldesign-color-primary']);
		$this->assertStringNotContainsString('var(--openwoo-color-primary)', $result['css']);
	}//end testBuiltThemeCssIsInputAWithItsChainResolved()

	/**
	 * Input B: a DTCG document goes through DesignTokensMapper.
	 *
	 * @return void
	 */
	public function testADtcgDocumentIsInputB(): void {
		$result = $this->convert('{"color": {"primary": {"$value": "#154273", "$type": "color"}}}');

		$this->assertSame('B', $result['inputKind']);
		$this->assertSame('#154273', $result['decl']['--nldesign-color-primary']);
	}//end testADtcgDocumentIsInputB()

	/**
	 * Input C: a Style Dictionary tree, its alias resolved rather than emitted as braces.
	 *
	 * @return void
	 */
	public function testAStyleDictionaryTreeIsInputCWithItsAliasResolved(): void {
		$result = $this->convert((string)json_encode([
			'brand' => ['red' => ['500' => ['value' => '#c8102e']]],
			'utrecht' => ['button' => ['primary-action' => ['background-color' => ['value' => '{brand.red.500}']]]],
		]));

		$this->assertSame('C', $result['inputKind']);
		$this->assertSame('#c8102e', $result['decl']['--nldesign-color-primary']);
		$this->assertSame('#c8102e', $result['decl']['--demo-brand-red-500']);
		$this->assertStringNotContainsString('{brand', $result['css']);
	}//end testAStyleDictionaryTreeIsInputCWithItsAliasResolved()

	/**
	 * Input D: an existing set runs add-only.
	 *
	 * @return void
	 */
	public function testAnExistingSetIsInputDAndKeepsItsValues(): void {
		$result = $this->convert(':root { --nldesign-color-primary: #1b3d6b; --nldesign-animation-quick: 100ms; }');

		$this->assertSame('D', $result['inputKind']);
		$this->assertSame('#1b3d6b', $result['decl']['--nldesign-color-primary']);
		$this->assertSame('100ms', $result['decl']['--nldesign-animation-quick']);
		$this->assertSame('kept-existing-value', $this->entry($result, '--nldesign-animation-quick')['reason']);
	}//end testAnExistingSetIsInputDAndKeepsItsValues()

	/**
	 * Content matching no accepted shape is refused with a 422 and nothing to store.
	 *
	 * @return void
	 */
	public function testUnrecognisedContentIsRefused(): void {
		try {
			$this->converter->convert(content: 'this is not a theme at all', slug: 'demo', displayName: 'Demo');
			$this->fail('Unrecognised content was converted.');
		} catch (RuntimeException $exception) {
			$this->assertSame(422, $exception->getCode());
		}
	}//end testUnrecognisedContentIsRefused()

	/**
	 * The contrast guard darkens a primary that fails its declared text colour.
	 *
	 * @return void
	 */
	public function testTheContrastGuardDarkensAFailingPrimary(): void {
		$result = $this->convert($this->theme([
			'--utrecht-button-primary-action-background-color' => '#7fb3e0',
			'--utrecht-button-primary-action-color' => '#ffffff',
		]));

		$contrast = new ContrastService();
		$ratio = $contrast->ratio(
			first: $contrast->parseColor(value: $result['decl']['--nldesign-color-primary']),
			second: $contrast->parseColor(value: $result['decl']['--nldesign-color-primary-text'])
		);

		$this->assertGreaterThanOrEqual(4.5, $ratio);
		$primary = null;
		foreach ($result['report'] as $item) {
			if ($item['target'] === '--nldesign-color-primary') {
				$primary = $item;
			}
		}

		$this->assertSame('contrast-adjusted', $primary['reason']);
		$this->assertSame('#7fb3e0', $primary['original']);
	}//end testTheContrastGuardDarkensAFailingPrimary()

	/**
	 * The never policy refuses layout, type scale and clickable areas, and keeps components.
	 *
	 * @return void
	 */
	public function testTheNeverPolicySkipsLayoutAndKeepsComponents(): void {
		$result = $this->convert($this->theme([
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-page-max-inline-size' => '1200px',
			'--utrecht-document-font-size' => '18px',
			'--utrecht-button-padding-block-start' => '12px',
			'--utrecht-accordion-button-background-color' => '#eeeeee',
		]));

		$this->assertSame('layout-fixed-by-nextcloud', $this->entry($result, '--utrecht-page-max-inline-size')['reason']);
		$this->assertSame('typography-scale-locked', $this->entry($result, '--utrecht-document-font-size')['reason']);
		$this->assertSame('clickable-area-locked', $this->entry($result, '--utrecht-button-padding-block-start')['reason']);
		$this->assertArrayNotHasKey('--utrecht-page-max-inline-size', $result['decl']);
		$this->assertSame(
			['action' => 'kept', 'reason' => 'kept-for-nlds-components'],
			array_intersect_key($this->entry($result, '--utrecht-accordion-button-background-color'), ['action' => 1, 'reason' => 1])
		);
	}//end testTheNeverPolicySkipsLayoutAndKeepsComponents()

	/**
	 * A var() chain that leaves the input is reported, never emitted.
	 *
	 * @return void
	 */
	public function testAnUnresolvedVarIsReportedAndNotEmitted(): void {
		$result = $this->convert($this->theme([
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-link-color' => 'var(--some-foreign-token)',
		]));

		$this->assertSame('unresolved-var', $this->entry($result, '--utrecht-link-color')['reason']);
		$this->assertStringNotContainsString('--some-foreign-token', $result['css']);
	}//end testAnUnresolvedVarIsReportedAndNotEmitted()

	/**
	 * An external url() is dropped by the converter, so the validator never sees it.
	 *
	 * @return void
	 */
	public function testAnExternalUrlIsDroppedBeforeTheValidator(): void {
		$result = $this->convert($this->theme([
			'--utrecht-button-primary-action-background-color' => '#154273',
			'--utrecht-page-header-background-image' => 'url("https://example.com/header.png")',
		]));

		$this->assertSame('external-url-blocked', $this->entry($result, '--utrecht-page-header-background-image')['reason']);
		$this->assertStringNotContainsString('example.com', $result['css']);
	}//end testAnExternalUrlIsDroppedBeforeTheValidator()

	/**
	 * Every non-applied entry carries a reason the table has copy for.
	 *
	 * @return void
	 */
	public function testEveryReasonHasCopyInTheTable(): void {
		$result = $this->convert((string)file_get_contents(\dirname(__DIR__) . '/fixtures/converter/theme-css.input.css'), 'parity');
		$reasons = $this->converter->getReasons();

		foreach ($result['report'] as $item) {
			if ($item['action'] === 'applied' && $item['reason'] === null) {
				continue;
			}

			$this->assertArrayHasKey((string)$item['reason'], $reasons, (string)$item['source']);
		}
	}//end testEveryReasonHasCopyInTheTable()
}//end class
