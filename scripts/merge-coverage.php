#!/usr/bin/env php
<?php
/**
 * Merge the coverage of PHPUnit shards into one clover report.
 *
 * Usage:
 *   php scripts/merge-coverage.php <clover.xml> <shard.cov> [<shard.cov> ...]
 *
 * Each shard of the CI PHPUnit job writes its coverage with `--coverage-php`:
 * a PHP file that returns the CodeCoverage object. This script loads them,
 * merges them in order and writes one clover report, the file the coverage
 * guard reads. It is what `phpcov merge --clover` does, without adding phpcov
 * to the dependencies: php-code-coverage already comes with PHPUnit.
 *
 * Run it from the app root: it loads that checkout's vendor/autoload.php, so a
 * copy kept outside the checkout (to merge the merge base's shards) works too.
 * The shards must run on the same checkout path, because the coverage records
 * absolute file names and the clover writer reads the files.
 *
 * Exit codes:
 *   0 — the report is written
 *   1 — a shard file is missing or holds no coverage
 *   2 — wrong arguments
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;

require getcwd() . '/vendor/autoload.php';

$target = ($argv[1] ?? '');
$shards = array_slice($argv, 2);
if ($target === '' || $shards === []) {
	fwrite(STDERR, "Usage: php scripts/merge-coverage.php <clover.xml> <shard.cov> [<shard.cov> ...]\n");
	exit(2);
}

$merged = null;
foreach ($shards as $file) {
	$coverage = (is_file($file) === true) ? (include $file) : null;
	if (($coverage instanceof CodeCoverage) === false) {
		fwrite(STDERR, "No coverage in {$file}.\n");
		exit(1);
	}

	if ($merged === null) {
		$merged = $coverage;
		continue;
	}

	$merged->merge($coverage);
}

(new Clover())->process($merged, $target);
echo 'Merged ' . count($shards) . " shard(s) into {$target}.\n";
