<?php

/**
 * Reads the arguments of a colour function literal.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads one argument of an rgb(), hsl(), oklch() or oklab() literal as a number: plain, as a
 * percentage, as an hsl() hue in degrees, turns or radians, or as an oklab component.
 *
 * @spec openspec/specs/token-set-converter/spec.md
 */
class ColourArgument {

	/**
	 * The number of a percentage argument.
	 *
	 * @param string $part The argument.
	 *
	 * @return float|null The number before the `%`, or null when it is no percentage.
	 *
	 * @spec openspec/specs/token-set-converter/spec.md#requirement-an-oklch-or-oklab-colour-becomes-hex
	 */
	public function percentage(string $part): ?float {
		if (str_ends_with($part, '%') === false) {
			return null;
		}

		return $this->number(part: $part);
	}//end percentage()

	/**
	 * An hsl() hue in degrees, from degrees, turns or radians.
	 *
	 * @param string $part The hue as written.
	 *
	 * @return float|null Null when it is no number.
	 *
	 * @spec openspec/specs/token-set-converter/spec.md#requirement-an-oklch-or-oklab-colour-becomes-hex
	 */
	public function hueDegrees(string $part): ?float {
		$hue = $this->number(part: (string)preg_replace('/(deg|turn|rad)$/', '', $part));
		if ($hue === null) {
			return null;
		}

		if (str_ends_with($part, 'turn') === true) {
			return ($hue * 360);
		}

		if (str_ends_with($part, 'rad') === true) {
			return ($hue * 180 / M_PI);
		}

		return $hue;
	}//end hueDegrees()

	/**
	 * One oklch() or oklab() argument as a number: `none` is 0, L's percentage is of 1, the
	 * others' of 0.4.
	 *
	 * @param string $part The argument.
	 * @param int $index Its position.
	 *
	 * @return float|null The number, or null when it is none.
	 *
	 * @spec openspec/specs/token-set-converter/spec.md#requirement-an-oklch-or-oklab-colour-becomes-hex
	 */
	public function oklabComponent(string $part, int $index): ?float {
		if ($part === 'none') {
			return 0.0;
		}

		$number = $this->number(part: (string)preg_replace('/deg$/', '', $part));
		if ($number === null) {
			return null;
		}

		if (str_ends_with($part, '%') === false) {
			return $number;
		}

		return ($number / 100 * $this->oklabPercentScale(index: $index));
	}//end oklabComponent()

	/**
	 * What 100% is for an oklch() or oklab() argument: 1 for L, 0.4 for the others.
	 *
	 * @param int $index The argument's position.
	 *
	 * @return float The scale.
	 */
	private function oklabPercentScale(int $index): float {
		if ($index > 0) {
			return 0.4;
		}

		return 1.0;
	}//end oklabPercentScale()

	/**
	 * A numeric argument, without a trailing `%`.
	 *
	 * @param string $part The argument.
	 *
	 * @return float|null The number, or null when it is not one.
	 *
	 * @spec openspec/specs/token-set-converter/spec.md#requirement-an-oklch-or-oklab-colour-becomes-hex
	 */
	public function number(string $part): ?float {
		$bare = rtrim($part, '%');
		if (is_numeric($bare) === false) {
			return null;
		}

		return (float)$bare;
	}//end number()
}//end class
