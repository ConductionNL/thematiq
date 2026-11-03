<?php

/**
 * Regression guard for the error-fill foreground contrast.
 *
 * @category Test
 * @package  NLDesign
 * @author   Conduction B.V. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/nldesign
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use PHPUnit\Framework\TestCase;

/**
 * Every token set's error colour is used as a FILL (`--color-error`), and
 * css/error-contrast.css pins the foreground painted on that fill to white —
 * because Nextcloud's own `--color-error-text` is a dark red meant for a pale
 * fill, and every nldesign set makes the fill saturated instead.
 *
 * That only stays readable while each set's error red is dark enough to carry
 * white text. This guards it: a future token set whose error colour is too
 * light would silently reintroduce the unreadable Delete button.
 *
 * @category Test
 * @package  NLDesign
 * @author   Conduction B.V. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/nldesign
 */
class ErrorForegroundContrastTest extends TestCase {

	/**
	 * WCAG 2.1 AA minimum for normal-size text.
	 *
	 * @var float
	 */
	private const AA_NORMAL_TEXT = 4.5;

	/**
	 * The foreground css/error-contrast.css paints on the error fill.
	 *
	 * @var string
	 */
	private const ON_FILL_FOREGROUND = '#ffffff';

	/**
	 * Every declared error token across all token sets must carry white text.
	 *
	 * @return void
	 */
	public function testEveryErrorFillCarriesWhiteTextAtAaContrast(): void {
		$service = new ContrastService();
		$white = $service->parseColor(self::ON_FILL_FOREGROUND);

		$tokenFiles = glob(__DIR__ . '/../../css/tokens/*.css');
		$this->assertNotEmpty(actual: $tokenFiles, message: 'No token sets found to audit.');

		$audited = 0;

		foreach ($tokenFiles as $file) {
			$css = file_get_contents($file);

			// Match the error fill token of either design system, e.g.
			// `--nldesign-color-error: #d52b1e;` / `--summer-color-error: #c0392b;`
			// but NOT the -light / -border variants, which are not fills.
			$matched = preg_match_all(
				'/--(?:nldesign|summer)-color-error:\s*(#[0-9a-fA-F]{3,8})\s*;/',
				$css,
				$matches
			);

			if ($matched === 0) {
				continue;
			}

			foreach ($matches[1] as $errorColor) {
				$parsed = $service->parseColor($errorColor);
				$this->assertNotNull(
					actual: $parsed,
					message: sprintf('Could not parse error colour "%s" in %s.', $errorColor, basename($file))
				);

				$ratio = $service->ratio($white, $parsed);
				$audited++;

				$this->assertGreaterThanOrEqual(
					expected: self::AA_NORMAL_TEXT,
					actual: $ratio,
					message: sprintf(
						'Token set "%s" declares error fill %s, which only reaches %.2f:1 against the white '
						. 'foreground that css/error-contrast.css paints on it (WCAG AA needs %.1f:1). Darken '
						. 'the error colour, or the Delete button becomes unreadable again.',
						basename($file),
						$errorColor,
						$ratio,
						self::AA_NORMAL_TEXT
					)
				);
			}//end foreach
		}//end foreach

		$this->assertGreaterThan(expected: 0, actual: $audited, message: 'No error tokens were audited — the regex stopped matching.');

	}//end testEveryErrorFillCarriesWhiteTextAtAaContrast()

}//end class
