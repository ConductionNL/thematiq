<?php

/**
 * Unit tests for TokenReferenceService: the generated per-set token reference.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/token-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenReferenceService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the token reference renderer, on a small app tree of its own.
 */
class TokenReferenceServiceTest extends TestCase {

	/**
	 * The temporary app root.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Build a minimal app tree: defaults, one set, its dark variant and a bridge stylesheet.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appDir = sys_get_temp_dir() . '/thematiq-reference-' . uniqid();
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		file_put_contents(
			$this->appDir . '/css/systems/nldesign/defaults.css',
			":root {\n\t--nldesign-color-primary: #154273;\n\t--nldesign-color-primary-text: #ffffff;\n\t--nldesign-color-background: #ffffff;\n\t--nldesign-font-family: sans-serif;\n}\n"
		);
		file_put_contents(
			$this->appDir . '/css/systems/nldesign/overrides.css',
			":root {\n\t--color-primary-element: var(--nldesign-color-primary) !important;\n\t--color-main-background: var(--nldesign-color-background) !important;\n\t--font-face: var(--nldesign-font-family, sans-serif);\n}\n"
		);
		file_put_contents(
			$this->appDir . '/css/tokens/voorbeeld.css',
			":root {\n\t--nldesign-color-primary: #24578F;\n\t--voorbeeld-unused-token: 4px;\n}\n"
		);
		file_put_contents($this->appDir . '/css/tokens/dark/voorbeeld.css', ":root {\n\t--nldesign-color-primary: #8fb4e0;\n}\n");
	}//end setUp()

	/**
	 * Remove the tree.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->appDir));
		parent::tearDown();
	}//end tearDown()

	/**
	 * The service under test.
	 *
	 * @return TokenReferenceService The service.
	 */
	private function service(): TokenReferenceService {
		$parser = new CssParserService();
		return new TokenReferenceService(new ShippedTokenSetAuditService(new ContrastService(), $parser), $parser);
	}//end service()

	/**
	 * The set under test.
	 *
	 * @return array<string, mixed> The set metadata.
	 */
	private function set(): array {
		return ['id' => 'voorbeeld', 'name' => 'Gemeente Voorbeeld', 'theming' => ['background_color' => '#ffffff']];
	}//end set()

	/**
	 * Declared and inherited tokens are told apart, with what they paint.
	 *
	 * @return void
	 */
	public function testMarkdownMarksDeclaredAndDefaultTokensAndWhatTheyPaint(): void {
		$md = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'md');

		$this->assertStringContainsString('# Gemeente Voorbeeld', $md);
		$this->assertMatchesRegularExpression('/^\| `--nldesign-color-primary` \| .*`#24578F` \| .*`#8fb4e0` \| this set \| Primary element color \|$/m', $md);
		$this->assertMatchesRegularExpression('/^\| `--nldesign-color-primary-text` \| .*`#ffffff` \| \| defaults \| /m', $md);
		$this->assertMatchesRegularExpression('/^\| `--nldesign-font-family` \| `sans-serif` \| \| defaults \| Font family \|$/m', $md);
		$this->assertStringContainsString('2 declared by this set, 3 from the defaults layer', $md);
	}//end testMarkdownMarksDeclaredAndDefaultTokensAndWhatTheyPaint()

	/**
	 * A colour row carries a swatch and the value as text.
	 *
	 * @return void
	 */
	public function testAColourRowHasASwatchAndTheValueAsText(): void {
		$md = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'md');

		$this->assertMatchesRegularExpression('/!\[\]\(data:image\/svg\+xml,[^)]*%2324578F[^)]*\) `#24578F`/', $md);
	}//end testAColourRowHasASwatchAndTheValueAsText()

	/**
	 * As `md-plain` a colour row is the value as text alone, and no data URL is left in the file.
	 *
	 * @return void
	 */
	public function testWithoutSwatchesAColourRowIsTheValueAsText(): void {
		$md = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'md-plain');

		$this->assertStringContainsString('| `#24578F` |', $md);
		$this->assertStringNotContainsString('data:image', $md);
	}//end testWithoutSwatchesAColourRowIsTheValueAsText()

	/**
	 * A token the set declares that nothing reads is listed on its own.
	 *
	 * @return void
	 */
	public function testTokensNothingReadsAreListedApart(): void {
		$md = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'md');

		$apart = substr($md, (int)strpos($md, '## Tokens nothing reads'));
		$this->assertStringContainsString('`--voorbeeld-unused-token`', $apart);
		$this->assertStringNotContainsString('`--voorbeeld-unused-token`', substr($md, 0, (int)strpos($md, '## Tokens nothing reads')));
	}//end testTokensNothingReadsAreListedApart()

	/**
	 * The HTML reference is a complete page whose swatches never carry meaning alone.
	 *
	 * @return void
	 */
	public function testHtmlIsAnAccessiblePage(): void {
		$html = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'html');

		$this->assertStringStartsWith('<!DOCTYPE html>', $html);
		$this->assertStringContainsString('<html lang="en">', $html);
		$this->assertStringContainsString('<title>Gemeente Voorbeeld token reference</title>', $html);
		$this->assertStringContainsString('<th scope="col">Token</th>', $html);
		$this->assertMatchesRegularExpression('/<span class="swatch" aria-hidden="true" style="background:#24578F"><\/span> <code>#24578F<\/code>/', $html);
		$this->assertStringContainsString('<details>', $html);
	}//end testHtmlIsAnAccessiblePage()

	/**
	 * Values are escaped in HTML.
	 *
	 * @return void
	 */
	public function testHtmlEscapesValues(): void {
		file_put_contents($this->appDir . '/css/tokens/voorbeeld.css', ":root {\n\t--nldesign-font-family: \"</code><script>x</script>\";\n}\n");

		$html = $this->service()->render(appPath: $this->appDir, set: $this->set(), format: 'html');

		$this->assertStringNotContainsString('<script>', $html);
	}//end testHtmlEscapesValues()

	/**
	 * Rendering is deterministic.
	 *
	 * @return void
	 */
	public function testRenderingIsDeterministic(): void {
		$service = $this->service();

		$this->assertSame(
			$service->render(appPath: $this->appDir, set: $this->set(), format: 'md'),
			$service->render(appPath: $this->appDir, set: $this->set(), format: 'md')
		);
	}//end testRenderingIsDeterministic()
}//end class
