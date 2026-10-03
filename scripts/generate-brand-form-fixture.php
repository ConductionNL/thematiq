#!/usr/bin/env php
<?php

/**
 * Writes tests/Unit/fixtures/brand-form-parity.json: the brand form inputs and what PHP derives
 * for a few colour pairs. BrandFormServiceTest checks PHP against it, tests/vitest/brandForm.spec.js
 * checks the JS mirror against it. Rerun after changing scripts/mapping/brand-form.json or defaults.css.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OCA\Thematiq\Service\BrandFormService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;

$root = dirname(__DIR__);
$service = new BrandFormService(new ContrastService(), new CssParserService());
$cases = [];
foreach ([['#c8102e', '#ffffff'], ['#ffd200', '#ffffff'], ['#808080', '#ffffff'], ['#154273', '#f5f5f5'], ['#0a0', '#FFF']] as [$primary, $background]) {
	$cases[] = [
		'primary' => $primary,
		'background' => $background,
		'expected' => $service->derive(appPath: $root, primary: $primary, background: $background),
	];
}

$json = json_encode(['inputs' => $service->inputs(appPath: $root), 'cases' => $cases], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
file_put_contents($root . '/tests/Unit/fixtures/brand-form-parity.json', $json . "\n");
echo "Wrote tests/Unit/fixtures/brand-form-parity.json\n";
