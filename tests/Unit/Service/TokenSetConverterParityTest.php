<?php

/**
 * Both converter runtimes produce the same token set for the same input.
 *
 * tests/Unit/fixtures/converter/<name>.expected.json holds what js/lib/tokenConverter.js emits
 * (written by `npm run convert:theme:check -- --write`, checked by tests/vitest/tokenConverter.spec.js).
 * The admin upload converts through TokenSetConverterService and the repository through the
 * JavaScript module; both read scripts/mapping/nlds-to-nextcloud.json, so a theme must come out
 * byte-equal either way, provenance hash included.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md#requirement-semantic-mapping-is-table-driven-and-shared
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Parity of the PHP converter with the committed JavaScript expectations.
 */
class TokenSetConverterParityTest extends TestCase {

	private const FIXTURE_DIR = __DIR__ . '/../fixtures/converter';

	/**
	 * Build the converter against the app's own mapping table.
	 *
	 * @return TokenSetConverterService
	 */
	private function converter(): TokenSetConverterService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end converter()

	/**
	 * The fixtures named in fixtures.json.
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function fixtureProvider(): array {
		$list = json_decode((string)file_get_contents(self::FIXTURE_DIR . '/fixtures.json'), true);
		$cases = [];
		foreach ($list['fixtures'] as $fixture) {
			$cases[$fixture['name']] = [$fixture];
		}

		return $cases;
	}//end fixtureProvider()

	/**
	 * Convert one fixture through PHP.
	 *
	 * @param array<string, string> $fixture The fixture entry.
	 *
	 * @return array<string, mixed> The PHP result.
	 */
	private function convert(array $fixture): array {
		$content = (string)file_get_contents(\dirname(__DIR__, 3) . '/' . $fixture['input']);

		return $this->converter()->convert(
			content: $content,
			slug: $fixture['slug'],
			displayName: $fixture['displayName'],
			sourceName: $fixture['sourceName']
		);
	}//end convert()

	/**
	 * The expectation the JavaScript runtime wrote.
	 *
	 * @param string $name The fixture name.
	 *
	 * @return array<string, mixed>
	 */
	private function expected(string $name): array {
		return json_decode((string)file_get_contents(self::FIXTURE_DIR . '/' . $name . '.expected.json'), true);
	}//end expected()

	/**
	 * The emitted CSS is byte-equal, provenance block and mapping table hash included.
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param array<string, string> $fixture The fixture entry.
	 *
	 * @return void
	 */
	public function testTheEmittedCssIsByteEqual(array $fixture): void {
		$result = $this->convert($fixture);
		$expected = $this->expected($fixture['name']);

		$this->assertSame($expected['inputKind'], $result['inputKind']);
		$this->assertSame($expected['css'], $result['css']);
	}//end testTheEmittedCssIsByteEqual()

	/**
	 * The report holds the same entries in the same order, and the counts agree.
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param array<string, string> $fixture The fixture entry.
	 *
	 * @return void
	 */
	public function testTheReportIsStructurallyEqual(array $fixture): void {
		$result = $this->convert($fixture);
		$expected = $this->expected($fixture['name']);

		$shape = static fn (array $entry): array => [
			'source' => (string)$entry['source'],
			'target' => (string)$entry['target'],
			'action' => (string)$entry['action'],
			'reason' => $entry['reason'] ?? null,
			'value' => (isset($entry['value']) === true ? (string)$entry['value'] : null),
		];

		$this->assertSame(array_map($shape, $expected['report']), array_map($shape, $result['report']));
		$this->assertSame($expected['counts'], $result['counts']);
	}//end testTheReportIsStructurallyEqual()

	/**
	 * The manifest entry and the extracted logo agree.
	 *
	 * @dataProvider fixtureProvider
	 *
	 * @param array<string, string> $fixture The fixture entry.
	 *
	 * @return void
	 */
	public function testTheManifestEntryAndLogoAgree(array $fixture): void {
		$result = $this->convert($fixture);
		$expected = $this->expected($fixture['name']);

		$this->assertEquals($expected['manifestEntry'], $result['manifestEntry']);

		$logo = $result['logoAsset'];
		$this->assertSame(
			$expected['logoAsset'],
			($logo === null) ? null : ['path' => $logo['path'], 'base64' => base64_encode($logo['contents'])]
		);
	}//end testTheManifestEntryAndLogoAgree()
}//end class
