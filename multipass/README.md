# Multipass dev VM (proof of concept)

This is an experimental replacement for `vagrant up`, evaluating
[Multipass](https://canonical.com/multipass) as a way to get a Submitty
Ubuntu 22.04 dev VM without depending on Vagrant Cloud (which HashiCorp is
retiring) or any third-party Vagrant box registry.

**Status: proof of concept.** It brings up a single dev VM and runs the
existing install script largely unmodified. It does not yet cover worker
VMs, `EXTRA=` packages, or a prebuilt/fast-path image. See "Known
limitations" below.

## Prerequisites

Install Multipass:

```bash
# macOS
brew install --cask multipass

# Windows (native, run in PowerShell)
winget install Canonical.Multipass

# Linux
sudo snap install multipass
sudo snap install multipass-sshfs
```

### macOS only: grant Full Disk Access if your checkout is under Desktop/Documents/Downloads/iCloud Drive

macOS's privacy controls (TCC) block Multipass's VM process from reading
files under those specific folders, regardless of normal Unix permissions.
If `up.sh` fails during launch/mount with a QEMU error like:

```
qemu-system-aarch64: ... failed to open '/Users/you/Desktop/...': Operation not permitted
```

fix it before continuing:

1. Find the exact binary path: `which qemu-system-aarch64` (Apple Silicon)
   or `which qemu-system-x86_64` (Intel).
2. **System Settings -> Privacy & Security -> Full Disk Access.**
3. Click **+**, authenticate, and add that binary.
4. Also add `multipassd` (`ls /opt/homebrew/opt/multipass` or
   `mdfind -name multipassd` to find it) and the `Multipass` app itself if
   it's listed under `/Applications`.
5. Retry `./multipass/up.sh` (or `multipass start <name>` if the instance
   already exists).

This is a one-time, per-machine setup step, not something the script can do
for you.

## Usage

```bash
cd /path/to/Submitty
./multipass/up.sh
```

This will take a while -- it's running the same full install
(`install_system.sh`) that takes a long time under Vagrant too; nothing
about that got faster here.

When it finishes, it prints the instance's IP:

```
==> Done.
    Submitty should be reachable from THIS HOST ONLY at: http://<ip>:1511
```

Open that URL in a browser. It will **not** work at `http://localhost:1511`
-- see "Why not localhost" below.

Tear down and start over:

```bash
./multipass/down.sh
./multipass/up.sh
```

Env vars to override defaults (instance name, CPUs, memory, disk size) are
documented at the top of `up.sh`.

## What `up.sh` actually does, and why

1. **Launches Ubuntu 22.04 from Multipass's own catalog.** This image comes
   straight from Canonical, the same org that publishes the Ubuntu cloud
   images -- not from Vagrant Cloud, not from a third-party box maintainer.
2. **Mounts the repo checkout using a *native* mount, not the default sshfs
   mount.** Multipass's default ("classic") mount is sshfs-based, and in
   testing it only granted filesystem access to the user that created the
   mount -- `sudo` (root) inside the guest got `Operation not permitted`
   trying to read anything under it, even on a plain 644 file it didn't own.
   Since the whole Submitty install has to run as root, that's a hard
   blocker, not a permissions nuance to work around. The native mount type
   (virtiofs/9p on macOS, SMB on Windows, depending on backend) doesn't have
   that restriction. Native mounts are configured at VM boot rather than
   hot-attached, which is why `up.sh` stops the instance, attaches the
   mount, then starts it again.
3. **Runs `.setup/vagrant/setup_vagrant.sh --utm`.** The `--utm` flag
   already exists in `install_system.sh` (added for manual UTM installs on
   Apple Silicon, see
   [GitHub issue #7885](https://github.com/Submitty/Submitty/issues/7885))
   and is exactly the right fit here: without it, install fails almost
   immediately with `cp: cannot stat '/home/vagrant/.ssh/authorized_keys'`,
   because Multipass's default user is `ubuntu`, not `vagrant` -- there's no
   pre-baked Vagrant box supplying a `vagrant` user with SSH keys already in
   place the way a real Vagrant box does. `--utm` creates a fresh `vagrant`
   user instead of assuming one exists, and skips the SSH-key copy. It also
   creates a `GIT_PATH/.utm` marker directory inside the repo checkout,
   which is why `/.utm/` is now in `.gitignore`.
4. **Checks that the install actually succeeded before declaring victory.**
   `setup_vagrant.sh` swallows failures from `install_system.sh` -- it
   prints a failure banner but always exits 0 itself -- so `up.sh` can't
   trust `multipass exec`'s own exit code. Instead it checks for
   `/usr/local/submitty/config/submitty.json`, which only exists once the
   install has gotten reasonably far. If that check fails, scroll up in the
   output for the real error; it will not be the last thing printed.
5. **Patches `submission_url`/`cgi_url` to the instance's real IP.** See
   below.

## Why not `localhost:1511`

Under Vagrant + VirtualBox, `localhost:1511` worked because Vagrant
configured an explicit port-forwarding rule: anything hitting the *host's*
port 1511 got silently relayed into the guest's port 1511. Multipass has no
equivalent feature (`multipass forward` is a long-standing open feature
request, unimplemented as of this writing) -- instead every instance gets
its own real IP on a private, host-local network, and you talk to that
address directly.

`Config.php` reads `submission_url`/`cgi_url` straight out of
`submitty.json` on every request (nothing is baked into a compiled asset or
Apache config), and `.setup/testing/setup.sh` already demonstrates the
pattern for changing it: a `jq` patch of the live config file. `up.sh` does
exactly that automatically, once the instance's IP is known, so no manual
step is needed.

If you want `localhost:1511` back anyway (bookmarks, existing scripts,
muscle memory), it's possible via an SSH tunnel --
`ssh -L 1511:localhost:1511 ubuntu@<instance-ip>` -- recreating the same
illusion Vagrant's port forwarding gave you. That's not built into `up.sh`
today; ask if you want it added.

## Networking / isolation

Multipass's default network is host-local/NAT: instances get a private IP
reachable from your machine, and from each other, but **not from your LAN
or the internet** -- that requires explicitly bridging an instance onto a
physical network interface (`--network`), which nothing here does. Worth
confirming once per machine: from a *different* device on your network, try
the printed `http://<ip>:1511` and confirm it fails to connect.

## Known limitations (not yet handled)

- **No worker VMs.** The current `vagrant-workers/workers.py` /
  `.vagrant/workers.json` multi-machine setup has no Multipass equivalent
  yet. A replacement would launch multiple named instances on Multipass's
  default network, discover each instance's IP via
  `multipass info <name> --format json` after launch (rather than
  pre-assigning fixed IPs the way Vagrant does), and write that into
  `autograding_workers.json` and/or each instance's `/etc/hosts`.
- **No `EXTRA=` package support** (e.g. `rpi`, `matlab`).
- **No prebuilt/fast-path image.** Every run does the full
  `install_system.sh` install; there's no equivalent yet of the (now
  HCP-Vagrant-hosted, being migrated away from) prebuilt `submitty.box` that
  skips most of the install for the common case.
- **Known rough edges on Apple Silicon carried over from issue #7885**,
  unrelated to Multipass itself: NTP clock skew after sleep/resume,
  SubmittyAnalysisTools Haskell binary downloads sometimes unavailable,
  autograding Docker image pulls timing out, and some pinned pip packages
  lacking arm64 wheels. If provisioning fails partway through on an M-series
  Mac, check whether one of these is the actual cause before assuming
  something Multipass-specific broke.
- The LVM resize step in `setup_vagrant.sh`
  (`lvresize .../ubuntu--vg-ubuntu--lv`) will print errors and do nothing --
  Multipass's image doesn't use LVM the way the old Vagrant box did. This is
  cosmetic; the script has no `set -e` around it and continues past it.
