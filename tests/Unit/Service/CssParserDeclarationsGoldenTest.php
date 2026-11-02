<?php

/**
 * Golden test for CssParserService::parseDeclarations().
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-27
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Parses every stylesheet the app ships (recorded as a hash of the result) and seeded strings
 * built from the characters the scanner treats specially: quotes, escapes, parentheses,
 * comments, terminators and `--name:` starts (recorded in full). Compares each case with the
 * recording. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\CssParserService
 */
class CssParserDeclarationsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/css-parser-declarations.golden.json';

	private const PIECES = ['--a', '--b-c', ':', ' ', ';', '}', '{', '(', ')', '"', "'", '\\', '/*', '*/', '/', '*', 'url(', 'x', '1px', '!important', '! important', "\n", ':root {', 'data:a;b', '', '--a: v;', '--b:"x;y"', "--c: url('a;b')", '--d: 2px ', '--e:', ' --f : g }'];

	/**
	 * Every case parses to the recorded result.
	 *
	 * @return void
	 */
	public function testDeclarationsMatchTheGoldenRecording(): void {
		$parser = new CssParserService();
		$actual = [];

		$root = dirname(__DIR__, 3) . '/css';
		$files = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
			if (str_ends_with($file->getPathname(), '.css') === true) {
				$files[] = $file->getPathname();
			}
		}

		sort($files);
		foreach ($files as $path) {
			$result = $parser->parseDeclarations(content: (string)file_get_contents($path));
			$actual['file:' . substr($path, strlen($root) + 1)] = hash('sha256', (string)json_encode($result, JSON_UNESCAPED_SLASHES));
		}

		for ($seed = 1; $seed <= 400; $seed++) {
			$random = new Randomizer(new Mt19937($seed));
			$content = '';
			$count = $random->getInt(1, 40);
			for ($i = 0; $i < $count; $i++) {
				$content .= self::PIECES[$random->getInt(0, (count(self::PIECES) - 1))];
			}

			$actual['seed:' . $seed] = (string)json_encode(
				['in' => $content, 'out' => $parser->parseDeclarations(content: $content)],
				JSON_UNESCAPED_SLASHES
			);
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $value) {
			self::assertSame($value, $actual[$name], $name);
		}
	}//end testDeclarationsMatchTheGoldenRecording()
}//end class
