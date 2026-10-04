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
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;

$root = dirname(__DIR__);
$report = (new ShippedTokenSetAuditService(new ContrastService(), new CssParserService()))->renderReport($root);

file_put_contents($root . '/docs/reference/contrast-report.md', $report);
fwrite(STDOUT, "Wrote docs/reference/contrast-report.md.\n");
