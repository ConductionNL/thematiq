<?php

/**
 * Regression guard for the NcNoteCard fill contrast (nldesign#268).
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
 * NcNoteCard paints its whole surface with `--note-background`, which resolves
 * to `--color-<status>`. Stock Nextcloud 34 makes those four tokens pale fills
 * (`--color-success` is #D8F3DA) that carry near-black body text; every nldesign
 * token set makes them saturated brand colours instead, which inverts the
 * contract the component was built against. Measured on a live NC 34 instance,
 * changing only whether the app was enabled: 3.94:1 on success, 3.95:1 on info,
 * and 2.44:1 / 2.06:1 on `hoog-contrast` — the set whose purpose is contrast.
 *
 * css/error-contrast.css restores the pale fill at the component, by mixing the
 * brand colour 15% into `--color-main-background`. This guards the arithmetic
 * that makes that safe for EVERY shipped set, present and future: a set whose
 * status colour is so light that a 15% tint no longer separates from the body
 * text would silently reintroduce an unreadable note card.
 *
 * @category Test
 * @package  NLDesign
 * @author   Conduction B.V. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/nldesign
 */
class NoteCardFillContrastTest extends TestCase {

	/**
	 * WCAG 2.1 AA minimum for normal-size text.
	 *
	 * @var float
	 */
	private const AA_NORMAL_TEXT = 4.5;

	/**
	 * The mix ratio css/error-contrast.css applies to the status fill.
	 *
	 * Kept in step with the `color-mix(in srgb, var(--color-<status>) 15%, …)`
	 * declarations there. If that percentage moves, this constant must move
	 * with it or the test measures a fill the browser never paints.
	 *
	 * @var float
	 */
	private const TINT_RATIO = 0.15;

	/**
	 * The surface the tint is mixed against in the light theme.
	 *
	 * @var string
	 */
	private const LIGHT_BACKGROUND = '#ffffff';

	/**
	 * The status fills NcNoteCard paints.
	 *
	 * @var string[]
	 */
	private const STATUSES = ['error', 'warning', 'success', 'info'];

	/**
	 * Mix a colour into a background the way CSS `color-mix(in srgb, …)` does.
	 *
	 * ContrastService::parseColor() returns an INDEXED [r, g, b] triple, not a
	 * keyed map. Reading it with 'r'/'g'/'b' keys yields nulls that coerce to 0,
	 * i.e. pure black — which does not error, it just produces a confident wrong
	 * answer (every set "failed" at ~1.3:1 against a #000000 tint).
	 *
	 * @param array<int,int|float> $color Parsed foreground colour.
	 * @param array<int,int|float> $background Parsed background colour.
	 * @param float $ratio Share of $color, 0..1.
	 *
	 * @return array<int,int> The mixed colour, in the shape ContrastService returns.
	 */
	private function mix(array $color, array $background, float $ratio): array {
		$mixed = [];
		foreach ([0, 1, 2] as $channel) {
			$mixed[$channel] = (int)round(
				(($color[$channel] * $ratio) + ($background[$channel] * (1.0 - $ratio)))
			);
		}

		return $mixed;
	}//end mix()

	/**
	 * Every token set's note-card tint must carry that set's body text at AA.
	 *
	 * @return void
	 */
	public function testEveryStatusFillTintCarriesBodyTextAtAaContrast(): void {
		$service = new ContrastService();
		$background = $service->parseColor(self::LIGHT_BACKGROUND);

		$tokenFiles = glob(__DIR__ . '/../../css/tokens/*.css');
		$this->assertNotEmpty(actual: $tokenFiles, message: 'No token sets found to audit.');

		$audited = 0;

		foreach ($tokenFiles as $file) {
			$css = file_get_contents($file);

			// The body text colour this set paints on the note card. Sets that
			// declare no text token inherit the previous layer's, which this
			// file cannot see — skip rather than guess.
			$hasText = preg_match(
				'/--(?:nldesign|summer)-color-text:\s*(#[0-9a-fA-F]{3,8})\s*;/',
				$css,
				$textMatch
			);

			if ($hasText === 0) {
				continue;
			}

			$text = $service->parseColor($textMatch[1]);
			$this->assertNotNull(
				actual: $text,
				message: sprintf('Could not parse text colour "%s" in %s.', $textMatch[1], basename($file))
			);

			foreach (self::STATUSES as $status) {
				$matched = preg_match(
					sprintf('/--(?:nldesign|summer)-color-%s:\s*(#[0-9a-fA-F]{3,8})\s*;/', $status),
					$css,
					$matches
				);

				if ($matched === 0) {
					continue;
				}

				$fill = $service->parseColor($matches[1]);
				$this->assertNotNull(
					actual: $fill,
					message: sprintf('Could not parse %s colour "%s" in %s.', $status, $matches[1], basename($file))
				);

				$tint = $this->mix(color: $fill, background: $background, ratio: self::TINT_RATIO);
				$ratio = $service->ratio($text, $tint);
				$audited++;

				$this->assertGreaterThanOrEqual(
					expected: self::AA_NORMAL_TEXT,
					actual: $ratio,
					message: sprintf(
						'Token set "%s" declares %s fill %s. css/error-contrast.css paints note cards with a '
						. '%d%% tint of it (#%02x%02x%02x), and this set\'s body text %s only reaches %.2f:1 on '
						. 'that tint — WCAG AA needs %.1f:1. Darken the text token or the status colour; do NOT '
						. 'raise the tint percentage, which is what makes the fill readable in the first place.',
						basename($file),
						$status,
						$matches[1],
						(int)(self::TINT_RATIO * 100),
						$tint[0],
						$tint[1],
						$tint[2],
						$textMatch[1],
						$ratio,
						self::AA_NORMAL_TEXT
					)
				);
			}//end foreach
		}//end foreach

		$this->assertGreaterThan(expected: 0, actual: $audited, message: 'No status fills were audited — the regex stopped matching.');

	}//end testEveryStatusFillTintCarriesBodyTextAtAaContrast()

	/**
	 * The stylesheet must actually pin all four fills.
	 *
	 * The arithmetic above is only worth anything while the CSS that produces
	 * the tint is present. A silent revert of css/error-contrast.css would keep
	 * this class green on the maths alone.
	 *
	 * @return void
	 */
	public function testErrorContrastStylesheetPinsEveryStatusFill(): void {
		$css = file_get_contents(__DIR__ . '/../../css/error-contrast.css');
		$this->assertNotFalse(condition: $css, message: 'css/error-contrast.css is missing.');

		// `\s*` sits after every `(` on purpose. The assertion is about the
		// DECLARATION, not its layout: prettier wraps a long `color-mix()` with
		// one argument per line, and this test must not be the reason a
		// stylesheet cannot be formatted.
		foreach (self::STATUSES as $status) {
			$this->assertMatchesRegularExpression(
				pattern: sprintf(
					'/\.notecard--%s\s*\{[^}]*--note-background:\s*color-mix\(\s*in srgb,\s*var\(\s*--color-%s\s*\)\s*%d%%/',
					$status,
					$status,
					(int)(self::TINT_RATIO * 100)
				),
				string: $css,
				message: sprintf(
					'css/error-contrast.css no longer pins .notecard--%s to a %d%% tint of --color-%s. '
					. 'Without it the note card paints the saturated brand fill under near-black text again '
					. '(nldesign#268).',
					$status,
					(int)(self::TINT_RATIO * 100),
					$status
				)
			);
		}

	}//end testErrorContrastStylesheetPinsEveryStatusFill()

}//end class
