#!/usr/bin/env php
<?php
/**
 * Write the PHPUnit configuration for one shard of the test suite.
 *
 * Usage:
 *   php scripts/phpunit-shard-config.php <shard> <total> [<config>] [<output>]
 *
 * PHPUnit has no `--shard` option, so the CI job splits the suite itself. This
 * reads the test directories from the `<testsuites>` of <config> (default
 * phpunit.xml), lists every `*Test.php` under them sorted by path, and gives
 * shard N of T every T-th file starting at the N-th. The output (default
 * phpunit-shard.xml) is <config> with its `<testsuites>` replaced by one suite
 * of those files, so the bootstrap, the source filter and every other setting
 * stay exactly as the unsharded run has them.
 *
 * Round robin over sorted paths spreads the slow catalogue-wide tests, which
 * sit next to each other by name, across the shards. Every file lands on
 * exactly one shard by construction; the script prints the counts so a run
 * shows it.
 *
 * Exit codes:
 *   0 — the configuration is written
 *   1 — the configuration has no test directories, or the shard holds no file
 *   2 — wrong arguments
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

$shard = (int)($argv[1] ?? 0);
$total = (int)($argv[2] ?? 0);
$config = ($argv[3] ?? 'phpunit.xml');
$output = ($argv[4] ?? 'phpunit-shard.xml');
if ($total < 1 || $shard < 1 || $shard > $total || is_file($config) === false) {
	fwrite(STDERR, "Usage: php scripts/phpunit-shard-config.php <shard> <total> [<config>] [<output>]\n");
	exit(2);
}

$xml = (string)file_get_contents($config);
if (preg_match('#<testsuites>.*?</testsuites>#s', $xml, $block, PREG_OFFSET_CAPTURE) !== 1) {
	fwrite(STDERR, "{$config} has no <testsuites>.\n");
	exit(1);
}

preg_match_all('#<directory[^>]*>\s*([^<]+?)\s*</directory>#', $block[0][0], $directories);
$files = [];
foreach ($directories[1] as $directory) {
	$base = dirname($config) . '/' . $directory;
	if (is_dir($base) === false) {
		continue;
	}

	$walker = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
	foreach ($walker as $entry) {
		if (str_ends_with($entry->getFilename(), 'Test.php') === true) {
			$files[] = $directory . '/' . substr($entry->getPathname(), strlen($base) + 1);
		}
	}
}

$files = array_values(array_unique(str_replace('\\', '/', $files)));
sort($files);
$mine = array_values(array_filter($files, static fn (int $index): bool => $index % $total === $shard - 1, ARRAY_FILTER_USE_KEY));
if ($mine === []) {
	fwrite(STDERR, "Shard {$shard} of {$total} holds no test file; lower the shard count.\n");
	exit(1);
}

$entries = implode("\n", array_map(static fn (string $file): string => '            <file>' . htmlspecialchars($file, ENT_XML1) . '</file>', $mine));
$suite = "<testsuites>\n        <testsuite name=\"Shard {$shard} of {$total}\">\n{$entries}\n        </testsuite>\n    </testsuites>";
file_put_contents($output, substr_replace($xml, $suite, $block[0][1], strlen($block[0][0])));
echo 'Shard ' . $shard . ' of ' . $total . ': ' . count($mine) . ' of ' . count($files) . " test files, written to {$output}.\n";
