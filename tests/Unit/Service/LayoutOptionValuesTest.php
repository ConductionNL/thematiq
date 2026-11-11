<?php

/**
 * Unit tests for LayoutOptionValues.
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
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\LayoutOptionsService;
use OCA\Thematiq\Service\LayoutOptionValues;
use PHPUnit\Framework\TestCase;

/**
 * Golden of the values each layout option accepts, and of a bundle's layout options.
 */
class LayoutOptionValuesTest extends TestCase {

	/**
	 * Every key against every kind of value.
	 *
	 * @return void
	 */
	public function testWhatEachOptionAccepts(): void {
		$keys = array_merge(array_values(LayoutOptionValues::BUNDLE_KEYS), ['unknown_key']);
		$values = ['', '1', '0', true, false, 264, '264', '199', '481', 480, '200', 'soft', 'default', 'header', 'workplace', 'light', ' 264', '26.4', null, 1];
		$out = [];
		foreach ($keys as $key) {
			foreach ($values as $value) {
				$out[] = $key . '=' . var_export($value, true) . '=>' . var_export(LayoutOptionValues::accepted(key: $key, value: $value), true);
			}
		}

		// Recorded on the code before the split; the message shows a slice when it fails.
		$this->assertSame(expected: 'f891e788c8cc3c7a', actual: substr(hash('sha256', implode("\n", $out)), 0, 16), message: implode("\n", array_slice($out, 40, 20)));
	}//end testWhatEachOptionAccepts()

	/**
	 * A bundle's layout options: absent, not an object, and each value checked.
	 *
	 * @return void
	 */
	public function testABundlesOptions(): void {
		$errors = [];
		$all = array_fill_keys(array_values(LayoutOptionValues::BUNDLE_KEYS), '');
		$this->assertSame(expected: $all, actual: LayoutOptionValues::fromBundle(options: null, errors: $errors));
		$this->assertSame(expected: [], actual: $errors);

		$this->assertSame(expected: $all, actual: LayoutOptionValues::fromBundle(options: 'x', errors: $errors));
		$this->assertSame(expected: [['section' => 'config', 'message' => '"config.layoutOptions" must be an object.']], actual: $errors);

		$errors = [];
		$resolved = LayoutOptionValues::fromBundle(
			options: ['navigationWidth' => 264, 'headerStyle' => 'workplace', 'loginWatermark' => '0', 'brandStripe' => true, 'navigationActiveStyle' => 'loud'],
			errors: $errors
		);
		$this->assertSame(
			expected: [
				LayoutOptionsService::WORKPLACE_LAYOUT_KEY => '',
				LayoutOptionsService::BRAND_STRIPE_KEY => '',
				LayoutOptionValues::NAVIGATION_WIDTH_KEY => '',
				LayoutOptionValues::NAVIGATION_ACTIVE_STYLE_KEY => '',
				LayoutOptionValues::BRAND_STRIPE_PLACEMENT_KEY => '',
				LayoutOptionValues::LOGIN_WATERMARK_KEY => '0',
				LayoutOptionValues::HEADER_STYLE_KEY => 'workplace',
			],
			actual: $resolved
		);
		$this->assertSame(
			expected: [
				'"config.layoutOptions.brandStripe" is not a value the option accepts.',
				'"config.layoutOptions.navigationWidth" is not a value the option accepts.',
				'"config.layoutOptions.navigationActiveStyle" is not a value the option accepts.',
			],
			actual: array_column($errors, 'message')
		);
	}//end testABundlesOptions()
}//end class
