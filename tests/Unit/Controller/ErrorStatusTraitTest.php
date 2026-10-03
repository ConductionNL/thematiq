<?php

/**
 * Unit tests for ErrorStatusTrait.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ErrorStatusTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A service exception's code becomes the response status only when it is a
 * real HTTP error status; anything else falls back.
 */
class ErrorStatusTraitTest extends TestCase {

	/**
	 * Codes and the status each one must produce with a 500 fallback.
	 *
	 * @return array<string, array{int, int}>
	 */
	public static function codes(): array {
		return [
			'409 clash passes' => [409, 409],
			'413 oversized passes' => [413, 413],
			'422 invalid passes' => [422, 422],
			'503 unavailable passes' => [503, 503],
			'0 falls back' => [0, 500],
			'200 is not an error' => [200, 500],
			'399 is below the range' => [399, 500],
			'419 is no JSONResponse status' => [419, 500],
			'599 is no JSONResponse status' => [599, 500],
			'600 is above the range' => [600, 500],
		];
	}//end codes()

	/**
	 * The mapping.
	 *
	 * @param int $code The exception code.
	 * @param int $expected The status the response must carry.
	 *
	 * @return void
	 */
	#[DataProvider('codes')]
	public function testMapsTheExceptionCode(int $code, int $expected): void {
		$subject = new class {
			use ErrorStatusTrait;

			/**
			 * Expose the private helper.
			 *
			 * @param RuntimeException $exception The exception.
			 * @param 422|500 $fallback The fallback.
			 *
			 * @return int The status.
			 */
			public function status(RuntimeException $exception, int $fallback): int {
				return $this->errorStatus(exception: $exception, fallback: $fallback);
			}//end status()
		};

		$this->assertSame($expected, $subject->status(new RuntimeException('x', $code), 500));
	}//end testMapsTheExceptionCode()

	/**
	 * The fallback is the caller's, not a fixed 500.
	 *
	 * @return void
	 */
	public function testUsesTheCallersFallback(): void {
		$subject = new class {
			use ErrorStatusTrait;

			/**
			 * Expose the private helper.
			 *
			 * @param RuntimeException $exception The exception.
			 *
			 * @return int The status.
			 */
			public function status(RuntimeException $exception): int {
				return $this->errorStatus(exception: $exception, fallback: 422);
			}//end status()
		};

		$this->assertSame(422, $subject->status(new RuntimeException('x', 0)));
	}//end testUsesTheCallersFallback()
}//end class
