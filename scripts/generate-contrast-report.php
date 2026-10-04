#!/usr/bin/env php
<?php

/**
 * Write docs/reference/contrast-report.md (no Nextcloud instance required).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Renders the report with ShippedTokenSetAuditService::renderReport(), the same call
 * tests/Unit/TokenSetContrastAuditTest.php compares the committed file to, so a run of
 * this script is what makes that test's staleness check pass after a token set changes
 * (openspec/specs/token-set-contrast-audit/spec.md).
 *
 * Usage: composer docs:contrast-report
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\ContrastVerdictDocument;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;

$root = dirname(__DIR__);
$service = new ShippedTokenSetAuditService(new ContrastService(), new CssParserService());

file_put_contents($root . '/docs/reference/contrast-report.md', $service->renderReport($root));
fwrite(STDOUT, "Wrote docs/reference/contrast-report.md.\n");

// The same verdicts as data, for scripts/audit-token-sets.mjs: one contrast
// engine, two files, so the Node coverage table cannot disagree with the PHP
// report about whether a set passes.
file_put_contents(
    $root . '/docs/reference/contrast-report.json',
    (new ContrastVerdictDocument())->render($service->auditAll($root))
);
fwrite(STDOUT, "Wrote docs/reference/contrast-report.json.\n");
