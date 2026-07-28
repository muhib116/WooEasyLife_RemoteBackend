#!/usr/bin/env bash
# Merge the currently live Vite fingerprinted assets into the local build folder
# before FTP sync. SamKirkland/FTP-Deploy-Action deletes remote files that are
# missing locally; without this merge, every deploy 404s the previous hashes and
# Semrush flags "Broken internal JavaScript and CSS files" on cached/crawled HTML.
set -euo pipefail

BASE_URL="${VITE_ASSET_PRESERVE_URL:-https://app.wpsalehub.com}"
BUILD_DIR="${1:-public/build}"
ASSETS_DIR="${BUILD_DIR}/assets"

mkdir -p "${ASSETS_DIR}"

TMP="$(mktemp)"
trap 'rm -f "${TMP}"' EXIT

if ! curl -fsSL --max-time 90 -A 'WooEasyLife-DeployPreserve/1.0' \
  "${BASE_URL}/build/manifest.json" -o "${TMP}"; then
  if [[ "${ALLOW_PRESERVE_SKIP:-}" == "1" ]]; then
    echo "preserve-vite: WARN no remote manifest — ALLOW_PRESERVE_SKIP=1, continuing"
    exit 0
  fi
  echo "preserve-vite: ERROR could not fetch ${BASE_URL}/build/manifest.json"
  echo "preserve-vite: refusing FTP sync that would delete prior hashed assets"
  echo "preserve-vite: set ALLOW_PRESERVE_SKIP=1 only for first-time empty deploys"
  exit 1
fi

python3 - "${TMP}" "${ASSETS_DIR}" "${BASE_URL}" <<'PY'
import json
import os
import sys
import urllib.error
import urllib.request

manifest_path, assets_dir, base = sys.argv[1:4]
base = base.rstrip("/")
ua = "WooEasyLife-DeployPreserve/1.0"
allow_skip = os.environ.get("ALLOW_PRESERVE_SKIP") == "1"

with open(manifest_path, encoding="utf-8") as handle:
    data = json.load(handle)

files: set[str] = set()
for entry in data.values():
    if not isinstance(entry, dict):
        continue
    file_name = entry.get("file")
    if isinstance(file_name, str) and file_name:
        files.add(file_name)
    for css in entry.get("css") or []:
        if isinstance(css, str) and css:
            files.add(css)
    for asset in entry.get("assets") or []:
        if isinstance(asset, str) and asset:
            files.add(asset)

for entry in data.values():
    if not isinstance(entry, dict):
        continue
    for key in ("imports", "dynamicImports"):
        for import_key in entry.get(key) or []:
            imported = data.get(import_key)
            if isinstance(imported, dict):
                file_name = imported.get("file")
                if isinstance(file_name, str) and file_name:
                    files.add(file_name)
                for css in imported.get("css") or []:
                    if isinstance(css, str) and css:
                        files.add(css)

downloaded = skipped = failed = 0
for rel in sorted(files):
    name = os.path.basename(rel)
    dest = os.path.join(assets_dir, name)
    if os.path.isfile(dest):
        skipped += 1
        continue
    url = f"{base}/build/{rel}" if rel.startswith("assets/") else f"{base}/build/assets/{name}"
    try:
        request = urllib.request.Request(url, headers={"User-Agent": ua})
        with urllib.request.urlopen(request, timeout=60) as response:
            with open(dest, "wb") as out:
                out.write(response.read())
        downloaded += 1
    except (urllib.error.URLError, OSError) as exc:
        failed += 1
        print(f"preserve-vite: warn {url}: {exc}")

print(
    f"preserve-vite: downloaded={downloaded} skipped_existing={skipped} "
    f"failed={failed} remote_files={len(files)}"
)

if failed > 0 and not allow_skip:
    print(
        "preserve-vite: ERROR partial download — refusing FTP sync that would "
        "delete still-live hashes. Set ALLOW_PRESERVE_SKIP=1 to override."
    )
    sys.exit(1)
PY
