<?php

/**
 * Unit tests for CustomTokenSetValidator.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @author  Conduction <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/custom-token-set-upload/tasks.md#task-5.1
 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CustomTokenSetValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CSS upload validator / re-serialiser.
 *
 * Covers the payload corpus called out in tasks.md#task-5.1: selector
 * smuggling, external url(), oversized hint, comments, and accepted
 * org-palette extras. Also covers the value-injection hardening corpus from
 * harden-custom-token-set-value-validation/tasks.md#task-2: semicolon and
 * CSS-comment-marker smuggling, both at the isForbiddenValue() unit level
 * and the validateDeclarations() end-to-end level.
 */
class CustomTokenSetValidatorTest extends TestCase {

	/**
	 * The validator under test.
	 *
	 * @var CustomTokenSetValidator
	 */
	private CustomTokenSetValidator $validator;

	/**
	 * Set up the validator before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new CustomTokenSetValidator();
	}//end setUp()

	/**
	 * Only --nldesign-* and --{slug}-* declarations are accepted; others are skipped.
	 *
	 * @return void
	 */
	public function testWhitelistSplitsAcceptedAndSkipped(): void {
		$declarations = [
			'--nldesign-color-primary' => '#007bc7',
			'--nldesign-color-primary-text' => '#ffffff',
			'--gemeente-color-accent' => '#ff0000',
			'--color-primary' => '#123456',
			'--v-some-other' => '#000000',
		];

		$split = $this->validator->validateDeclarations(declarations: $declarations, slug: 'gemeente');

		$this->assertNotNull(actual: $split);
		$this->assertArrayHasKey(key: '--nldesign-color-primary', array: $split['accepted']);
		$this->assertArrayHasKey(key: '--gemeente-color-accent', array: $split['accepted'], message: 'org-palette extras must be accepted');
		$this->assertContains(needle: '--color-primary', haystack: $split['skipped']);
		$this->assertContains(needle: '--v-some-other', haystack: $split['skipped']);
		$this->assertCount(expectedCount: 3, haystack: $split['accepted']);
		$this->assertCount(expectedCount: 2, haystack: $split['skipped']);
	}//end testWhitelistSplitsAcceptedAndSkipped()

	/**
	 * A forbidden value (external url) is a hard failure with HTTP 422.
	 *
	 * @return void
	 */
	public function testExternalUrlValueIsRejected(): void {
		$split = $this->validator->validateDeclarations(
			declarations: ['--nldesign-logo-url' => "url('https://evil.example/logo.svg')"],
			slug: 'x'
		);

		$this->assertNull(actual: $split);
		$error = $this->validator->getLastError();
		$this->assertSame(expected: 422, actual: $error['status']);
		$this->assertSame(expected: '--nldesign-logo-url', actual: $error['property']);
	}//end testExternalUrlValueIsRejected()

	/**
	 * Relative url() and data: URIs are permitted.
	 *
	 * @return void
	 */
	public function testRelativeAndDataUrlsAreAccepted(): void {
		$this->assertFalse(condition: $this->validator->isForbiddenValue(value: "url('../../img/logos/custom.svg')"));
		$this->assertFalse(condition: $this->validator->isForbiddenValue(value: 'url(data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)'));
	}//end testRelativeAndDataUrlsAreAccepted()

	/**
	 * The @import, expression(), javascript:, and raw-markup values are all forbidden.
	 *
	 * @return void
	 */
	public function testDangerousValuesAreForbidden(): void {
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: '@import url(x.css)'));
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'expression(alert(1))'));
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'javascript:alert(1)'));
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: '</style><script>x</script>'));
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'url(//evil.example/x.png)'));
	}//end testDangerousValuesAreForbidden()

	/**
	 * A `;` in a value can smuggle a second declaration past the whitelist
	 * (e.g. injecting an arbitrary property name/value into the generated
	 * :root block) and must be rejected, matching the guard already applied
	 * by CustomOverridesService::buildDeclarationLines().
	 *
	 * @return void
	 */
	public function testSemicolonSmugglingIsForbidden(): void {
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'red; --nldesign-evil: url(javascript:alert(1))'));
	}//end testSemicolonSmugglingIsForbidden()

	/**
	 * CSS comment delimiters can be used to close the :root block early or
	 * comment out the trailing brace, so both `/*` and `*​/` must be rejected.
	 *
	 * @return void
	 */
	public function testCommentDelimitersAreForbidden(): void {
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'red } /* injected'));
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'red */ .evil { color: red'));
	}//end testCommentDelimitersAreForbidden()

	/**
	 * Proposal-literal regression: a semicolon after a legitimate value can
	 * smuggle an unrelated `background` declaration into the served :root
	 * block. This is the exact payload from the change proposal's "Why"
	 * section and MUST be rejected.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-2.1
	 */
	public function testSemicolonSmuggledBackgroundDeclarationIsForbidden(): void {
		$this->assertTrue(
			condition: $this->validator->isForbiddenValue(value: 'red; background: url(https://evil.example/x.png)')
		);
	}//end testSemicolonSmuggledBackgroundDeclarationIsForbidden()

	/**
	 * A bare CSS comment marker embedded in an otherwise plausible value must
	 * be rejected even when it is not paired with a semicolon, since `/*` /
	 * `*​/` alone can unbalance the generated :root block's comment nesting.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-2.2
	 */
	public function testBareCommentMarkerValueIsForbidden(): void {
		$this->assertTrue(condition: $this->validator->isForbiddenValue(value: 'red /* } */'));
	}//end testBareCommentMarkerValueIsForbidden()

	/**
	 * End-to-end proof (not just isForbiddenValue() in isolation): a
	 * declaration set containing the semicolon-smuggling payload is rejected
	 * by validateDeclarations() as a hard 422 failure — it must NOT be
	 * silently split into an "accepted" (trimmed/truncated) value and a
	 * "skipped" entry, which would still let the smuggled fragment reach
	 * storage under a mangled but present accepted value.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-2.3
	 */
	public function testValidateDeclarationsRejectsSemicolonSmugglingEndToEnd(): void {
		$split = $this->validator->validateDeclarations(
			declarations: [
				'--nldesign-color-primary' => 'red; background: url(https://evil.example/x.png)',
			],
			slug: 'gemeente'
		);

		$this->assertNull(actual: $split, message: 'the smuggling payload must be a hard failure, not a split result');

		$error = $this->validator->getLastError();
		$this->assertSame(expected: 422, actual: $error['status']);
		$this->assertSame(expected: '--nldesign-color-primary', actual: $error['property']);
	}//end testValidateDeclarationsRejectsSemicolonSmugglingEndToEnd()

	/**
	 * A forbidden value is a hard failure even under a name this validator
	 * does NOT accept — the split is not a filter on what gets written.
	 *
	 * `store()` writes the converter's emitted CSS, not `serialize($accepted)`,
	 * and the converter parses `--[\w-]+` where this validator accepts only
	 * `[a-z0-9-]+`. So a name like `--utrecht-colorPrimary` is emitted, lands
	 * in `skipped` here, and is written anyway. While the value check sat after
	 * the name check, its value was never judged.
	 *
	 * @param string $name The name the validator refuses.
	 *
	 * @dataProvider skippedNameProvider
	 *
	 * @return void
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	public function testForbiddenValueUnderASkippedNameIsAHardFailure(string $name): void {
		$split = $this->validator->validateDeclarations(
			declarations: [
				'--nldesign-color-primary' => '#007bc7',
				$name => 'expression(alert(1))',
			],
			slug: 'gemeente'
		);

		$this->assertNull(actual: $split, message: 'a skipped name must not exempt its value from the gate');

		$error = $this->validator->getLastError();
		$this->assertSame(expected: 422, actual: $error['status']);
		$this->assertSame(expected: $name, actual: $error['property']);
	}//end testForbiddenValueUnderASkippedNameIsAHardFailure()

	/**
	 * Names outside the accepted vocabulary, including the shapes the
	 * converter keeps verbatim.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function skippedNameProvider(): array {
		return [
			'camelCase component token' => ['--utrecht-colorPrimary'],
			'snake_case component token' => ['--utrecht-color_secondary'],
			'a Nextcloud core variable' => ['--color-primary'],
			'an unknown vendor prefix' => ['--v-some-other'],
		];
	}//end skippedNameProvider()

	/**
	 * A skipped name with an ORDINARY value is still just skipped — the gate
	 * widened to cover every value, it did not start rejecting names.
	 *
	 * @return void
	 */
	public function testSkippedNameWithAnOrdinaryValueIsStillOnlySkipped(): void {
		$split = $this->validator->validateDeclarations(
			declarations: [
				'--nldesign-color-primary' => '#007bc7',
				'--utrecht-colorPrimary' => '#ff0000',
			],
			slug: 'gemeente'
		);

		$this->assertNotNull(actual: $split);
		$this->assertContains(needle: '--utrecht-colorPrimary', haystack: $split['skipped']);
		$this->assertArrayNotHasKey(key: '--utrecht-colorPrimary', array: $split['accepted']);
	}//end testSkippedNameWithAnOrdinaryValueIsStillOnlySkipped()

	/**
	 * Any selector other than :root (or any at-rule) is rejected pre-parse.
	 *
	 * @return void
	 */
	public function testDisallowedSelectorIsDetected(): void {
		$smuggledSelector = $this->validator->hasDisallowedSelector(
			css: ':root { --nldesign-color-primary: #007bc7; } .header { color: red; }'
		);
		$smuggledImport = $this->validator->hasDisallowedSelector(
			css: '@import url(x.css); :root { --nldesign-color-primary: #007bc7; }'
		);

		$this->assertTrue(condition: $smuggledSelector);
		$this->assertTrue(condition: $smuggledImport);
		$this->assertTrue(condition: $this->validator->hasDisallowedSelector(css: '@font-face { font-family: X; } :root {}'));
	}//end testDisallowedSelectorIsDetected()

	/**
	 * A clean single-:root block (with comments) passes the selector guard.
	 *
	 * @return void
	 */
	public function testCleanRootBlockPassesSelectorGuard(): void {
		$css = "/* comment with .fake-selector { } inside */\n:root {\n  --nldesign-color-primary: #007bc7;\n}";
		$this->assertFalse(condition: $this->validator->hasDisallowedSelector(css: $css));
	}//end testCleanRootBlockPassesSelectorGuard()

	/**
	 * An upload with no --nldesign-* declarations is a hard failure.
	 *
	 * @return void
	 */
	public function testEmptyAcceptedSetIsRejected(): void {
		$split = $this->validator->validateDeclarations(
			declarations: ['--color-primary' => '#123456'],
			slug: 'x'
		);

		$this->assertNull(actual: $split);
		$this->assertSame(expected: 422, actual: $this->validator->getLastError()['status']);
	}//end testEmptyAcceptedSetIsRejected()

	/**
	 * Serialization is generated from declarations only, in a single :root block.
	 *
	 * @return void
	 */
	public function testSerializeProducesCanonicalRootBlock(): void {
		$css = $this->validator->serialize(
			declarations: [
				'--nldesign-color-primary' => '#007bc7',
				'--nldesign-color-primary-text' => '#ffffff',
			]
		);

		$this->assertStringContainsString(needle: ':root {', haystack: $css);
		$this->assertStringContainsString(needle: '--nldesign-color-primary: #007bc7;', haystack: $css);
		$this->assertStringContainsString(needle: '--nldesign-color-primary-text: #ffffff;', haystack: $css);
		$this->assertStringEndsWith(suffix: "}\n", string: $css);
		// No selector smuggling can survive serialisation.
		$this->assertStringNotContainsString(needle: '.header', haystack: $css);
	}//end testSerializeProducesCanonicalRootBlock()

	/**
	 * The maximum size constant matches the specced 512 KB cap.
	 *
	 * @return void
	 */
	public function testMaxSizeIs512Kb(): void {
		$this->assertSame(expected: (512 * 1024), actual: CustomTokenSetValidator::MAX_SIZE);
	}//end testMaxSizeIs512Kb()
}//end class
