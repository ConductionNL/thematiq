<?php

/**
 * The nldesign path of the vocabulary audit, on a hand-built app dir.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use PHPUnit\Framework\TestCase;

/**
 * The rules thematiq#1060 found held only by browser scenarios.
 *
 * Every shipped set is complete since thematiq#1006, so the browser has no
 * incomplete subject left. Each rule is proven here on a temp app root with
 * one nldesign layer that reads the 26 required tokens, plus a control that
 * shows the same fixture passing once the defect is removed.
 */
class TokenSetVocabularyNldesignPathTest extends TestCase {

	/**
	 * The temp app directory.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Build an app dir with one nldesign layer that reads the vocabulary.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-vocab-nl-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);

		file_put_contents(
			$this->appDir . '/design-systems.json',
			json_encode([['id' => 'nldesign', 'stylesheets' => ['systems/nldesign/theme']]])
		);

		$reads = '';
		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			$reads .= "\tcolor: var(" . $token . ");\n";
		}

		$this->write(path: 'css/systems/nldesign/theme.css', css: "body {\n" . $reads . "}\n");
	}//end setUp()

	/**
	 * Remove the temp app dir, whatever a test wrote into it.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->appDir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $entry) {
			if ($entry->isDir() === true) {
				rmdir($entry->getPathname());
				continue;
			}

			unlink($entry->getPathname());
		}

		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Write a file under the app dir.
	 *
	 * @param string $path The app-relative path.
	 * @param string $css The content.
	 *
	 * @return void
	 */
	private function write(string $path, string $css): void {
		$dir = dirname($this->appDir . '/' . $path);
		if (is_dir($dir) === false) {
			mkdir($dir, 0777, true);
		}

		file_put_contents($this->appDir . '/' . $path, $css);
	}//end write()

	/**
	 * A token file declaring every required token, plus extra lines.
	 *
	 * @param string $primary The primary colour literal.
	 * @param string $extra Further declarations.
	 *
	 * @return string The CSS.
	 */
	private function completeSet(string $primary = '#123456', string $extra = ''): string {
		$declarations = '';
		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			$value = '#123456';
			if ($token === '--nldesign-color-primary') {
				$value = $primary;
			}

			$declarations .= "\t" . $token . ': ' . $value . ";\n";
		}

		return ":root {\n" . $declarations . $extra . "}\n";
	}//end completeSet()

	/**
	 * Audit the `example` set as an nldesign set.
	 *
	 * @param string $primary The manifest primary colour.
	 *
	 * @return array<string, mixed> The audit result.
	 */
	private function audit(string $primary = '#123456'): array {
		return (new TokenSetVocabularyAuditService(new CssParserService()))->auditSet(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'nldesign', 'theming' => ['primary_color' => $primary]]
		);
	}//end audit()

	/**
	 * Scenario "`--nldesign-*` names nothing reads are reported as foreign".
	 *
	 * @spec openspec/specs/token-sets/spec.md#nldesign-names-nothing-reads-are-reported-as-foreign
	 *
	 * @return void
	 */
	public function testANldesignNameNoLayerReadsIsForeign(): void {
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(extra: "\t--nldesign-color-blue-40: #1d4ed8;\n"));

		$result = $this->audit();

		$this->assertTrue($result['auditable']);
		$this->assertSame([], $result['missingRequired']);
		$this->assertSame(['--nldesign-color-blue-40'], $result['foreignNldesignNames']);
		$this->assertFalse($result['complete']);

		// The control: the raw palette step under the brand prefix is not app vocabulary.
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(extra: "\t--example-color-blue-40: #1d4ed8;\n"));
		$this->assertTrue($this->audit()['complete']);
	}//end testANldesignNameNoLayerReadsIsForeign()

	/**
	 * Scenario "The accepted vocabulary is every name any non-token-set CSS layer declares or reads".
	 *
	 * @spec openspec/specs/token-sets/spec.md#the-accepted-vocabulary-is-every-name-any-non-token-set-css-layer-declares-or-reads
	 *
	 * @return void
	 */
	public function testTheVocabularyIsEveryNonTokenLayerAndNeverTheRuntimeFiles(): void {
		// A name only one layer reads, outside defaults.css and the bridge.
		$this->write(path: 'css/systems/nldesign/element-overrides.css', css: "#header { background-image: var(--nldesign-logo-url); }\n");
		// Names only the audited input and the runtime files mention.
		$this->write(path: 'css/tokens/other.css', css: ":root { --nldesign-only-in-tokens: 1px; }\n");
		$this->write(path: 'css/tokens/dark/other.css', css: "body { --nldesign-only-in-dark-tokens: 1px; }\n");
		$this->write(path: 'css/custom-overrides.css', css: ":root { --nldesign-typed-in-the-editor: 1px; }\n");
		$this->write(path: 'css/custom-css.css', css: "body { color: var(--nldesign-typed-as-custom-css); }\n");

		$vocabulary = (new TokenSetVocabularyAuditService(new CssParserService()))->declaredVocabulary(appPath: $this->appDir);

		$this->assertContains('--nldesign-logo-url', $vocabulary);
		$this->assertContains('--nldesign-color-primary', $vocabulary);
		foreach (['--nldesign-only-in-tokens', '--nldesign-only-in-dark-tokens', '--nldesign-typed-in-the-editor', '--nldesign-typed-as-custom-css'] as $name) {
			$this->assertNotContains($name, $vocabulary, $name . ' must not widen the vocabulary');
		}

		// So a set declaring the logo url is complete, and one declaring a runtime-only name is not.
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(extra: "\t--nldesign-logo-url: url(x.svg);\n"));
		$this->assertTrue($this->audit()['complete']);
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(extra: "\t--nldesign-typed-in-the-editor: 1px;\n"));
		$this->assertSame(['--nldesign-typed-in-the-editor'], $this->audit()['foreignNldesignNames']);
	}//end testTheVocabularyIsEveryNonTokenLayerAndNeverTheRuntimeFiles()

	/**
	 * Scenario "Token names are matched case-sensitively but not case-restrictively".
	 *
	 * @spec openspec/specs/token-sets/spec.md#token-names-are-matched-case-sensitively-but-not-case-restrictively
	 *
	 * @return void
	 */
	public function testACamelCaseNameIsRecognisedKeptAsWrittenAndReported(): void {
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(extra: "\t--nldesign-tokenSetOrder-0: 0;\n"));

		$result = $this->audit();
		$this->assertSame(['--nldesign-tokenSetOrder-0'], $result['foreignNldesignNames']);
		$this->assertFalse($result['complete']);

		// The scan and the parse share one character class: once a layer reads
		// the camelCase name, it is vocabulary, exactly as written.
		$this->write(path: 'css/systems/nldesign/element-overrides.css', css: "body { order: var(--nldesign-tokenSetOrder-0); }\n");
		$service = new TokenSetVocabularyAuditService(new CssParserService());
		$this->assertContains('--nldesign-tokenSetOrder-0', $service->declaredVocabulary(appPath: $this->appDir));
		$this->assertNotContains('--nldesign-tokensetorder-0', $service->declaredVocabulary(appPath: $this->appDir));
		$this->assertTrue($this->audit()['complete']);
	}//end testACamelCaseNameIsRecognisedKeptAsWrittenAndReported()

	/**
	 * Scenario "A primary colour that disagrees with the manifest is a mismatch".
	 *
	 * @spec openspec/specs/token-sets/spec.md#a-primary-colour-that-disagrees-with-the-manifest-is-a-mismatch
	 *
	 * @return void
	 */
	public function testAPrimaryThatDisagreesWithTheManifestIsAMismatchComparedNormalised(): void {
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(primary: '#000000'));

		$result = $this->audit(primary: '#333');
		$this->assertTrue($result['primaryMismatch']);
		$this->assertSame('#333333', $result['declaredPrimary']);
		$this->assertSame('#000000', $result['cssPrimary']);
		$this->assertFalse($result['complete']);

		// Case and the 3-digit form are normalised before comparing.
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet(primary: '#ABC'));
		$agreeing = $this->audit(primary: '#aabbcc');
		$this->assertFalse($agreeing['primaryMismatch']);
		$this->assertSame('#aabbcc', $agreeing['cssPrimary']);
		$this->assertSame('#aabbcc', $agreeing['declaredPrimary']);
		$this->assertTrue($agreeing['complete']);
	}//end testAPrimaryThatDisagreesWithTheManifestIsAMismatchComparedNormalised()

	/**
	 * Scenario "The vocabulary finding is distinguishable from a contrast finding":
	 * the shape warningsFor() emits, next to a real contrast finding.
	 *
	 * @spec openspec/specs/token-sets/spec.md#the-vocabulary-finding-is-distinguishable-from-a-contrast-finding
	 *
	 * @return void
	 */
	public function testTheVocabularyFindingHasItsOwnKindAndShape(): void {
		$this->write(path: 'css/tokens/example.css', css: ":root {\n\t--nldesign-color-primary: #000000;\n\t--nldesign-color-blue-40: #1d4ed8;\n}\n");

		$service = new TokenSetVocabularyAuditService(new CssParserService());
		$warnings = $service->warningsFor(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'nldesign', 'theming' => ['primary_color' => '#333333']]
		);

		$this->assertCount(1, $warnings);
		$this->assertSame(['kind', 'missing', 'foreign', 'primaryMismatch', 'declaredPrimary', 'cssPrimary'], array_keys($warnings[0]));
		$this->assertSame('incomplete', $warnings[0]['kind']);
		$this->assertContains('--nldesign-color-text', $warnings[0]['missing']);
		$this->assertSame(['--nldesign-color-blue-40'], $warnings[0]['foreign']);
		$this->assertTrue($warnings[0]['primaryMismatch']);
		$this->assertSame('#333333', $warnings[0]['declaredPrimary']);
		$this->assertSame('#000000', $warnings[0]['cssPrimary']);

		// A contrast finding on the same channel keeps its own shape, with no kind.
		$contrast = (new ContrastService())->check(declarations: ['--nldesign-color-primary' => '#777777', '--nldesign-color-primary-text' => '#888888']);
		$this->assertNotSame([], $contrast);
		foreach ($contrast as $finding) {
			$this->assertArrayNotHasKey('kind', $finding);
			$this->assertArrayHasKey('pair', $finding);
		}

		// A complete set emits nothing.
		$this->write(path: 'css/tokens/example.css', css: $this->completeSet());
		$this->assertSame([], $service->warningsFor(appPath: $this->appDir, id: 'example', meta: ['design_system' => 'nldesign', 'theming' => ['primary_color' => '#123456']]));
	}//end testTheVocabularyFindingHasItsOwnKindAndShape()
}//end class
