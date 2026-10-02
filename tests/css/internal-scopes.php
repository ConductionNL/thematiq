<?php

/**
 * Print the internal scopes InternalScopesService builds for the token names given as JSON.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Used by tests/css/check-internal-scopes.mjs, so the browser check runs the
 * server's own rules rather than a copy of them. Only build() is called, which
 * reads nothing but the shipped token map, so no Nextcloud server is needed.
 *
 * Usage: php tests/css/internal-scopes.php '{"light": [...], "any": [...]}'
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$args = json_decode($argv[1] ?? '{}', true);
$service = (new ReflectionClass(\OCA\Thematiq\Service\InternalScopesService::class))->newInstanceWithoutConstructor();
echo $service->build(lightNames: ($args['light'] ?? []), anyNames: ($args['any'] ?? []));
