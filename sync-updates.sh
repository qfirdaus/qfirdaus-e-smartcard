#!/bin/bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_PATH="${BASE_PATH:-$(cd "$SCRIPT_DIR/.." && pwd)}"
MASTER_PROJECT="iqs-framework"
UPDATES_DIR="$SCRIPT_DIR/updates"
MANIFEST_FILE="$UPDATES_DIR/.sync-manifest.tsv"
MANIFEST_HASH_FILE="$UPDATES_DIR/.sync-manifest.sha256"
APPROVAL_FILE="$UPDATES_DIR/.sync-dry-run.approved"
EXCLUDE_FILE="$SCRIPT_DIR/.sync-update-ignore"
LOCK_FILE="$SCRIPT_DIR/.sync.lock"

PROJECTS=(apel e-bdr-uat e-mymohes-uat e-facility-uat e-hepa-uat e-madani-uat e-pms-uat e-pr-uat e-prestasi-uat e-spk-uat e-zakat-uat sap-uat survey-uat)

DRY_RUN=false
FORCE=false
BACKUP=false
FIX_PERM=false
ONLY=""
LOCK_HELD=false

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; CYAN='\033[0;36m'; NC='\033[0m'

declare -a SYNC_EXCLUDES=() ACTIONS=() PATHS=() BASE_HASHES=() UPDATE_HASHES=() ACTIVE_PROJECTS=()
declare -A CLASS=() P_UPDATED=() P_NEW=() P_DELETED=() P_UNCHANGED=() P_CONFLICT=() P_SKIPPED=() P_ERROR=()
UPDATED_COUNT=0; NEW_COUNT=0; DELETED_COUNT=0; UNCHANGED_COUNT=0; CONFLICT_COUNT=0; SKIPPED_COUNT=0; ERROR_COUNT=0

cleanup() {
    if [ "$LOCK_HELD" = true ]; then
        rm -f -- "$LOCK_FILE"
    fi
}
trap cleanup EXIT INT TERM

die() { echo -e "${RED}ERROR: $*${NC}" >&2; exit 1; }
sha256_file() { sha256sum -- "$1" | awk '{print $1}'; }

acquire_lock() {
    local lock_pid=''
    if [ -f "$LOCK_FILE" ]; then
        lock_pid="$(tr -dc '0-9' < "$LOCK_FILE")"
        if [ -n "$lock_pid" ] && kill -0 "$lock_pid" 2>/dev/null; then
            die "Another sync process is running with PID $lock_pid."
        fi
        rm -f -- "$LOCK_FILE"
    fi
    printf '%s\n' "$$" > "$LOCK_FILE"
    LOCK_HELD=true
}

safe_relative_path() {
    local path="$1"
    [ -n "$path" ] || return 1
    [[ "$path" != /* && "$path" != *$'\t'* && "$path" != *$'\n'* ]] || return 1
    case "/$path/" in */../*|*/./*) return 1 ;; esac
    return 0
}

path_within_project() {
    local root resolved
    root="$(realpath -m -- "$1")"
    resolved="$(realpath -m -- "$1/$2")"
    [[ "$resolved" == "$root/"* ]]
}

target_has_symlink_component() {
    local root="$1" rel="$2" current="$1" part
    IFS='/' read -r -a parts <<< "$rel"
    for part in "${parts[@]}"; do
        current="$current/$part"
        [ -L "$current" ] && return 0
    done
    return 1
}

load_sync_excludes() {
    local line
    SYNC_EXCLUDES=()
    [ -f "$EXCLUDE_FILE" ] || return 0
    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%%#*}"; line="${line%$'\r'}"
        line="${line#${line%%[![:space:]]*}}"; line="${line%${line##*[![:space:]]}}"
        [ -n "$line" ] && SYNC_EXCLUDES+=("$line")
    done < "$EXCLUDE_FILE"
}

should_skip_update_file() {
    local path="$1" pattern
    case "$path" in public/lang/custom/*|updates/public/lang/custom/*) return 0 ;; esac
    for pattern in "${SYNC_EXCLUDES[@]}"; do
        [[ "$path" == $pattern || "updates/$path" == $pattern ]] && return 0
    done
    return 1
}

is_collectable_update_file() {
    case "$1" in
        public/*|storage/manuals/.gitkeep|tools/language-split-tool.php|tools/core-file-protection-audit.php|docs/core-file-protection-*|docs/core-protected-files.md|docs/user-sso-metadata-migration-2026-09-03.sql|VERSION|README.md|CHANGELOG.md) return 0 ;;
    esac
    return 1
}

manifest_add() {
    local action="$1" path="$2" base_hash="$3" source update_hash='-'
    safe_relative_path "$path" || die "Unsupported update path: $path"
    is_collectable_update_file "$path" || return 0
    if should_skip_update_file "$path"; then
        echo -e "${YELLOW}SKIPPED  -> $path${NC}"
        return 0
    fi
    if [ "$action" != DELETE ]; then
        source="$SCRIPT_DIR/$path"
        [ -f "$source" ] || die "Source file missing while collecting: $path"
        mkdir -p -- "$UPDATES_DIR/$(dirname "$path")"
        cp -- "$source" "$UPDATES_DIR/$path"
        update_hash="$(sha256_file "$UPDATES_DIR/$path")"
    fi
    printf '%s\t%s\t%s\t%s\n' "$action" "$path" "$base_hash" "$update_hash" >> "$MANIFEST_FILE.records"
    echo -e "${CYAN}${action} -> $path${NC}"
}

git_base_hash() {
    git show "HEAD:$1" 2>/dev/null | sha256sum | awk '{print $1}'
}

collect_updates() {
    local status path old_path base_hash release created
    cd "$SCRIPT_DIR"
    [ "$LOCK_HELD" = true ] || acquire_lock
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "Master project is not a Git repository."
    mkdir -p -- "$UPDATES_DIR"
    rm -f -- "$APPROVAL_FILE"
    : > "$MANIFEST_FILE.records"
    echo "Detecting tracked changes..."

    while IFS= read -r -d '' status; do
        IFS= read -r -d '' path || die "Invalid Git status record."
        case "$status" in
            R*|C*)
                old_path="$path"
                IFS= read -r -d '' path || die "Invalid Git rename record."
                base_hash="$(git_base_hash "$old_path")"
                manifest_add DELETE "$old_path" "$base_hash"
                manifest_add CREATE "$path" '-'
                ;;
            D*) manifest_add DELETE "$path" "$(git_base_hash "$path")" ;;
            A*) manifest_add CREATE "$path" '-' ;;
            *)  manifest_add UPDATE "$path" "$(git_base_hash "$path")" ;;
        esac
    done < <(git diff --name-status -z HEAD --)

    while IFS= read -r -d '' path; do
        manifest_add CREATE "$path" '-'
    done < <(git ls-files --others --exclude-standard -z)

    release="$(tr -d '\r\n' < "$SCRIPT_DIR/VERSION" 2>/dev/null || echo unknown)"
    created="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    {
        printf '# sync-manifest-v1\n# release=%s\n# created_at=%s\n# action\tpath\tbase_sha256\tupdate_sha256\n' "$release" "$created"
        LC_ALL=C sort -t $'\t' -k2,2 "$MANIFEST_FILE.records"
    } > "$MANIFEST_FILE"
    rm -f -- "$MANIFEST_FILE.records"
    sha256_file "$MANIFEST_FILE" > "$MANIFEST_HASH_FILE"
    echo "Collect complete: $(grep -vc '^#' "$MANIFEST_FILE") manifest entries for release $release."
}

show_menu() {
    clear
    echo "========================================"
    echo "        SYNC ALL PROJECTS MENU"
    echo "========================================"
    echo "1. Sync All Projects (Normal)"
    echo "2. Dry Run (Preview sahaja)"
    echo "3. Force Sync (Overwrite conflicts)"
    echo "4. Backup + Sync"
    echo "5. Clear Updates Folder"
    echo "6. Fix Permission"
    echo "7. Sync Specific Project"
    echo "8. Exit"
    echo "9. Collect Updates + Build Hash Manifest"
    echo "========================================"
    read -r -p "Pilih option: " choice
    case "$choice" in
        1) ;;
        2) DRY_RUN=true ;;
        3) FORCE=true ;;
        4) BACKUP=true ;;
        5)
            read -r -p "Clear generated update package? (y/n): " confirm
            [ "$confirm" = y ] || exit 0
            find "$UPDATES_DIR" -mindepth 1 -type f -delete
            echo "Update package cleared."
            exit 0
            ;;
        6) FIX_PERM=true ;;
        7)
            printf '%s\n' "${PROJECTS[@]}"
            read -r -p "Masukkan nama project: " ONLY
            ;;
        8) exit 0 ;;
        9) collect_updates; exit 0 ;;
        *) die "Invalid menu option." ;;
    esac
}

load_manifest() {
    local expected actual action path base_hash update_hash extra source
    local -A seen_paths=()
    [ -f "$MANIFEST_FILE" ] || die "Manifest missing. Run option 9 to collect updates and build it."
    [ -f "$MANIFEST_HASH_FILE" ] || die "Manifest checksum missing. Run option 9 again."
    expected="$(awk 'NF {print $1; exit}' "$MANIFEST_HASH_FILE")"
    actual="$(sha256_file "$MANIFEST_FILE")"
    [ "$expected" = "$actual" ] || die "Manifest changed after collection. Run option 9 again."

    while IFS=$'\t' read -r action path base_hash update_hash extra; do
        [[ "$action" == \#* || -z "$action" ]] && continue
        [ -z "${extra:-}" ] || die "Invalid manifest row for $path."
        case "$action" in CREATE|UPDATE|DELETE) ;; *) die "Invalid manifest action: $action" ;; esac
        safe_relative_path "$path" || die "Unsafe manifest path: $path"
        [ -z "${seen_paths[$path]:-}" ] || die "Duplicate manifest path: $path"
        seen_paths[$path]=1
        if [ "$action" != DELETE ]; then
            source="$UPDATES_DIR/$path"
            [ -f "$source" ] || die "Manifest source missing: $path"
            [ "$(sha256_file "$source")" = "$update_hash" ] || die "Update package hash mismatch: $path"
        fi
        ACTIONS+=("$action"); PATHS+=("$path"); BASE_HASHES+=("$base_hash"); UPDATE_HASHES+=("$update_hash")
    done < "$MANIFEST_FILE"
    [ "${#PATHS[@]}" -gt 0 ] || die "Manifest contains no update records."
}

counter_add() {
    local project="$1" class="$2"
    case "$class" in
        UPDATE) ((UPDATED_COUNT+=1)); ((P_UPDATED[$project]+=1)) ;;
        NEW) ((NEW_COUNT+=1)); ((P_NEW[$project]+=1)) ;;
        DELETE) ((DELETED_COUNT+=1)); ((P_DELETED[$project]+=1)) ;;
        UNCHANGED) ((UNCHANGED_COUNT+=1)); ((P_UNCHANGED[$project]+=1)) ;;
        CONFLICT) ((CONFLICT_COUNT+=1)); ((P_CONFLICT[$project]+=1)) ;;
        SKIPPED) ((SKIPPED_COUNT+=1)); ((P_SKIPPED[$project]+=1)) ;;
        ERROR) ((ERROR_COUNT+=1)); ((P_ERROR[$project]+=1)) ;;
    esac
}

classify_record() {
    local project="$1" index="$2" target="$BASE_PATH/$project" path="${PATHS[$index]}"
    local action="${ACTIONS[$index]}" base_hash="${BASE_HASHES[$index]}" update_hash="${UPDATE_HASHES[$index]}"
    local target_file="$target/$path" target_hash
    if ! path_within_project "$target" "$path" || target_has_symlink_component "$target" "$path"; then echo ERROR; return; fi
    [ -w "$target" ] || { echo ERROR; return; }
    if [ ! -e "$target_file" ]; then
        [ "$action" = DELETE ] && echo UNCHANGED || echo NEW
        return
    fi
    [ -f "$target_file" ] || { echo ERROR; return; }
    target_hash="$(sha256_file "$target_file")"
    [ "$action" != DELETE ] && [ "$target_hash" = "$update_hash" ] && { echo UNCHANGED; return; }
    if [ "$base_hash" != '-' ] && [ "$target_hash" = "$base_hash" ]; then
        [ "$action" = DELETE ] && echo DELETE || echo UPDATE
        return
    fi
    if [ "$FORCE" = true ]; then
        [ "$action" = DELETE ] && echo DELETE || echo UPDATE
        return
    fi
    echo CONFLICT
}

print_row() { printf "${4}│ %-16s │ %-10s │ %-44s │${NC}\n" "$1" "$2" "$3"; }

preflight() {
    local project index class target available_bytes required_bytes
    echo "Release: $(sed -n 's/^# release=//p' "$MANIFEST_FILE")"
    echo "Manifest: $(cat "$MANIFEST_HASH_FILE")"
    echo "Files: ${#PATHS[@]}"
    echo "┌──────────────────┬────────────┬──────────────────────────────────────────────┐"
    printf "│ %-16s │ %-10s │ %-44s │\n" PROJECT ACTION FILE
    echo "├──────────────────┼────────────┼──────────────────────────────────────────────┤"
    for project in "${PROJECTS[@]}"; do
        [ -n "$ONLY" ] && [ "$project" != "$ONLY" ] && continue
        target="$BASE_PATH/$project"
        if [ ! -d "$target" ]; then
            print_row "$project" ERROR "Project directory not found" "$RED"
            counter_add "$project" ERROR
            continue
        fi
        available_bytes="$(df -Pk -- "$target" | awk 'NR==2 {print $4 * 1024}')"
        required_bytes="$(du -sb -- "$UPDATES_DIR" | awk '{print $1}')"
        if [ "${available_bytes%.*}" -lt "$required_bytes" ]; then
            print_row "$project" ERROR "Insufficient disk space" "$RED"
            counter_add "$project" ERROR
            continue
        fi
        ACTIVE_PROJECTS+=("$project")
        for index in "${!PATHS[@]}"; do
            class="$(classify_record "$project" "$index")"
            CLASS["$project|$index"]="$class"
            counter_add "$project" "$class"
            case "$class" in
                UPDATE) print_row "$project" "$([ "$DRY_RUN" = true ] && echo DRY-UPD || echo UPDATE)" "${PATHS[$index]}" "$CYAN" ;;
                NEW) print_row "$project" "$([ "$DRY_RUN" = true ] && echo DRY-NEW || echo NEW)" "${PATHS[$index]}" "$GREEN" ;;
                DELETE) print_row "$project" "$([ "$DRY_RUN" = true ] && echo DRY-DEL || echo DELETE)" "${PATHS[$index]}" "$RED" ;;
                CONFLICT) print_row "$project" CONFLICT "${PATHS[$index]}" "$YELLOW" ;;
                ERROR) print_row "$project" ERROR "${PATHS[$index]}" "$RED" ;;
            esac
        done
    done
    echo "└──────────────────┴────────────┴──────────────────────────────────────────────┘"
}

rollback_project() {
    local target="$1" tx="$2" path
    while IFS= read -r path; do rm -f -- "$target/$path"; done < "$tx/new-files" 2>/dev/null || true
    if [ -d "$tx/original" ]; then
        while IFS= read -r -d '' path; do
            rel="${path#$tx/original/}"; mkdir -p -- "$target/$(dirname "$rel")"; cp -p -- "$path" "$target/$rel"
        done < <(find "$tx/original" -type f -print0)
    fi
}

apply_project() {
    local project="$1" target="$BASE_PATH/$project" tx backup_dir index class path source expected actual
    tx="$(mktemp -d "$target/.sync-transaction.XXXXXX")"
    mkdir -p "$tx/original"; : > "$tx/new-files"
    if [ "$BACKUP" = true ]; then
        backup_dir="$BASE_PATH/_sync_backups/$(date +%Y%m%d_%H%M%S)/$project"
        mkdir -p "$backup_dir"
    fi
    for index in "${!PATHS[@]}"; do
        class="${CLASS[$project|$index]}"; path="${PATHS[$index]}"
        case "$class" in UPDATE|NEW|DELETE) ;; *) continue ;; esac
        if [ -f "$target/$path" ]; then
            mkdir -p "$tx/original/$(dirname "$path")"
            cp -p -- "$target/$path" "$tx/original/$path"
            if [ "$BACKUP" = true ]; then mkdir -p "$backup_dir/$(dirname "$path")"; cp -p -- "$target/$path" "$backup_dir/$path"; fi
        else
            printf '%s\n' "$path" >> "$tx/new-files"
        fi
        if [ "${ACTIONS[$index]}" = DELETE ]; then
            rm -f -- "$target/$path" || { rollback_project "$target" "$tx"; die "Delete failed; project rolled back: $project/$path"; }
            continue
        fi
        source="$UPDATES_DIR/$path"; expected="${UPDATE_HASHES[$index]}"
        mkdir -p -- "$target/$(dirname "$path")"
        cp -- "$source" "$target/$path.tmp" || { rollback_project "$target" "$tx"; die "Copy failed; project rolled back: $project/$path"; }
        actual="$(sha256_file "$target/$path.tmp")"
        [ "$actual" = "$expected" ] || { rm -f "$target/$path.tmp"; rollback_project "$target" "$tx"; die "Staged hash mismatch; project rolled back: $project/$path"; }
        mv -- "$target/$path.tmp" "$target/$path" || { rm -f "$target/$path.tmp"; rollback_project "$target" "$tx"; die "Atomic replace failed; project rolled back: $project/$path"; }
        [ "$(sha256_file "$target/$path")" = "$expected" ] || { rollback_project "$target" "$tx"; die "Final hash mismatch; project rolled back: $project/$path"; }
    done
    if [ "$FIX_PERM" = true ]; then
        for index in "${!PATHS[@]}"; do [ -e "$target/${PATHS[$index]}" ] && chown www-data:www-data "$target/${PATHS[$index]}"; done
    fi
    rm -rf -- "$tx"
}

print_summary() {
    local project
    echo ""
    [ "$DRY_RUN" = true ] && echo "====== PER PROJECT SUMMARY (DRY RUN) ======" || echo "====== PER PROJECT SUMMARY ======"
    for project in "${ACTIVE_PROJECTS[@]}"; do
        printf "%-16s -> Updated: %-3s | New: %-3s | Deleted: %-3s | Unchanged: %-3s | Conflict: %-3s | Error: %-3s\n" "$project" "${P_UPDATED[$project]:-0}" "${P_NEW[$project]:-0}" "${P_DELETED[$project]:-0}" "${P_UNCHANGED[$project]:-0}" "${P_CONFLICT[$project]:-0}" "${P_ERROR[$project]:-0}"
    done
    echo ""
    [ "$DRY_RUN" = true ] && echo "====== SUMMARY (SIMULATION ONLY) ======" || echo "====== SUMMARY ======"
    echo "Updated: $UPDATED_COUNT"; echo "New: $NEW_COUNT"; echo "Deleted: $DELETED_COUNT"; echo "Unchanged: $UNCHANGED_COUNT"; echo "Conflict: $CONFLICT_COUNT"; echo "Error: $ERROR_COUNT"
}

for required_command in git sha256sum realpath awk sort find df du mktemp cmp cp mv; do
    command -v "$required_command" >/dev/null 2>&1 || die "Required command is unavailable: $required_command"
done

load_sync_excludes
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=true ;; --force) FORCE=true ;; --backup) BACKUP=true ;; --fix-permission) FIX_PERM=true ;;
        --only=*) ONLY="${arg#*=}" ;; --collect) collect_updates; exit 0 ;; *) die "Unknown option: $arg" ;;
    esac
done
[ "$#" -gt 0 ] || show_menu

if [ -n "$ONLY" ]; then
    valid=false; for project in "${PROJECTS[@]}"; do [ "$project" = "$ONLY" ] && valid=true; done
    [ "$valid" = true ] || die "Unknown project: $ONLY"
fi
acquire_lock

load_manifest
preflight
print_summary

if [ "$DRY_RUN" = true ]; then
    if [ "$ERROR_COUNT" -eq 0 ] && { [ "$CONFLICT_COUNT" -eq 0 ] || [ "$FORCE" = true ]; }; then
        printf '%s|scope=%s|force=%s\n' "$(cat "$MANIFEST_HASH_FILE")" "${ONLY:-ALL}" "$FORCE" > "$APPROVAL_FILE"
        echo "Dry-run approval recorded for this exact manifest and scope."
    else
        rm -f -- "$APPROVAL_FILE"
        echo "Dry-run approval was not recorded because conflicts or errors remain."
    fi
    echo "Dry run complete - no target files, directories, backups, or permissions were changed."
    exit 0
fi
[ "$ERROR_COUNT" -eq 0 ] || die "Preflight contains errors. Nothing was changed."
if [ "$CONFLICT_COUNT" -gt 0 ] && [ "$FORCE" = false ]; then die "Preflight contains conflicts. Nothing was changed. Review dry-run or use explicit force mode."; fi
expected_approval="$(cat "$MANIFEST_HASH_FILE")|scope=${ONLY:-ALL}|force=$FORCE"
[ -f "$APPROVAL_FILE" ] && [ "$(cat "$APPROVAL_FILE")" = "$expected_approval" ] || die "This exact manifest, scope, and force mode has not passed dry-run. Run the matching dry-run first."
read -r -p "Apply this exact manifest to ${#ACTIVE_PROJECTS[@]} project(s)? Type APPLY: " confirm
[ "$confirm" = APPLY ] || { echo "Cancelled. Nothing was changed."; exit 0; }
for project in "${ACTIVE_PROJECTS[@]}"; do apply_project "$project"; done
echo "Sync complete and hashes verified."
