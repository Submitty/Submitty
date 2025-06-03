#!/bin/bash

RESTART_NEEDED=false
COMPONENTS=("shipper" "worker")
TARGETS=("primary" "perform_on_all_workers")
STATUS_SCRIPT="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"
RESTART_SCRIPT="/usr/local/submitty/sbin/restart_shipper_and_all_workers.py"

for component in "${COMPONENTS[@]}"; do
  for target in "${TARGETS[@]}"; do
    echo "Checking status of $component on $target..."
    if [[ "$target" == "primary" ]]; then
      sudo python3 $STATUS_SCRIPT --daemon "$component" --target "$target" status
    else
      python3 -u submitty_daemon $STATUS_SCRIPT --daemon "$component" --target "$target" status
    fi
    STATUS=$?

    if [[ "$STATUS" -ne 1 ]]; then
      echo "Detected issue with $component on $target (status code: $STATUS)"
      RESTART_NEEDED=true
      break 2
    fi
  done
done

if $RESTART_NEEDED; then
  echo "Restarting all shippers and workers..."
  python3 $RESTART_SCRIPT
else
  echo "All daemons are healthy."
fi
