#!/bin/bash

COMMAND=$1

if [[ $1 == "generate" ]]; then
  python3 ./generate_workers.py ${@:2}
  exit $?
fi

if [[ $1 == "start" ]]; then
  source .vagrant/.workervars 2>/dev/null
  if [[ -z $GATEWAY_IP ]]; then
    echo "Worker configuration not created, please run 'workers.sh generate'."
    exit 0
  fi

  if [[ "$(uname -s)" == "Darwin" && "$(uname -p)" == "arm" ]]; then
    if [[ -z "${HOMEBREW_PREFIX}" ]]; then
      echo "Homebrew has not been installed."
      exit 0
    fi
    if [[ ! -f "${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet" ]]; then
      brew install socket_vmnet
    fi
    mkdir -p ${HOMEBREW_PREFIX}/var/run
    stop_server() {
      if [[ ! -z $SOCKET_PID ]]; then
        echo "Stopping socket vmnet server..."
        sudo kill -9 $SOCKET_PID
      fi
    }
    trap stop_server EXIT
    echo "Starting socket vmnet server..."
    sudo ${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet --vmnet-mode=host --vmnet-gateway=${GATEWAY_IP} ${HOMEBREW_PREFIX}/var/run/socket_vmnet 1>/dev/null &
    SOCKET_PID=$!
    echo "Server running, pid=$SOCKET_PID"
    sleep 2
    wait $SOCKET_PID
  fi
  exit 0
fi

if [[ $1 == "up" ]]; then
  WORKER_MODE=1 GATEWAY_IP=${GATEWAY_IP} ${HOMEBREW_PREFIX}/opt/socket_vmnet/bin/socket_vmnet_client ${HOMEBREW_PREFIX}/var/run/socket_vmnet vagrant up
  exit 0
fi

WORKER_MODE=1 WORKERS_ONLY=1 vagrant $@
