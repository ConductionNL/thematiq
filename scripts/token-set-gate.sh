#!/usr/bin/env bash
# SPDX-License-Identifier: EUPL-1.2
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
#
# The shipped token-set gate. Regenerates everything that follows from the token files
# (dark variants of changed sets, the contrast report, the token reference pages, the
# coverage counts), then runs the shipped token-set tests (phpunit.token-sets.xml and the
# vitest specs that read the sets). Exits non-zero when any test
# fails. scripts/sync-upstream-tokens.mjs runs it to accept or reject each converted set,
# and a person can run it after editing a set by hand: `bash scripts/token-set-gate.sh`.
set -euo pipefail
cd "$(dirname "$0")/.."

# Without --force only sets whose token file changed are regenerated, so a hand-tuned
# dark variant of an untouched set is never rewritten.
php scripts/generate-dark-variants.php > /dev/null
php scripts/generate-contrast-report.php > /dev/null
php scripts/generate-token-reference.php > /dev/null
# The set counts css/public-bridge.css and the public-portals doc state.
node scripts/update-coverage-claims.mjs > /dev/null
# The per-set instance-coverage table (bridge/font/logo/contrast). Regenerated
# here for the same reason the contrast report is: a set change moves the numbers,
# and tests/vitest/tokenSetCoverage.spec.js compares the committed file to a fresh
# render, so a sync that forgets this step fails rather than publishing stale
# coverage claims.
node scripts/audit-token-sets.mjs --markdown > /dev/null
php vendor/bin/phpunit --no-coverage -c phpunit.token-sets.xml
# The JavaScript tests that read the shipped sets (needs npm ci).
npx vitest run \
	tests/vitest/denhaagBridge.spec.js \
	tests/vitest/fontLicences.spec.js \
	tests/vitest/tokenSetCoverage.spec.js \
	tests/vitest/focusRingContrast.spec.js \
	tests/vitest/frankendeskTokenSet.spec.js \
	tests/vitest/layerSwap.spec.js \
	tests/vitest/layerSwapDom.spec.js \
	tests/vitest/playgroundSelection.spec.js \
	tests/vitest/rotterdamBrandSet.spec.js \
	tests/vitest/generateTokensManifest.spec.js \
	tests/vitest/syncUpstreamTokens.spec.js \
	tests/vitest/zuiddrechtTokenSet.spec.js \
	tests/vitest/schoolTokenSets.spec.js \
	tests/vitest/publicBridgeRoleLayer.spec.js \
	tests/vitest/componentScopesGenerator.spec.js \
	tests/vitest/workplaceLayout.spec.js
