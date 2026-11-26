#!/usr/bin/env bash
# build-base-box.sh - Build an Ubuntu 22.04 VirtualBox base box for Vagrant from the live server ISO.
#
# Requires: packer, VirtualBox. Publishing (-p) also needs the R2_* variables and tools listed in push-to-r2.sh.
set -euo pipefail

usage() {
  cat >&2 <<EOF
Usage: $0 [-A ARCH] [-i ISO] [-c CHECKSUM] [-o BOX_FILE] [-d DISK_MB] [-m MEMORY_MB] [-n CPUS] [-w BOOT_WAIT] [-t TIMEOUT] [-g] [-k] [-a] [-p VERSION [-N BOX_NAME]]
  -A  amd64 or arm64 (default: this machine's architecture; VirtualBox can't build for another)
  -i  Path or URL of an Ubuntu 22.04 live server ISO for ARCH
      (default: download ubuntu-22.04.5-live-server-ARCH.iso from Ubuntu)
  -c  ISO checksum, e.g. sha256:abc... (default: verified against Ubuntu's SHA256SUMS
      for the download, skipped for a local ISO)
  -o  Output .box file (default: output/ubuntu-22.04-ARCH-virtualbox.box next to this script)
  -d  Max disk size in MB (default: 65536)
  -m  Memory in MB used during the build (default: 4096)
  -n  CPUs used during the build (default: 2)
  -w  Wait after power-on before typing the boot command (default: 15s; raise on slow hosts, max ~25s)
  -t  How long the OS install may take before giving up (default: 60m; hosts without hardware
      virtualization need several hours)
  -g  Show the VirtualBox window instead of building headless
  -k  On failure, leave the VM running for debugging instead of deleting it
  -a  Add the finished box to Vagrant as ubuntu-22.04-local
  -p  Publish the box to Cloudflare R2 with push-to-r2.sh under this version, e.g. 2026.10.061530
  -N  Box name to publish under (default: SubmittyBot/ubuntu22-base)
EOF
  exit 1
}

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
case $(uname -m) in
  arm64|aarch64) HOST_ARCH=arm64 ;;
  *) HOST_ARCH=amd64 ;;
esac
REPO_ROOT=$(cd "$SCRIPT_DIR/../../.." && pwd)
ARCH=$HOST_ARCH; ISO=""; CHECKSUM=""; BOX_FILE=""
DISK=65536; MEMORY=4096; CPUS=2; HEADLESS=true; ADD_BOX=false
PUBLISH_VERSION=""; BOX_NAME="SubmittyBot/ubuntu22-base"; BOOT_WAIT=15s; SSH_TIMEOUT=60m; ON_ERROR=cleanup
while getopts "A:i:c:o:d:m:n:w:t:gkap:N:h" opt; do
  case $opt in
    A) ARCH=$OPTARG ;; i) ISO=$OPTARG ;; c) CHECKSUM=$OPTARG ;; o) BOX_FILE=$OPTARG ;;
    d) DISK=$OPTARG ;; m) MEMORY=$OPTARG ;; n) CPUS=$OPTARG ;; w) BOOT_WAIT=$OPTARG ;; t) SSH_TIMEOUT=$OPTARG ;;
    g) HEADLESS=false ;; k) ON_ERROR=abort ;; a) ADD_BOX=true ;; p) PUBLISH_VERSION=$OPTARG ;; N) BOX_NAME=$OPTARG ;;
    *) usage ;;
  esac
done

[[ $ARCH == amd64 || $ARCH == arm64 ]] || { echo "Error: -A must be amd64 or arm64" >&2; exit 1; }
if [[ $ARCH != "$HOST_ARCH" ]]; then
  echo "Error: can't build an $ARCH box on an $HOST_ARCH host; VirtualBox only runs guests of the host's architecture" >&2
  exit 1
fi
BOX_FILE=${BOX_FILE:-$SCRIPT_DIR/output/ubuntu-22.04-$ARCH-virtualbox.box}

command -v packer >/dev/null || {
  echo "Error: packer is not installed. See https://developer.hashicorp.com/packer/install" >&2
  echo "       (Windows: choco install packer / winget install Hashicorp.Packer)" >&2
  exit 1
}
# Check publishing prerequisites now rather than after a 30+ minute build.
if [[ -n $PUBLISH_VERSION ]]; then
  : "${R2_ACCOUNT_ID:?not set}" "${R2_ACCESS_KEY_ID:?not set}" "${R2_SECRET_ACCESS_KEY:?not set}"
  : "${R2_BUCKET:?not set}" "${R2_PUBLIC_BASE_URL:?not set}"
  for cmd in aws jq; do
    command -v "$cmd" >/dev/null || { echo "Error: $cmd is not installed (needed to publish)" >&2; exit 1; }
  done
fi
if ! command -v VBoxManage >/dev/null && [[ ! -x "${VBOX_INSTALL_PATH:-/c/Program Files/Oracle/VirtualBox}/VBoxManage.exe" ]]; then
  echo "Error: VirtualBox is not installed" >&2
  exit 1
fi

# Packer is a native binary, so on Git Bash it needs Windows-style paths.
native_path() {
  if command -v cygpath >/dev/null; then cygpath -m "$1"; else echo "$1"; fi
}

VARS=(-var "arch=$ARCH" -var "disk_size=$DISK" -var "memory=$MEMORY" -var "cpus=$CPUS" -var "headless=$HEADLESS" -var "boot_wait=$BOOT_WAIT" -var "ssh_timeout=$SSH_TIMEOUT")

if [[ -n $ISO ]]; then
  # The 22.04 desktop ISO ignores autoinstall and just boots to a live desktop.
  if [[ $(basename "$ISO") == *desktop* ]]; then
    echo "Error: $ISO looks like a desktop ISO; use the live server ISO (ubuntu-22.04.5-live-server-$ARCH.iso)" >&2
    exit 1
  fi
  OTHER_ARCH=$([[ $ARCH == amd64 ]] && echo arm64 || echo amd64)
  if [[ $(basename "$ISO") == *"$OTHER_ARCH"* ]]; then
    echo "Error: $ISO looks like an $OTHER_ARCH ISO, but this is an $ARCH build" >&2
    exit 1
  fi
  if [[ $ISO =~ ^[a-z]+:// ]]; then
    VARS+=(-var "iso_url=$ISO")
  else
    [[ -f $ISO ]] || { echo "Error: no such file: $ISO" >&2; exit 1; }
    ISO_ABS="$(cd "$(dirname "$ISO")" && pwd)/$(basename "$ISO")"
    VARS+=(-var "iso_url=$(native_path "$ISO_ABS")")
    CHECKSUM=${CHECKSUM:-none}
  fi
fi
[[ -n $CHECKSUM ]] && VARS+=(-var "iso_checksum=$CHECKSUM")

mkdir -p "$(dirname "$BOX_FILE")"
BOX_ABS="$(cd "$(dirname "$BOX_FILE")" && pwd)/$(basename "$BOX_FILE")"
rm -f "$BOX_ABS"
VARS+=(-var "output_box=$(native_path "$BOX_ABS")")

TEMPLATE=$(native_path "$SCRIPT_DIR/ubuntu-22.04.pkr.hcl")

echo "==> Installing Packer plugins..."
packer init "$TEMPLATE"

echo "==> Building $ARCH box (the unattended install takes 15-30 minutes)..."
# Packer's working directory holds the VM and export; keep it next to the template.
cd "$SCRIPT_DIR"
packer build -force -on-error="$ON_ERROR" "${VARS[@]}" "$TEMPLATE"

echo
echo "Done: $BOX_ABS"

if $ADD_BOX; then
  vagrant box add --force --name ubuntu-22.04-local "$(native_path "$BOX_ABS")"
  echo "Use it with: VAGRANT_BOX=ubuntu-22.04-local vagrant up"
fi

if [[ -n $PUBLISH_VERSION ]]; then
  echo "==> Publishing to R2 as $BOX_NAME $PUBLISH_VERSION ($ARCH)..."
  bash "./push-to-r2.sh" -f "$BOX_ABS" -n "$BOX_NAME" -p virtualbox -a "$ARCH" -v "$PUBLISH_VERSION"
elif ! $ADD_BOX; then
  cat <<EOF

Try it locally:
  vagrant box add --name ubuntu-22.04-local "$BOX_ABS"
  VAGRANT_BOX=ubuntu-22.04-local vagrant up

Publish it by re-running with -p <version>, or:
  ./push-to-r2.sh -f "$BOX_ABS" -n $BOX_NAME -a $ARCH -v <version>
EOF
fi
