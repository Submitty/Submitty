#!/bin/bash

components=("shipper" "worker")
targets=("primary" "perform_on_all_workers")
status_codes=("inactive" "active" "failure" "bad_arguments" "io_error")
status_script="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"

for component in "${components[@]}"; do
  for target in "${targets[@]}"; do
    if [[ "$target" == "primary" ]]; then
      cmd_prefix="sudo"
    else
      cmd_prefix="sudo -u submitty_daemon"
    fi

    $cmd_prefix python3 "$status_script" --daemon "$component" --target "$target" status
    status=$?

    if [[ "$status" -ne 1 ]]; then
        today=$(date +%Y%m%d)
        log_file="/var/log/services/${today}.txt"

        if [[ ! -f "${log_file}" ]]; then
            sudo touch "${log_file}"
        fi

        echo -e "Restarting autograding ${component} for ${target}\n\nLast status: ${status_codes[$status]}" | sudo tee -a "${log_file}"
        echo -e "\n----------------------------------------\n" | sudo tee -a "${log_file}"

        $cmd_prefix python3 "$status_script" --daemon "$component" --target "$target" restart
    fi
  done
done
