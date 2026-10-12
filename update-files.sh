#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "🚀 Collecting updated files..."

MARKER=".last_update_check"
SYNC_EXCLUDE_FILE=".sync-update-ignore"
SYNC_EXCLUDES=()

load_sync_excludes() {
    local file_path="$1"
    SYNC_EXCLUDES=()

    if [ ! -f "$file_path" ]; then
        return
    fi

    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%%#*}"
        line="${line%$'\r'}"
        line="${line#${line%%[![:space:]]*}}"
        line="${line%${line##*[![:space:]]}}"

        if [ -z "$line" ]; then
            continue
        fi

        SYNC_EXCLUDES+=("$line")
    done < "$file_path"
}

should_skip_update_file() {
    local path="$1"
    local pattern

    case "$path" in
        public/lang/custom/*|updates/public/lang/custom/*)
            return 0
            ;;
    esac

    for pattern in "${SYNC_EXCLUDES[@]}"; do
        if [[ "$path" == $pattern ]] || [[ "updates/$path" == $pattern ]]; then
            return 0
        fi
    done

    return 1
}

load_sync_excludes "$SYNC_EXCLUDE_FILE"

mkdir -p updates

[ ! -f "$MARKER" ] && touch "$MARKER"

find public -type f -newer "$MARKER" \
! -path "public/cache/*" \
! -path "public/log/*" \
! -path "public/uploads/*" \
! -path "public/lang/custom/*" \
-print0 | while IFS= read -r -d '' file; do

    if should_skip_update_file "$file"; then
        echo "⏭ Skipped: $file"
        continue
    fi

    target="updates/$file"
    mkdir -p "$(dirname "$target")"
    cp -p "$file" "$target"

    echo "✔ Copied: $file"

done

for file in VERSION README.md CHANGELOG.md tools/language-split-tool.php tools/core-file-protection-audit.php docs/core-file-protection-standard-2026-06-04.md docs/core-protected-files.md docs/user-sso-metadata-migration-2026-09-03.sql; do
    if [ -f "$file" ] && [ "$file" -newer "$MARKER" ]; then
        if should_skip_update_file "$file"; then
            echo "⏭ Skipped: $file"
            continue
        fi

        target="updates/$file"
        mkdir -p "$(dirname "$target")"
        cp -p "$file" "$target"

        echo "✔ Copied: $file"
    fi
done

touch "$MARKER"

echo "✅ Done collecting updates!"
