<?php

/**
 * A thematiq round trip is exact: every shipped set, exported as DTCG and imported again
 * through the converter, gives every `--nldesign-*` declaration the same value.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-a-thematiq-round-trip-is-exact
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColorSpaceConverter;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssColorParser;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Export, import, compare.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-a-thematiq-round-trip-is-exact
 */
final class DesignTokensRoundTripTest extends TestCase {

	/**
	 * Every set in token-sets.json.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function shippedSets(): array {
		$sets = json_decode((string)file_get_contents(\dirname(__DIR__, 3) . '/token-sets.json'), true);
		$out = [];
		foreach ((array)$sets as $set) {
			$id = (string)($set['id'] ?? '');
			if ($id !== '' && is_file(\dirname(__DIR__, 3) . '/css/tokens/' . $id . '.css') === true) {
				$out[$id] = [$id];
			}
		}

		return $out;
	}//end shippedSets()

	/**
	 * Export and import one set's declarations.
	 *
	 * @param array<string, string> $declarations The set's declarations.
	 *
	 * @return array{declarations: array<string, string>, report: array<int, array<string, mixed>>}
	 */
	private function roundTrip(array $declarations): array {
		$document = (new DesignTokensWriter())->write(declarations: $declarations, setId: 'x', setName: 'X', appVersion: 'test');
		$json = (string)json_encode($document);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$converter = new TokenSetConverterService($appManager, $parser, new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class));
		$result = $converter->convert(content: $json, slug: 'kopie', displayName: 'Kopie');

		return ['declarations' => $parser->parseRootBlock(css: (string)$result['css']), 'report' => $result['report']];
	}//end roundTrip()

	/**
	 * A value with every var() to a name the set declares replaced by that value, so a
	 * palette step that moved to the new prefix compares by what it holds.
	 *
	 * @param string $value The value.
	 * @param array<string, string> $set The set's declarations.
	 * @param int $depth Recursion guard.
	 *
	 * @return string
	 */
	private function resolved(string $value, array $set, int $depth = 0): string {
		if ($depth > 10) {
			return $value;
		}

		return (string)preg_replace_callback(
			'/var\(\s*(--[A-Za-z0-9_-]+)\s*\)/',
			fn (array $m): string => isset($set[$m[1]]) === true ? $this->resolved(value: $set[$m[1]], set: $set, depth: ($depth + 1)) : $m[0],
			$value
		);
	}//end resolved()

	/**
	 * A value as compared: a colour as its sRGB hex, anything else with collapsed spaces.
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private function normalise(string $value): string {
		$parsed = (new CssColorParser())->parse(value: $value);
		if ($parsed !== null) {
			$converter = new ColorSpaceConverter();

			return $converter->toHex(rgb: (array)$converter->toSrgb(space: $parsed['space'], components: $parsed['components']), alpha: $parsed['alpha']);
		}

		return (string)preg_replace('/\s+/', ' ', trim($value));
	}//end normalise()

	/**
	 * Scenario: every shipped set survives the round trip.
	 *
	 * @param string $id The set id.
	 *
	 * @return void
	 */
	#[DataProvider('shippedSets')]
	public function testShippedSetSurvivesTheRoundTrip(string $id): void {
		$original = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(\dirname(__DIR__, 3) . '/css/tokens/' . $id . '.css'));
		$back = $this->roundTrip(declarations: $original)['declarations'];

		$differences = [];
		foreach ($original as $name => $value) {
			// The thematiq vocabulary: lower case, as the validator accepts it.
			if (preg_match('/^--nldesign-[a-z0-9-]+$/', $name) !== 1) {
				continue;
			}

			$returned = '(missing)';
			if (isset($back[$name]) === true) {
				$returned = $this->normalise(value: $this->resolved(value: $back[$name], set: $back));
			}

			if ($returned !== $this->normalise(value: $this->resolved(value: $value, set: $original))) {
				$differences[$name] = $value . ' => ' . $returned;
			}
		}

		$this->assertSame([], $differences, $id);
	}//end testShippedSetSurvivesTheRoundTrip()

	/**
	 * The report of a round trip lists no thematiq token as skipped.
	 *
	 * @return void
	 */
	public function testNoThematiqTokenIsSkipped(): void {
		$original = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(\dirname(__DIR__, 3) . '/css/tokens/amsterdam.css'));
		$skipped = array_filter(
			$this->roundTrip(declarations: $original)['report'],
			static fn (array $entry): bool => $entry['action'] === 'skipped' && str_starts_with((string)$entry['source'], '--nldesign-')
		);

		$this->assertSame([], array_values($skipped));
	}//end testNoThematiqTokenIsSkipped()

	/**
	 * Task 6.5: a set that declares only a few tokens comes back with only those; the
	 * defaults layer fills the rest at run time, as for the original.
	 *
	 * @return void
	 */
	public function testIncompleteSetStaysIncomplete(): void {
		$back = $this->roundTrip(declarations: ['--nldesign-color-primary' => '#154273', '--nldesign-border-radius' => '4px'])['declarations'];

		$this->assertSame(['--nldesign-border-radius' => '4px', '--nldesign-color-primary' => '#154273'], $back);
	}//end testIncompleteSetStaysIncomplete()
}//end class
