#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Conduction B.V.
# SPDX-License-Identifier: EUPL-1.2
#
# Seeds what the TqEigenSets and TqLettertypen boards draw a dialog over, so
# their rows in admin-boards.tsv have a row to open Delete on: an own token
# set "Zuiddrecht koningsdag" and an uploaded font "Zuiddrecht Text". Run it
# once against a disposable instance before runall.sh; it writes, compdiff
# itself never does. The font file is a bundled WOFF2 under another name.
#
# Usage: BASE=http://localhost:8095 USER=admin PASS=admin tests/compdiff/seed.sh
set -euo pipefail
BASE=${BASE:-http://localhost:8080}
USER=${USER:-admin}
PASS=${PASS:-admin}
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
api() { curl -sS -u "$USER:$PASS" -H 'OCS-APIRequest: true' "$@"; echo; }

api -X POST "$BASE/index.php/apps/thematiq/settings/tokenset" -d 'tokenSet=zuiddrecht'
api -X POST "$BASE/index.php/apps/thematiq/settings/tokensets/from-colours" \
	--data-urlencode 'name=Zuiddrecht koningsdag' \
	--data-urlencode 'primary=#E17000' \
	--data-urlencode 'background=#FFFFFF' \
	--data-urlencode 'action=#1E7A3C'
api -F 'name=Zuiddrecht Text' -F 'role=body' \
	-F "font=@$DIR/css/systems/lasuite/fonts/marianne/Marianne-Bold.woff2;type=font/woff2;filename=ZuiddrechtText.woff2" \
	"$BASE/index.php/apps/thematiq/settings/fonts/upload"
