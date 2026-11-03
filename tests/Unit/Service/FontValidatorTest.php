<?php

/**
 * Unit tests for FontValidator.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @author  Conduction <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\FontValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the font upload validator.
 *
 * Covers the hardening corpus from tasks.md#task-5.1: a valid woff2 header
 * is accepted; TTF/OTF/WOFF1/zip/renamed-text are all rejected as non-woff2
 * by content, never by extension; oversize is rejected; display names
 * yielding empty/overlong/bad-charset slugs and names carrying `../`, `/`,
 * or a NUL byte are all rejected; every failure message names the check
 * that failed.
 */
class FontValidatorTest extends TestCase {

	/**
	 * The validator under test.
	 *
	 * @var FontValidator
	 */
	private FontValidator $validator;

	/**
	 * Set up the validator before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new FontValidator();
	}//end setUp()

	/**
	 * A valid woff2 magic-byte header is accepted (no exception).
	 */
	public function testValidWoff2HeaderAccepted(): void {
		$this->validator->validateMagicBytes(bytes: "wOF2\x00\x01\x02\x03rest-of-file-does-not-matter-here");
		$this->addToAssertionCount(count: 1);
	}//end testValidWoff2HeaderAccepted()

	/**
	 * A TrueType font (bytes `\x00\x01\x00\x00`) is rejected as non-woff2.
	 */
	public function testTtfRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateMagicBytes(bytes: "\x00\x01\x00\x00restofdata");
	}//end testTtfRejected()

	/**
	 * An OpenType font (`OTTO`) is rejected as non-woff2.
	 */
	public function testOtfRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateMagicBytes(bytes: 'OTTOrestofdata');
	}//end testOtfRejected()

	/**
	 * A WOFF (v1, `wOFF`) font is rejected as non-woff2.
	 */
	public function testWoff1Rejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateMagicBytes(bytes: 'wOFFrestofdata');
	}//end testWoff1Rejected()

	/**
	 * A zip archive (`PK`) renamed to .woff2 is rejected as non-woff2.
	 */
	public function testZipRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateMagicBytes(bytes: "PK\x03\x04restofdata");
	}//end testZipRejected()

	/**
	 * A plain text file renamed to .woff2 is rejected as non-woff2.
	 */
	public function testRenamedTextFileRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateMagicBytes(bytes: "this is just a text file, not a font\n");
	}//end testRenamedTextFileRejected()

	/**
	 * The magic-byte failure message names the check that failed.
	 */
	public function testMagicByteErrorNamesTheCheck(): void {
		try {
			$this->validator->validateMagicBytes(bytes: 'not-a-font');
			$this->fail(message: 'Expected a RuntimeException.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString(needle: 'WOFF2', haystack: $e->getMessage());
			$this->assertStringContainsString(needle: 'magic byte', haystack: $e->getMessage());
		}
	}//end testMagicByteErrorNamesTheCheck()

	/**
	 * A size at exactly the 2 MB cap is accepted.
	 */
	public function testSizeAtCapAccepted(): void {
		$this->validator->validateSize(size: (2 * 1024 * 1024));
		$this->addToAssertionCount(count: 1);
	}//end testSizeAtCapAccepted()

	/**
	 * A size one byte over the 2 MB cap is rejected with 413.
	 */
	public function testOversizeRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 413);
		$this->validator->validateSize(size: ((2 * 1024 * 1024) + 1));
	}//end testOversizeRejected()

	/**
	 * An empty display name is rejected with 422.
	 */
	public function testEmptyDisplayNameRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateDisplayName(name: '   ');
	}//end testEmptyDisplayNameRejected()

	/**
	 * A display name containing a path-traversal sequence is rejected.
	 */
	public function testDisplayNamePathTraversalRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateDisplayName(name: '../../config/config');
	}//end testDisplayNamePathTraversalRejected()

	/**
	 * A display name containing a bare slash is rejected.
	 */
	public function testDisplayNameSlashRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateDisplayName(name: 'Rijks/Sans');
	}//end testDisplayNameSlashRejected()

	/**
	 * A display name containing a NUL byte is rejected.
	 */
	public function testDisplayNameNulByteRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateDisplayName(name: "Rijks\0Sans");
	}//end testDisplayNameNulByteRejected()

	/**
	 * A well-formed display name passes.
	 */
	public function testWellFormedDisplayNameAccepted(): void {
		$this->validator->validateDisplayName(name: 'Rijks Sans');
		$this->addToAssertionCount(count: 1);
	}//end testWellFormedDisplayNameAccepted()

	/**
	 * An empty slug (all-symbol display name) is rejected with 422.
	 */
	public function testEmptySlugRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateSlug(slug: '');
	}//end testEmptySlugRejected()

	/**
	 * A slug longer than 64 characters is rejected with 422.
	 */
	public function testOverlongSlugRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateSlug(slug: str_repeat('a', 65));
	}//end testOverlongSlugRejected()

	/**
	 * A slug outside the `[a-z0-9-]` charset is rejected with 422.
	 */
	public function testBadCharsetSlugRejected(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateSlug(slug: 'custom-UPPER');
	}//end testBadCharsetSlugRejected()

	/**
	 * A valid slug (64 chars exactly, safe charset) passes.
	 */
	public function testValidSlugAccepted(): void {
		$this->validator->validateSlug(slug: str_repeat('a', 64));
		$this->addToAssertionCount(count: 1);
	}//end testValidSlugAccepted()

	/**
	 * Role validation accepts `body` and `heading` only.
	 */
	public function testRoleValidation(): void {
		$this->validator->validateRole(role: 'body');
		$this->validator->validateRole(role: 'heading');
		$this->addToAssertionCount(count: 2);

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 422);
		$this->validator->validateRole(role: 'footer');
	}//end testRoleValidation()

	/**
	 * The per-instance cap rejects the 21st font with 409.
	 */
	public function testCapRejectsAt21st(): void {
		$this->validator->validateCap(currentCount: 19);
		$this->addToAssertionCount(count: 1);

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 409);
		$this->validator->validateCap(currentCount: 20);
	}//end testCapRejectsAt21st()
}//end class
