<?php

/**
 * Unit tests for TokenSetVocabularyAuditService against a hand-built app dir.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use PHPUnit\Framework\TestCase;

/**
 * The audit rules no shipped file exercises today, proven on a temp app dir.
 *
 * tests/Unit/TokenSetVocabularyTest.php runs the audit over the real
 * checkout, so it can only see what the shipped sets happen to contain. A
 * commented-out declaration is in none of them.
 */
class TokenSetVocabularyAuditServiceTest extends TestCase {

	/**
	 * The temp app directory.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Build an app dir with one design system that reads the vocabulary.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-vocab-' . uniqid();
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

		file_put_contents($this->appDir . '/css/systems/nldesign/theme.css', "body {\n" . $reads . "}\n");
	}//end setUp()

	/**
	 * Remove the temp app dir.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (['css/tokens/example.css', 'css/systems/nldesign/theme.css', 'css/systems/summer-breeze/theme.css', 'design-systems.json'] as $file) {
			@unlink($this->appDir . '/' . $file);
		}

		@rmdir($this->appDir . '/css/systems/summer-breeze');
		@rmdir($this->appDir . '/css/systems/nldesign');
		@rmdir($this->appDir . '/css/systems');
		@rmdir($this->appDir . '/css/tokens');
		@rmdir($this->appDir . '/css');
		@rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * A declaration inside a CSS comment is not a declaration: the token is
	 * still missing, and its value is not read as the CSS primary.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-shipped-token-set-vocabulary-completeness
	 */
	public function testCommentedOutDeclarationsNeverCount(): void {
		$declarations = '';
		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			if ($token !== '--nldesign-color-primary') {
				$declarations .= "\t" . $token . ": #123456;\n";
			}
		}

		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n" . $declarations . "\t/* --nldesign-color-primary: red; */\n}\n"
		);

		$result = (new TokenSetVocabularyAuditService(new CssParserService()))->auditSet(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'nldesign', 'theming' => ['primary_color' => '#123456']]
		);

		$this->assertTrue($result['auditable']);
		$this->assertSame(['--nldesign-color-primary'], $result['missingRequired']);
		$this->assertNull($result['cssPrimary']);
		$this->assertFalse($result['primaryMismatch']);
		$this->assertFalse($result['complete']);

		// The control: the same file with the comment markers removed is complete.
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n" . $declarations . "\t--nldesign-color-primary: #123456;\n}\n"
		);
		$control = (new TokenSetVocabularyAuditService(new CssParserService()))->auditSet(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'nldesign', 'theming' => ['primary_color' => '#123456']]
		);
		$this->assertTrue($control['complete']);
	}//end testCommentedOutDeclarationsNeverCount()

	/**
	 * A design system with its own vocabulary is audited against it: a name
	 * its stylesheet reads and the set leaves out is missing, a name the set
	 * declares and nothing reads is foreign, a name the stylesheet declares
	 * itself is not required, and the primary is compared on its own token.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-a-design-system-with-its-own-vocabulary-is-audited-against-it
	 */
	public function testOwnVocabularySystemIsAuditedAgainstItsOwnNames(): void {
		mkdir($this->appDir . '/css/systems/summer-breeze', 0777, true);
		file_put_contents(
			$this->appDir . '/design-systems.json',
			json_encode([
				['id' => 'nldesign', 'stylesheets' => ['systems/nldesign/theme']],
				['id' => 'summer-breeze', 'stylesheets' => ['systems/summer-breeze/theme']],
			])
		);
		file_put_contents(
			$this->appDir . '/css/systems/summer-breeze/theme.css',
			"body {\n\t--summer-local: 1px;\n\tcolor: var(--summer-color-text);\n\tbackground: var(--summer-color-surface, #fff);\n\tpadding: var(--summer-local);\n\t/* var(--summer-commented-out) */\n}\n"
		);
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n\t--summer-color-text: #02162e;\n\t--summer-color-primary: #21468b;\n\t--summer-unread: 4px;\n}\n"
		);

		$service = new TokenSetVocabularyAuditService(new CssParserService());
		$result = $service->auditSet(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'summer-breeze', 'theming' => ['primary_color' => '#2874D1']]
		);

		$this->assertTrue($result['auditable']);
		$this->assertSame('summer-breeze', $result['designSystem']);
		$this->assertSame(['--summer-color-surface'], $result['missingRequired']);
		$this->assertSame(['--summer-color-primary', '--summer-unread'], $result['foreignNldesignNames']);
		$this->assertTrue($result['primaryMismatch']);
		$this->assertSame('#21468b', $result['cssPrimary']);
		$this->assertSame('#2874d1', $result['declaredPrimary']);
		$this->assertFalse($result['complete']);
		$this->assertNotSame([], $service->warningsFor(appPath: $this->appDir, id: 'example', meta: ['design_system' => 'summer-breeze']));

		// The control: declare what is read, drop what is not, agree on the primary.
		file_put_contents(
			$this->appDir . '/css/tokens/example.css',
			":root {\n\t--summer-color-text: #02162e;\n\t--summer-color-surface: #ffffff;\n}\n"
		);
		$control = (new TokenSetVocabularyAuditService(new CssParserService()))->auditSet(
			appPath: $this->appDir,
			id: 'example',
			meta: ['design_system' => 'summer-breeze', 'theming' => ['primary_color' => '#2874D1']]
		);
		$this->assertTrue($control['complete']);
		$this->assertSame([], $service->warningsFor(appPath: $this->appDir, id: 'missing', meta: ['design_system' => 'none']));
	}//end testOwnVocabularySystemIsAuditedAgainstItsOwnNames()
}//end class
