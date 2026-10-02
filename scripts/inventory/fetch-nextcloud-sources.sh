#!/usr/bin/env bash
#
# Collect what extract-nextcloud-variables.mjs reads, from a running Nextcloud.
#
# SPDX-License-Identifier: EUPL-1.2
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
#
# Usage: scripts/inventory/fetch-nextcloud-sources.sh <out-dir> [container] [base-url]
#   container  default: nextcloud
#   base-url   default: http://localhost:8080
#
# It copies only the css/js/mjs files of core/, apps/ and dist/ (no
# node_modules, no source maps), fetches the four theming stylesheets as the
# theming app serves them, and records the Nextcloud version. Custom apps are
# deliberately left out: each ships its own copy of @nextcloud/vue, so they do
# not describe one Nextcloud release.

set -euo pipefail

OUT="${1:?usage: fetch-nextcloud-sources.sh <out-dir> [container] [base-url]}"
CONTAINER="${2:-nextcloud}"
BASE="${3:-http://localhost:8080}"

mkdir -p "$OUT/nc" "$OUT/theming"

# One tar reads the whole file list. `xargs tar` would split it into several
# archives in one stream, the receiving tar stops at the first end marker, and
# the sender dies of SIGPIPE with only the first batch copied.
docker exec "$CONTAINER" sh -c 'cd /var/www/html && find core apps dist -type f \( -name "*.css" -o -name "*.js" -o -name "*.mjs" -o -name "*.cjs" \) -not -path "*/node_modules/*" -print0 | tar --null -T - -cf -' \
	| tar -xf - -C "$OUT/nc"

expected=$(docker exec "$CONTAINER" sh -c 'cd /var/www/html && find core apps dist -type f \( -name "*.css" -o -name "*.js" -o -name "*.mjs" -o -name "*.cjs" \) -not -path "*/node_modules/*" | wc -l')
copied=$(find "$OUT/nc" -type f | wc -l)
if [ "$copied" -ne "$expected" ]; then
	echo "fetch-nextcloud-sources: copied $copied of $expected files, refusing to continue" >&2
	exit 1
fi

for theme in default dark light-highcontrast dark-highcontrast; do
	code=$(curl -s -o "$OUT/theming/$theme.css" -w '%{http_code}' "$BASE/index.php/apps/theming/theme/$theme.css")
	if [ "$code" != "200" ] || [ ! -s "$OUT/theming/$theme.css" ]; then
		echo "fetch-nextcloud-sources: $theme.css returned HTTP $code, refusing to continue" >&2
		exit 1
	fi
done

version=$(curl -s "$BASE/status.php" | sed -E 's/.*"version":"([^"]+)".*/\1/')
case "$version" in
	[0-9]*) ;;
	*) echo "fetch-nextcloud-sources: could not read the Nextcloud version from $BASE/status.php" >&2; exit 1 ;;
esac
printf '{ "nextcloud": "%s" }\n' "$version" > "$OUT/meta.json"

echo "fetch-nextcloud-sources: Nextcloud $version, $(find "$OUT/nc" -type f | wc -l) files, 4 theming stylesheets in $OUT"
