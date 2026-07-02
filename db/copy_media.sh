#!/usr/bin/env bash
#
# All The Venues — legacy media copy (U1b), SERVER-RUN step.
#
# The catalogue transform (db/migrate_catalogue.php) rewrites image/doc paths
# to the new LOCAL scheme but does NOT move files (it can't reach prod files
# from a dev machine). This script copies the referenced files from the legacy
# images tree into the new app's uploads/ tree so they serve from 'self'
# (the tight CSP allows no external image hosts).
#
# Path scheme (matches migrate_catalogue.php):
#   venue images   -> uploads/venues/images/<filename>
#   venue main img  -> uploads/venues/images/<filename>   (same folder)
#   venue documents -> uploads/venues/documents/<filename>
#   partner logos   -> uploads/partners/<filename>
#
# It reads the TARGET file paths straight from the migrated DB (sameraou_atv2),
# takes each basename, locates it anywhere under the legacy images root, and
# copies it to the new app tree. Idempotent: existing files are skipped.
#
# Run AFTER migrate_catalogue.php, ON THE SERVER:
#   bash db/copy_media.sh
#
# Override defaults via environment variables as needed.

set -euo pipefail

LEGACY_IMAGES_ROOT="${LEGACY_IMAGES_ROOT:-/home1/sameraou/public_html/allthevenues/images}"
NEW_APP_ROOT="${NEW_APP_ROOT:-/home1/sameraou/atv-staging}"

DB_HOST="${ATV_NEW_DB_HOST:-localhost}"
DB_NAME="${ATV_NEW_DB_NAME:-sameraou_atv2}"
DB_USER="${ATV_NEW_DB_USER:-sameraou_atv2}"
DB_PASS="${ATV_NEW_DB_PASS:?Set ATV_NEW_DB_PASS to the sameraou_atv2 DB password}"

echo "== ATV media copy =="
echo "  legacy images root: $LEGACY_IMAGES_ROOT"
echo "  new app root:       $NEW_APP_ROOT"

if [ ! -d "$LEGACY_IMAGES_ROOT" ]; then
    echo "ERROR: legacy images root not found: $LEGACY_IMAGES_ROOT" >&2
    exit 1
fi

# Collect every referenced target path from the migrated DB.
SQL="
SELECT file_path FROM venue_images WHERE file_path IS NOT NULL AND file_path<>''
UNION
SELECT main_image FROM venues WHERE main_image IS NOT NULL AND main_image<>''
UNION
SELECT file_path FROM venue_documents WHERE file_path IS NOT NULL AND file_path<>''
UNION
SELECT logo_path FROM partners WHERE logo_path IS NOT NULL AND logo_path<>'';
"

mapfile -t PATHS < <(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -N -B "$DB_NAME" -e "$SQL")

copied=0; skipped=0; missing=0
for rel in "${PATHS[@]}"; do
    [ -z "$rel" ] && continue
    base="$(basename "$rel")"
    dest="$NEW_APP_ROOT/$rel"

    if [ -f "$dest" ]; then
        skipped=$((skipped+1))
        continue
    fi

    # Locate the source file by basename anywhere under the legacy tree.
    src="$(find "$LEGACY_IMAGES_ROOT" -type f -name "$base" -print -quit 2>/dev/null || true)"
    if [ -z "$src" ]; then
        echo "  MISSING: $base"
        missing=$((missing+1))
        continue
    fi

    mkdir -p "$(dirname "$dest")"
    cp -p "$src" "$dest"
    copied=$((copied+1))
done

echo "-- done: copied=$copied skipped(existing)=$skipped missing=$missing (of ${#PATHS[@]} referenced) --"
[ "$missing" -eq 0 ] || echo "NOTE: some source files were not found under the legacy tree (see MISSING above)."
