#!/usr/bin/env bash
#
# Proof-of-concept: bring up a Submitty dev VM with Multipass instead of Vagrant.
#
# What this does:
#   1. Launches an Ubuntu 22.04 instance via Multipass (image comes straight from
#      Canonical's own Multipass catalog -- not Vagrant Cloud, not a third-party box).
#   2. Mounts this repo checkout into the instance NATIVELY at the same path the
#      existing install scripts already expect (GIT_PATH below). Native, not the
#      default sshfs "classic" mount: sshfs mounts here only granted access to the
#      user that created the mount, so `sudo` (root) inside the guest got
#      "Operation not permitted" trying to read anything under it -- and the whole
#      install needs to run as root. Native mounts (virtiofs/9p/SMB depending on
#      platform) don't have that restriction.
#   3. Runs the existing .setup/vagrant/setup_vagrant.sh install script, with --utm
#      (see the comment further down for why).
#
# PREREQUISITE ON macOS: if your repo checkout lives under a TCC-protected folder
# (Desktop, Documents, Downloads, iCloud Drive), macOS will block Multipass's VM
# process from reading it at all, independent of anything above. If `up.sh` fails
# at the mount/launch step with a QEMU error like:
#   failed to open '<path>': Operation not permitted
# go to System Settings -> Privacy & Security -> Full Disk Access and add the
# binary named in the error (find it with `which qemu-system-aarch64` or
# `qemu-system-x86_64`), plus `multipassd`. Then retry.
#
# What this deliberately does NOT do yet (out of scope for a first try):
#   - Any port forwarding. The instance is reachable at its own IP from this host
#     only -- see the printed URL at the end. It is NOT reachable from your LAN.
#   - Worker VMs, EXTRA packages -- single dev VM only.
#
# Usage:
#   ./multipass/up.sh
#
# Env vars (all optional):
#   SUBMITTY_VM_NAME  instance name              (default: submitty-dev)
#   VM_CPUS           cpu count                  (default: 4)
#   VM_MEMORY         memory, multipass format    (default: 4G)
#   VM_DISK           disk size, multipass format (default: 40G)

set -euo pipefail

INSTANCE_NAME="${SUBMITTY_VM_NAME:-submitty-dev}"
CPUS="${VM_CPUS:-4}"
MEMORY="${VM_MEMORY:-4G}"
DISK="${VM_DISK:-40G}"
GIT_PATH="/usr/local/submitty/GIT_CHECKOUT/Submitty"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if ! command -v multipass >/dev/null 2>&1; then
    echo "ERROR: multipass is not installed." >&2
    echo "  macOS:   brew install --cask multipass" >&2
    echo "  Windows: winget install Canonical.Multipass" >&2
    echo "  Linux:   sudo snap install multipass && sudo snap install multipass-sshfs" >&2
    exit 1
fi

if multipass info "${INSTANCE_NAME}" >/dev/null 2>&1; then
    echo "ERROR: an instance named '${INSTANCE_NAME}' already exists." >&2
    echo "  Remove it first: multipass delete --purge ${INSTANCE_NAME}" >&2
    echo "  Or pick a different name: SUBMITTY_VM_NAME=foo ./multipass/up.sh" >&2
    exit 1
fi

echo "==> Launching ${INSTANCE_NAME} (Ubuntu 22.04, ${CPUS} CPUs, ${MEMORY} RAM, ${DISK} disk)"
multipass launch 22.04 \
    --name "${INSTANCE_NAME}" \
    --cpus "${CPUS}" \
    --memory "${MEMORY}" \
    --disk "${DISK}"

echo "==> Mounting ${REPO_DIR} -> ${INSTANCE_NAME}:${GIT_PATH} (native mount type --"
echo "    see the header comment for why this isn't the default sshfs mount)"
# The mount point's parent has to exist before the mount is attached, and
# native mounts are configured at VM boot rather than hot-attached, so:
# create the directory while the instance is up, then stop/mount/start.
multipass exec "${INSTANCE_NAME}" -- sudo mkdir -p "$(dirname "${GIT_PATH}")"
multipass stop "${INSTANCE_NAME}"
multipass mount --type native "${REPO_DIR}" "${INSTANCE_NAME}:${GIT_PATH}"
multipass start "${INSTANCE_NAME}"

echo "==> Provisioning via .setup/vagrant/setup_vagrant.sh --utm (this reuses the existing"
echo "    install path largely unchanged, so expect it to take as long as it does today)"
#
# --utm is passed (setup_vagrant.sh forwards extra args to install_system.sh)
# because this is a real bare Ubuntu VM, not a pre-baked Vagrant box: there is
# no 'vagrant' user with pre-populated SSH keys the way a Vagrant box provides
# one. --utm already exists in install_system.sh for exactly this situation
# (it was added for manual UTM installs on Apple Silicon, see GitHub issue
# #7885) -- it creates a fresh vagrant user instead of assuming one exists,
# and skips copying /home/vagrant/.ssh/authorized_keys. Without it, install
# fails early with "cp: cannot stat '/home/vagrant/.ssh/authorized_keys'".
#
# Note: --utm creates a GIT_PATH/.utm directory inside this repo checkout
# (already added to .gitignore).
#
# Known rough edges carried over from issue #7885 for UTM-style installs on
# Apple Silicon specifically (not caused by Multipass, pre-existing): NTP
# clock skew after resume, SubmittyAnalysisTools Haskell binary downloads
# sometimes unavailable, autograding docker image pulls timing out, and some
# pinned pip packages lacking arm64 wheels. If provisioning fails partway
# through, check whether one of these is the actual cause before assuming
# something Multipass-specific broke.
multipass exec "${INSTANCE_NAME}" -- sudo mkdir -p "${GIT_PATH}/.vagrant/logs"
multipass exec "${INSTANCE_NAME}" -- sudo bash "${GIT_PATH}/.setup/vagrant/setup_vagrant.sh" --utm

# setup_vagrant.sh swallows install_system.sh's failure and always exits 0
# (see the "if ! sudo bash ... install_system.sh" branch -- it prints a
# failure banner but never re-raises), so `multipass exec`'s own exit code
# can't be trusted to tell us whether the install actually succeeded. Check
# for a real artifact of a completed install instead.
if ! multipass exec "${INSTANCE_NAME}" -- test -f /usr/local/submitty/config/submitty.json; then
    echo "ERROR: install did not complete -- /usr/local/submitty/config/submitty.json" >&2
    echo "       was never created. Scroll up for the actual failure (it will not" >&2
    echo "       necessarily be the last thing printed)." >&2
    exit 1
fi

IP="$(multipass info "${INSTANCE_NAME}" | awk '/IPv4/{print $2; exit}')"

# install_system.sh --vagrant hardcodes submission_url/cgi_url to
# http://localhost:1511, which only resolves correctly under VirtualBox's
# NAT port-forwarding. Multipass instances get their own real IP instead, so
# repoint the config at that address the same way .setup/testing/setup.sh
# already does for its own scenario (jq-patching the live config -- Config.php
# reads submitty.json fresh on every request, no rebuild/restart required).
echo "==> Pointing submission_url/cgi_url at ${IP} (see comment above for why)"
multipass exec "${INSTANCE_NAME}" -- sudo bash -s -- "${IP}" <<'EOF'
set -euo pipefail
IP="$1"
CONF=/usr/local/submitty/config/submitty.json
BASE_URL="http://${IP}:1511"
TMP="$(mktemp)"
jq --arg v "${BASE_URL}" '.submission_url = $v | .cgi_url = ($v + "/cgi-bin")' "${CONF}" > "${TMP}"
mv "${TMP}" "${CONF}"
echo "    submitty.json submission_url/cgi_url now point at ${BASE_URL}"
EOF

echo
echo "==> Done."
echo "    Submitty should be reachable from THIS HOST ONLY at: http://${IP}:1511"
echo "    (default Multipass networking is host-local/NAT -- not reachable from your LAN)"
echo "    Shell in with:   multipass shell ${INSTANCE_NAME}"
echo "    Tear down with:  ./multipass/down.sh"
