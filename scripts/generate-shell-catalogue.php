#!/usr/bin/env php
<?php

/**
 * Write docs/reference/shell-catalogue.json: every shipped token set as the
 * eighteen shell roles, with what this repository says about its logo and fonts.
 *
 * The roles come from ShellRoleMapper, the class the shell tokens endpoint
 * uses, so the catalogue and the endpoint cannot disagree about a set. Only
 * files are read: no instance, no administrator's values.
 *
 * Usage:
 *   php scripts/generate-shell-catalogue.php
 *       Write the committed catalogue. tests/Unit/ShellCatalogueTest.php fails
 *       when it is stale.
 *   php scripts/generate-shell-catalogue.php --copy-to <dir> [--from <text>]
 *       Also write <dir>/shell-catalogue.json and copy every logo marked
 *       redistributable to <dir>/logos/, keeping file names. --from names the
 *       source in that copy's `generated_from`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Script
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-the-catalogue-is-reproducible
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShellCatalogueService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;

$root = dirname(__DIR__);
$service = new ShellCatalogueService(
	new ShippedTokenSetAuditService(new ContrastService(), new CssParserService()),
	new ShellRoleMapper(new ContrastService())
);

$catalogue = $service->build($root);
file_put_contents($root . '/docs/reference/shell-catalogue.json', $service->render($catalogue));
fwrite(STDOUT, 'Wrote docs/reference/shell-catalogue.json: ' . count($catalogue['sets']) . ' sets, ' . count($catalogue['fonts']) . " font families.\n");

$copyIndex = array_search('--copy-to', $argv, true);
if ($copyIndex === false) {
	exit(0);
}

$target = rtrim((string)($argv[($copyIndex + 1)] ?? ''), '/');
if ($target === '') {
	fwrite(STDERR, "--copy-to needs a directory.\n");
	exit(1);
}

$fromIndex = array_search('--from', $argv, true);
$copy = $catalogue;
if ($fromIndex !== false && ($argv[($fromIndex + 1)] ?? '') !== '') {
	$copy['generated_from'] = $argv[($fromIndex + 1)] . ' ' . $catalogue['generated_from'];
}

if (is_dir($target . '/logos') === false && mkdir($target . '/logos', 0755, true) === false) {
	fwrite(STDERR, 'Could not create ' . $target . "/logos.\n");
	exit(1);
}

file_put_contents($target . '/shell-catalogue.json', $service->render($copy));

$logos = $service->redistributableLogos($catalogue);
foreach ($logos as $logo) {
	if (copy($root . '/' . $logo, $target . '/logos/' . basename($logo)) === false) {
		fwrite(STDERR, 'Could not copy ' . $logo . ".\n");
		exit(1);
	}
}

fwrite(STDOUT, 'Wrote ' . $target . '/shell-catalogue.json and copied ' . count($logos) . ' logo files to ' . $target . "/logos.\n");
