<?php

/**
 * Tests for the freeform custom CSS sanitiser.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/custom-css-freeform/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CustomCssValidator;
use PHPUnit\Framework\TestCase;

/**
 * Every rule gets its own test: the validator is the only thing standing
 * between an administrator's free-text box and a stylesheet served on every
 * page of the instance.
 */
class CustomCssValidatorTest extends TestCase {

	/**
	 * The subject under test.
	 *
	 * @var CustomCssValidator
	 */
	private CustomCssValidator $validator;

	/**
	 * Set up the validator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new CustomCssValidator();

	}//end setUp()

	/**
	 * Ordinary theming CSS is accepted unchanged.
	 *
	 * @return void
	 */
	public function testAcceptsOrdinaryCss(): void {
		$css = ".app-content { padding: 8px; }\n#header .logo { opacity: .9; }";

		$this->assertSame(expected: [], actual: $this->validator->validate(css: $css));

	}//end testAcceptsOrdinaryCss()

	/**
	 * Empty input is acceptable — it is how an admin clears the layer.
	 *
	 * @return void
	 */
	public function testAcceptsEmptyCss(): void {
		$this->assertSame(expected: [], actual: $this->validator->validate(css: ''));

	}//end testAcceptsEmptyCss()

	/**
	 * @import pulls a remote stylesheet on every page load.
	 *
	 * @return void
	 */
	public function testRejectsImport(): void {
		$errors = $this->validator->validate(css: '@import url("https://example.test/x.css");');

		$this->assertNotEmpty(actual: $errors);
		$this->assertStringContainsStringIgnoringCase(needle: '@import', haystack: implode(' ', $errors));

	}//end testRejectsImport()

	/**
	 * @charset can change how the remaining bytes are decoded.
	 *
	 * @return void
	 */
	public function testRejectsCharset(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '@charset "utf-8";'));

	}//end testRejectsCharset()

	/**
	 * External url() is the classic CSS exfiltration channel.
	 *
	 * @return void
	 */
	public function testRejectsExternalUrl(): void {
		$errors = $this->validator->validate(css: '.x { background: url(https://evil.test/p.png); }');

		$this->assertNotEmpty(actual: $errors);
		$this->assertStringContainsStringIgnoringCase(needle: 'url()', haystack: implode(' ', $errors));

	}//end testRejectsExternalUrl()

	/**
	 * Protocol-relative URLs reach another origin just as well as https://.
	 *
	 * @return void
	 */
	public function testRejectsProtocolRelativeUrl(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { background: url(//evil.test/p.png); }'));

	}//end testRejectsProtocolRelativeUrl()

	/**
	 * Relative same-origin references stay usable for legitimate theming.
	 *
	 * @return void
	 */
	public function testAcceptsRelativeUrl(): void {
		$this->assertSame(expected: [], actual: $this->validator->validate(css: '.x { background: url(../img/logo.svg); }'));

	}//end testAcceptsRelativeUrl()

	/**
	 * data: URIs cannot reach another origin, so they remain permitted.
	 *
	 * @return void
	 */
	public function testAcceptsDataUri(): void {
		$css = '.x { background: url(data:image/gif;base64,R0lGODlhAQABAAAAACw=); }';

		$this->assertSame(expected: [], actual: $this->validator->validate(css: $css));

	}//end testAcceptsDataUri()

	/**
	 * A legitimate data: URI must not disable the block on the OTHER url()
	 * references in the same document (issue #193).
	 *
	 * The original rule asked two DOCUMENT-GLOBAL questions — "is there a
	 * data: anywhere?" and "is there an http(s)/`//` anywhere?" — instead of
	 * asking them of the offending match, so one data: URI earlier in the
	 * stylesheet accepted every non-http(s) scheme after it.
	 *
	 * @return void
	 */
	public function testRejectsForeignSchemeAfterDataUri(): void {
		$schemes = [
			'ftp://evil.test/x.png',
			'chrome-extension://abcdefghijklmnop/x.png',
			'file:///etc/passwd',
			'//evil.test/x.png',
			'https://evil.test/x.png',
		];

		foreach ($schemes as $scheme) {
			$css = '.a { background: url(data:image/gif;base64,R0lGODlhAQABAAAAACw=); }' . "\n"
				. '.b { background: url(' . $scheme . '); }';

			$errors = $this->validator->validate(css: $css);

			$this->assertNotEmpty(
				actual: $errors,
				message: $scheme . ' must still be rejected when a data: URI appears earlier in the same document.'
			);
			$this->assertStringContainsStringIgnoringCase(needle: 'url()', haystack: implode(' ', $errors));
		}

	}//end testRejectsForeignSchemeAfterDataUri()

	/**
	 * The same bypass in the other order — the data: URI AFTER the offending
	 * reference — must not be accepted either.
	 *
	 * @return void
	 */
	public function testRejectsForeignSchemeBeforeDataUri(): void {
		$css = '.b { background: url(ftp://evil.test/x.png); }' . "\n"
			. '.a { background: url(data:image/gif;base64,R0lGODlhAQABAAAAACw=); }';

		$this->assertNotEmpty(actual: $this->validator->validate(css: $css));

	}//end testRejectsForeignSchemeBeforeDataUri()

	/**
	 * A non-http(s) scheme on its own stays rejected.
	 *
	 * @return void
	 */
	public function testRejectsForeignSchemeWithoutDataUri(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { background: url(ftp://evil.test/x.png); }'));

	}//end testRejectsForeignSchemeWithoutDataUri()

	/**
	 * The counter-control for the fix: legitimate documents that mix several
	 * data: URIs with relative references must STILL be accepted. A rule that
	 * rejects everything is not a fix.
	 *
	 * @return void
	 */
	public function testAcceptsMultipleDataUrisAndRelativePaths(): void {
		$css = '.a { background: url(data:image/gif;base64,R0lGODlhAQABAAAAACw=); }' . "\n"
			. '.b { background: url("data:image/svg+xml;base64,PHN2Zy8+"); }' . "\n"
			. ".c { background: url('../img/logo.svg'); }\n"
			. '.d { background: url(/apps/thematiq/img/logo.svg); }';

		$this->assertSame(expected: [], actual: $this->validator->validate(css: $css));

	}//end testAcceptsMultipleDataUrisAndRelativePaths()

	/**
	 * Legacy script-execution vectors are refused.
	 *
	 * @return void
	 */
	public function testRejectsScriptExecutionVectors(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { width: expression(alert(1)); }'));
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { behavior: url(x.htc); }'));
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { -moz-binding: url(x.xml); }'));

	}//end testRejectsScriptExecutionVectors()

	/**
	 * HTML breakout sequences are refused as defence in depth.
	 *
	 * @return void
	 */
	public function testRejectsHtmlBreakout(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { color: red; } </style><script>alert(1)</script>'));

	}//end testRejectsHtmlBreakout()

	/**
	 * Reserved dark-mode variables cannot be set from freeform CSS.
	 *
	 * @return void
	 */
	public function testRejectsReservedDarkModeVariables(): void {
		foreach (CustomCssValidator::RESERVED_VARIABLES as $reserved) {
			$errors = $this->validator->validate(css: ':root { ' . $reserved . ': #fff; }');

			$this->assertNotEmpty(
				actual: $errors,
				message: $reserved . ' must be rejected (REQ-CSS-007 dark-mode derivation).'
			);
		}

	}//end testRejectsReservedDarkModeVariables()

	/**
	 * A reserved variable is caught under ANY selector, not just :root.
	 *
	 * @return void
	 */
	public function testRejectsReservedVariableOutsideRoot(): void {
		$this->assertNotEmpty(
			actual: $this->validator->validate(css: '.sneaky { --color-background-plain: #000; }')
		);

	}//end testRejectsReservedVariableOutsideRoot()

	/**
	 * An unbalanced brace would swallow the rest of the cascade.
	 *
	 * @return void
	 */
	public function testRejectsUnbalancedBraces(): void {
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { color: red;'));
		$this->assertNotEmpty(actual: $this->validator->validate(css: '.x { color: red; } }'));

	}//end testRejectsUnbalancedBraces()

	/**
	 * Braces inside strings and comments are not structural.
	 *
	 * @return void
	 */
	public function testBracesInStringsAndCommentsAreIgnored(): void {
		$this->assertSame(expected: [], actual: $this->validator->validate(css: '.x::after { content: "{"; }'));
		$this->assertSame(expected: [], actual: $this->validator->validate(css: '/* a { brace */ .x { color: red; }'));

	}//end testBracesInStringsAndCommentsAreIgnored()

	/**
	 * Oversized submissions are refused.
	 *
	 * @return void
	 */
	public function testRejectsOversizedPayload(): void {
		$css = str_repeat('a', (CustomCssValidator::MAX_BYTES + 1));

		$errors = $this->validator->validate(css: $css);

		$this->assertNotEmpty(actual: $errors);
		$this->assertStringContainsStringIgnoringCase(needle: 'limit', haystack: implode(' ', $errors));

	}//end testRejectsOversizedPayload()

	/**
	 * Validation is all-or-nothing and reports EVERY reason at once.
	 *
	 * @return void
	 */
	public function testReportsEveryViolationAtOnce(): void {
		$css = '@import url("https://evil.test/a.css"); .x { background: url(https://evil.test/b.png); }';

		$errors = $this->validator->validate(css: $css);

		$this->assertGreaterThanOrEqual(expected: 2, actual: count($errors), message: 'Both the @import and the external url() must be reported.');

	}//end testReportsEveryViolationAtOnce()

}//end class
