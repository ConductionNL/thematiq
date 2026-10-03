#!/usr/bin/env php
<?php

/**
 * Generate the token reference pages (no Nextcloud instance required).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Writes docs/reference/token-sets/<id>.md for every shipped token set with a token file,
 * plus index.md, from OCA\Thematiq\Service\TokenReferenceService, and removes pages no set
 * produces any more. tests/Unit/TokenReferenceDocsTest.php fails when the committed pages
 * differ (openspec/specs/token-reference/spec.md).
 *
 * Usage: composer docs:token-reference
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenReferenceService;

$root = dirname(__DIR__);
$dir = $root . '/docs/reference/token-sets';
if (is_dir($dir) === false) {
	mkdir($dir, 0755, true);
}

$parser = new CssParserService();
$pages = (new TokenReferenceService(new ShippedTokenSetAuditService(new ContrastService(), $parser), $parser))->docsPages(appPath: $root);

foreach (glob($dir . '/*.md') ?: [] as $existing) {
	if (isset($pages[basename($existing)]) === false) {
		unlink($existing);
	}
}

foreach ($pages as $name => $content) {
	file_put_contents($dir . '/' . $name, $content);
}

file_put_contents(
	$dir . '/_category_.json',
	json_encode(['label' => 'Token sets', 'collapsible' => true, 'collapsed' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

// The editable count the token editor states, written into the pages that state it.
$count = \OCA\Thematiq\Service\TokenRegistry::countEditable();
foreach (['docs/features/token-editor.md', 'docs/features/import-export.md'] as $page) {
	$text = (string)file_get_contents($root . '/' . $page);
	file_put_contents(
		$root . '/' . $page,
		(string)preg_replace('#<!-- editable-count -->\d+<!-- /editable-count -->#', '<!-- editable-count -->' . $count . '<!-- /editable-count -->', $text)
	);
}

fwrite(STDOUT, 'Wrote ' . count($pages) . ' pages to docs/reference/token-sets and the editable count (' . $count . ").\n");
