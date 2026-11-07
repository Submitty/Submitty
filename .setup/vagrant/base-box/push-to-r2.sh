#!/usr/bin/env bash
# upload-box-to-r2.sh - Publish a Vagrant box to Cloudflare R2 and update its metadata.json catalog.
#
# Required environment variables:
#   R2_ACCOUNT_ID         Cloudflare account ID
#   R2_ACCESS_KEY_ID      R2 API token access key (Object Read & Write on the bucket)
#   R2_SECRET_ACCESS_KEY  R2 API token secret
#   R2_BUCKET             Bucket name
#   R2_PUBLIC_BASE_URL    Public URL of the bucket, e.g. https://boxes.submitty.org
#
# Requires: aws (CLI v2), jq
usage() {
  cat >&2 <<EOF
Usage: $0 -f BOX_FILE -v VERSION [-n BOX_NAME] [-p PROVIDER] [-a ARCH] [-k KEEP]
  -f  Path to the .box file
  -v  Box version, e.g. 24.05.00.2405260215
  -n  Box name (default: SubmittyBot/ubuntu22-dev)
  -p  Provider (default: virtualbox)
  -a  Architecture (default: amd64)
  -k  Number of versions to keep; older ones are deleted (default: 2, 0 = keep all)
EOF
}

BOX_FILE=""; VERSION=""; BOX_NAME="SubmittyBot/ubuntu22-dev"
PROVIDER="virtualbox"; ARCH="amd64"; KEEP="${KEEP_VERSIONS:-2}"
while getopts "f:v:n:p:a:k:h" opt; do
  case $opt in
    f) BOX_FILE=$OPTARG ;; v) VERSION=$OPTARG ;; n) BOX_NAME=$OPTARG ;;
    p) PROVIDER=$OPTARG ;; a) ARCH=$OPTARG ;; k) KEEP=$OPTARG ;; *) usage ;;
  esac
done
[[ -n $BOX_FILE && -n $VERSION ]] || usage
[[ -f $BOX_FILE ]] || { echo "Error: no such file: $BOX_FILE" >&2; exit 1; }
: "${R2_ACCOUNT_ID:?not set}" "${R2_ACCESS_KEY_ID:?not set}" "${R2_SECRET_ACCESS_KEY:?not set}"
: "${R2_BUCKET:?not set}" "${R2_PUBLIC_BASE_URL:?not set}"
for cmd in aws jq; do
  command -v "$cmd" >/dev/null || { echo "Error: $cmd is not installed" >&2; exit 1; }
done

TMP=$(mktemp -d)
cleanup() {
  local code=$?
  rm -rf "$TMP"
  if [[ $code -ne 0 ]]; then
    echo >&2
    echo "Script exited with an error (code $code)." >&2
    read -rp "Press Enter to close this window..." _
  fi
}
trap cleanup EXIT

# Isolated AWS config so we don't touch ~/.aws. 64 MB parts keep a 16 GB upload at ~256 parts.
cat > "$TMP/config" <<EOF
[default]
region = auto
s3 =
  multipart_threshold = 64MB
  multipart_chunksize = 64MB
  max_concurrent_requests = 8
EOF
export AWS_CONFIG_FILE="$TMP/config" AWS_SHARED_CREDENTIALS_FILE=/dev/null
export AWS_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID" AWS_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"
# Recent AWS CLI versions send extra checksums by default; limit them to when required for R2 compatibility.
export AWS_REQUEST_CHECKSUM_CALCULATION=when_required AWS_RESPONSE_CHECKSUM_VALIDATION=when_required

ENDPOINT="https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com"
s3() { aws --endpoint-url "$ENDPOINT" "$@"; }

BASE_URL="${R2_PUBLIC_BASE_URL%/}"
BOX_KEY="${BOX_NAME}/${VERSION}/${PROVIDER}-${ARCH}.box"
META_KEY="${BOX_NAME}/metadata.json"
BOX_URL="${BASE_URL}/${BOX_KEY}"

echo "==> Computing SHA-256 of $BOX_FILE (this takes a while for large boxes)..."
if [[ "$(uname -s)" == MINGW* || "$(uname -s)" == MSYS* ]] && command -v certutil >/dev/null; then
  # Git Bash's sha256sum goes through MSYS's POSIX I/O emulation, which is
  # drastically slower than a native read on large files. certutil calls
  # straight into Windows and is over an order of magnitude faster here.
  CHECKSUM=$(certutil -hashfile "$BOX_FILE" SHA256 | sed -n '2p' | tr -d ' \r')
elif command -v sha256sum >/dev/null; then
  CHECKSUM=$(sha256sum "$BOX_FILE" | awk '{print $1}')
else
  CHECKSUM=$(shasum -a 256 "$BOX_FILE" | awk '{print $1}')
fi
LOCAL_SIZE=$(wc -c < "$BOX_FILE" | tr -d ' ')
echo "    sha256: $CHECKSUM"
echo "    size:   $LOCAL_SIZE bytes"

echo "==> Uploading box to s3://$R2_BUCKET/$BOX_KEY ..."
s3 s3 cp "$BOX_FILE" "s3://$R2_BUCKET/$BOX_KEY" \
  --content-type application/octet-stream --metadata "sha256=$CHECKSUM"

REMOTE_SIZE=$(s3 s3api head-object --bucket "$R2_BUCKET" --key "$BOX_KEY" --query ContentLength --output text)
[[ $REMOTE_SIZE == "$LOCAL_SIZE" ]] || { echo "Error: size mismatch ($REMOTE_SIZE != $LOCAL_SIZE)" >&2; exit 1; }
echo "    verified size on R2"

echo "==> Updating $META_KEY ..."
if ! s3 s3 cp "s3://$R2_BUCKET/$META_KEY" "$TMP/metadata.json" >/dev/null 2>&1; then
  echo "    no existing catalog, creating a new one"
  jq -n --arg name "$BOX_NAME" '{name: $name, versions: []}' > "$TMP/metadata.json"
fi

jq --arg name "$BOX_NAME" --arg v "$VERSION" --arg p "$PROVIDER" --arg a "$ARCH" \
   --arg url "$BOX_URL" --arg sum "$CHECKSUM" '
  .name = $name
  | .versions = (.versions // [])
  | if any(.versions[]; .version == $v) then . else .versions += [{version: $v, providers: []}] end
  | .versions |= map(
      if .version == $v then
        .providers = (((.providers // []) | map(select(.name != $p or (.architecture // "amd64") != $a)))
          + [{name: $p, architecture: $a, default_architecture: ($a == "amd64"),
              url: $url, checksum_type: "sha256", checksum: $sum}])
      else . end)
  | .versions |= sort_by(.version | split(".") | map(tonumber? // 0))
' "$TMP/metadata.json" > "$TMP/updated.json"

PRUNED=()
if (( KEEP > 0 )); then
  # while-read instead of mapfile: macOS ships bash 3.2, which has no mapfile.
  while IFS= read -r v; do
    PRUNED+=("$v")
  done < <(jq -r --argjson k "$KEEP" \
    '.versions | if length > $k then .[0:(length - $k)][].version else empty end' "$TMP/updated.json")
  jq --argjson k "$KEEP" '.versions |= .[-$k:]' "$TMP/updated.json" > "$TMP/final.json"
else
  cp "$TMP/updated.json" "$TMP/final.json"
fi

# Upload the catalog only after the box is verified, so it never points at a missing file.
s3 s3 cp "$TMP/final.json" "s3://$R2_BUCKET/$META_KEY" \
  --content-type application/json --cache-control "public, max-age=300"

for old in "${PRUNED[@]}"; do
  [[ -n $old ]] || continue
  echo "==> Pruning old version $old ..."
  s3 s3 rm "s3://$R2_BUCKET/${BOX_NAME}/${old}/" --recursive
done

echo
echo "Done. Versions now in catalog:"
jq -r '.versions[].version' "$TMP/final.json" | sed 's/^/  /'
cat <<EOF

Vagrantfile settings:
  config.vm.box     = "$BOX_NAME"
  config.vm.box_url = "$BASE_URL/$META_KEY"
EOF