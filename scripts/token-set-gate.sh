#!/usr/bin/env bash
# SPDX-License-Identifier: EUPL-1.2
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
#
# The shipped token-set gate. Regenerates everything that follows from the token files
# (dark variants of changed sets, the contrast report, the token reference pages), then
# runs the shipped token-set tests (phpunit.token-sets.xml). Exits non-zero when any test
# fails. scripts/sync-upstream-tokens.mjs runs it to accept or reject each converted set,
# and a person can run it after editing a set by hand: `bash scripts/token-set-gate.sh`.
set -euo pipefail
cd "$(dirname "$0")/.."

# Without --force only sets whose token file changed are regenerated, so a hand-tuned
# dark variant of an untouched set is never rewritten.
php scripts/generate-dark-variants.php > /dev/null
php scripts/generate-contrast-report.php > /dev/null
php scripts/generate-token-reference.php > /dev/null
php vendor/bin/phpunit --no-coverage -c phpunit.token-sets.xml
