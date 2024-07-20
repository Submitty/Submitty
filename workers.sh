#!/bin/bash

if [[ $1 == "generate" ]]; then
  python3 ./generate_workers.py "${@:2}"
  exit $?
fi

if [[ $1 == "socket" ]]; then
  if [[ ! $(uname -s) == "Darwin" ]]; then
    echo "Socket networking is only for macOS using QEMU"
    exit 0
  fi

  if [[ -z "${HOMEBREW_PREFIX}" ]]; then
    echo "Homebrew has not been installed."
    exit 0
  fi

  if [[ ! -f "${HOMEBREW_PREFIX}/bin/jq" ]]; then
    brew install jq
  fi

  VM_PROVIDER=$(jq -r '.provider' .vagrant/workers.json)
  GATEWAY_IP=$(jq -r '.gateway' .vagrant/workers.json)

  if [[ ! $VM_PROVIDER == "qemu" ]]; then
    echo "Socket networking is only for QEMU. Your configuration is set to '$VM_PROVIDER'."
    exit 0
  fi

  LOCKDIR=/tmp/submitty-workers-socket.lock

  if [[ $2 == "start" ]]; then
    if ! mkdir "$LOCKDIR" 2>/dev/null; then
      echo "There is a socket server already running on this machine. Run 'socket stop' to stop it."
      exit 0
    fi

    echo $$ > "${LOCKDIR}/pid"

    if [[ -z $GATEWAY_IP ]]; then
      echo "Worker configuration is not valid, please run 'workers.sh generate'."
      exit 0
    fi

    if [[ ! -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet" ]]; then
      brew install socket_vmnet
    fi

    MODE=host
    if [[ $3 == "--public" ]]; then
      MODE=shared
    fi

    mkdir -p "${HOMEBREW_PREFIX}/var/run"
    echo "Starting socket vmnet server..."
    if [[ $MODE == "shared" ]]; then
      echo "Using public networking"
    fi
    sudo "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet" --vmnet-mode="${MODE}" --vmnet-gateway="${GATEWAY_IP}" "${HOMEBREW_PREFIX}/var/run/socket_vmnet" 1>/dev/null &
    echo $! > "${LOCKDIR}/socket_pid"
    echo "Server running"

    exit 0
  fi

  if [[ $2 == "stop" ]]; then
    # Kill the 'socket start' process if running
    if [[ -f "${LOCKDIR}/pid" ]]; then
      PID=$(cat "${LOCKDIR}/pid")
      ps -p "$PID" 1>/dev/null && kill -9 "$PID"
    fi

    # Kill the socket
    if [[ -f "${LOCKDIR}/socket_pid" ]]; then
      echo "Stopping socket vmnet server..."
      SOCKET_PID=$(cat "${LOCKDIR}/socket_pid")
      ps -p "$SOCKET_PID" 1>/dev/null && sudo kill -2 "$SOCKET_PID"
      rm -rf "$LOCKDIR"
      echo "Successfully stopped"
      exit 0
    fi
    
    echo "Socket server is not running."
    exit 0
  fi

  echo "Expected a command ('start', 'stop')"
  exit 1
fi

if [[ $(uname -s) == "Darwin" && $1 == "up" ]]; then
  LOCKDIR=/tmp/submitty-workers-socket.lock
  VM_PROVIDER=$(jq -r '.provider' .vagrant/workers.json)
  GATEWAY_IP=$(jq -r '.gateway' .vagrant/workers.json)
  if [[ $VM_PROVIDER == "qemu" ]]; then
    PLUGIN_VERSION=24.06.00
    PLUGIN_INFO=$(vagrant plugin list --machine-readable | grep vagrant-qemu,plugin-version,)
    if [[ ! $PLUGIN_INFO == *"plugin-version,${PLUGIN_VERSION}%"* ]]; then
      echo "Updating QEMU plugin..."
      DIR=$(pwd)
      TMPDIR=$(mktemp -d)
      cd "$TMPDIR" || exit
      curl -L -o qemu.gem "https://github.com/Submitty/vagrant-qemu/releases/download/v${PLUGIN_VERSION}/vagrant-qemu-${PLUGIN_VERSION}.gem" &>/dev/null
      vagrant plugin install qemu.gem
      cd "$DIR" || exit
      rm -rf "$TMPDIR"
      echo "Successfully updated"
    fi

    PID=$(cat "${LOCKDIR}/pid") &>/dev/null
    SOCKET_PID=$(cat "${LOCKDIR}/socket_pid") &>/dev/null
    if ps -p "$PID" &>/dev/null; then
      echo "Waiting for socket to start..."
      wait "$PID"
    fi
    if ! ps -p "$SOCKET_PID" &>/dev/null; then
      echo "Socket not running, run 'socket start'"
      exit 1
    fi
    WORKER_MODE=1 GATEWAY_IP="${GATEWAY_IP}" "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet_client" "${HOMEBREW_PREFIX}/var/run/socket_vmnet" vagrant up "${@:2}"
    exit 0
  fi
fi

WORKER_MODE=1 vagrant "$@"
