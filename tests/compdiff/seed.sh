#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Conduction B.V.
# SPDX-License-Identifier: EUPL-1.2
#
# Seeds what the TqEigenSets and TqLettertypen boards draw a dialog over, so
# their rows in admin-boards.tsv have a row to open Delete on. Run it against a
# disposable instance; it writes, compdiff itself never does.
#
#   seed.sh          the Zuiddrecht set active and an own token set
#                    "Zuiddrecht koningsdag" (TqEigenSets). Run before runall.sh.
#   seed.sh font     an uploaded font "Zuiddrecht Text" (TqLettertypen). An
#                    uploaded font becomes the body face of EVERY page, so it
#                    skews every other board: measure TqLettertypen on its own.
#   seed.sh unfont   removes that font again.
#
# The font file is a bundled WOFF2 under another name.
#
# Usage: BASE=http://localhost:8095 NC_USER=admin NC_PASS=admin tests/compdiff/seed.sh [font|unfont]
set -euo pipefail
BASE=${BASE:-http://localhost:8080}
NC_USER=${NC_USER:-admin}
NC_PASS=${NC_PASS:-admin}
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
api() { curl -sS -u "$NC_USER:$NC_PASS" -H 'OCS-APIRequest: true' "$@"; echo; }

case "${1:-}" in
font)
	api -F 'name=Zuiddrecht Text' -F 'role=body' \
		-F "font=@$DIR/css/systems/lasuite/fonts/marianne/Marianne-Bold.woff2;type=font/woff2;filename=ZuiddrechtText.woff2" \
		"$BASE/index.php/apps/thematiq/settings/fonts/upload"
	;;
unfont)
	api -X DELETE "$BASE/index.php/apps/thematiq/settings/fonts/custom-zuiddrecht-text"
	;;
*)
	api -X POST "$BASE/index.php/apps/thematiq/settings/tokenset" -d 'tokenSet=zuiddrecht'
	api -X POST "$BASE/index.php/apps/thematiq/settings/tokensets/from-colours" \
		--data-urlencode 'name=Zuiddrecht koningsdag' \
		--data-urlencode 'primary=#E17000' \
		--data-urlencode 'background=#FFFFFF' \
		--data-urlencode 'action=#1E7A3C'
	;;
esac
