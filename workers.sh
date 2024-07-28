#!/bin/bash

if ! which python3 >/dev/null; then
  echo "Python 3 has not been configured. Make sure to install it and add it to your PATH."
  exit 1
fi

if [[ $1 == "generate" ]]; then
  python3 ./generate_workers.py "${@:2}"
  exit $?
fi

if [[ ! -f ".vagrant/workers.json" ]]; then
  echo "No worker configuration has been generated."
  echo "Please run 'vagrant workers generate'."
  exit 1
fi

VM_PROVIDER=$(python3 -c "import json;print(json.load(open('.vagrant/workers.json'))['provider'])")
GATEWAY_IP=$(python3 -c "import json;print(json.load(open('.vagrant/workers.json'))['gateway'])")

if [[ -z $VM_PROVIDER || -z $GATEWAY_IP ]]; then
  echo "Worker configuration is not valid, please run 'vagrant workers generate'."
  exit 1
fi

if [[ $1 == "socket" ]]; then
  if [[ ! $(uname -s) == "Darwin" ]]; then
    echo "Socket networking is only for macOS using QEMU"
    exit 1
  fi

  if [[ -z "${HOMEBREW_PREFIX}" ]]; then
    echo "Homebrew has not been configured. Make sure to install homebrew and follow the instructions at the end to add it to your PATH."
    exit 1
  fi

  if [[ $VM_PROVIDER != "qemu" ]]; then
    echo "Socket networking is only for QEMU. Your configuration is set to '$VM_PROVIDER'."
    exit 1
  fi

  if [[ $2 == "start" ]]; then
    PIDS=$(pgrep -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet")

    if [[ -n $PIDS ]]; then
      echo "There is a socket server already running on this machine. Run 'vagrant workers socket stop' to stop it."
      exit 1
    fi

    if [[ ! -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet" ]]; then
      brew install socket_vmnet
    fi

    MODE=host
    if [[ $3 == "--public" ]]; then
      MODE=shared
    fi

    mkdir -p "${HOMEBREW_PREFIX}/var/run"
    sudo echo "Starting socket vmnet server..."
    if [[ $MODE == "shared" ]]; then
      echo "Using public networking"
    fi
    sudo "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet" --vmnet-mode="${MODE}" --vmnet-gateway="${GATEWAY_IP}" "${HOMEBREW_PREFIX}/var/run/socket_vmnet" 1>/dev/null &
    echo "Server running"

    exit 0
  fi

  if [[ $2 == "stop" ]]; then
    PIDS=$(pgrep -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet")

    if [[ -z $PIDS ]]; then
      echo "Socket server is not running."
      exit 1
    fi

    sudo kill -4 $PIDS
    echo "Successfully stopped socket server"

    exit 0
  fi

  if [[ $2 == "restart" ]]; then
    sudo echo "Restarting..."
    bash $0 socket stop
    bash $0 socket start "${@:3}"
    exit $?
  fi

  echo "Expected a command ('start', 'stop', 'restart')"
  exit 1
fi

if [[ $(uname -s) == "Darwin" && $1 == "up" ]]; then
  if [[ $VM_PROVIDER == "qemu" ]]; then
    # Install Submitty/vagrant-qemu plugin
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

    # Exit if socket is not running
    PIDS=$(pgrep -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet")
    if [[ -z $PIDS ]]; then
      echo "Socket server is not running, to start it run 'vagrant workers socket start'."
      exit 1
    fi

    WORKER_MODE=1 GATEWAY_IP="${GATEWAY_IP}" "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet_client" "${HOMEBREW_PREFIX}/var/run/socket_vmnet" vagrant up --provider=qemu "${@:2}"
    exit $?
  fi

  vagrant up --provider="$VM_PROVIDER" "${@:2}"
  exit $?
fi

WORKER_MODE=1 vagrant "$@"
