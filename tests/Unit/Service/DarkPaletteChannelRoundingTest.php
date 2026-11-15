<?php

/**
 * Channel rounding in the dark palette: the same bytes on every PHP version.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/dark-mode/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The inputs are normalised channel values the repair produces for the shipped sets.
 * Their products with 255 land a float step away from a half, where PHP 8.3's round()
 * and PHP 8.4's round() disagree. The expected bytes are the PHP 8.3 results.
 *
 * @covers \OCA\Thematiq\Service\DarkPaletteService
 * @uses   \OCA\Thematiq\Service\ContrastService
 * @uses   \OCA\Thematiq\Service\CssParserService
 * @uses   \OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore
 */
class DarkPaletteChannelRoundingTest extends TestCase {

	/**
	 * Normalised values and the byte each scales to.
	 *
	 * @return array<string, array{0: float, 1: int}>
	 */
	public static function channels(): array {
		return [
			'0.4999999999999982 stays 0' => [0.0019607843137254832, 0],
			'1.4999999999999947 stays 1' => [0.00588235294117645, 1],
			'10.499999999999963 goes to 11' => [0.04117647058823515, 11],
			'101.49999999999999 goes to 102' => [0.39803921568627443, 102],
			'102.49999999999997 goes to 103' => [0.4019607843137254, 103],
			'127.49999999999997 goes to 128' => [0.4999999999999999, 128],
			'127.5 goes to 128' => [0.5, 128],
			'130.50000000000006 goes to 131' => [0.5117647058823531, 131],
			'148.49999999999997 goes to 149' => [0.5823529411764705, 149],
			'zero' => [0.0, 0],
			'one' => [1.0, 255],
		];
	}//end channels()

	/**
	 * A normalised value scales to the same byte on every PHP version.
	 *
	 * @param float $unit     The normalised value.
	 * @param int   $expected The byte.
	 *
	 * @return void
	 */
	#[DataProvider('channels')]
	public function testChannelIsRoundedTheSameOnEveryPhpVersion(float $unit, int $expected): void {
		$service = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $this->createMock(originalClassName: IAppManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$toByte = new ReflectionMethod(DarkPaletteService::class, 'toByte');

		self::assertSame(expected: $expected, actual: $toByte->invoke($service, $unit));
	}//end testChannelIsRoundedTheSameOnEveryPhpVersion()
}//end class
