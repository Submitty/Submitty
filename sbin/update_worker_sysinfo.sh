#!/usr/bin/env bash
# shellcheck enable=all

#### This script creates specific job files to the daemon job queue
#### It should only be invoked by other scripts or by system, not by PHP
#### For more info, see `./submitty_daemon_jobs/submitty_jobs/jobs.py`

CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../config
SUBMITTY_DATA_DIR=$(jq -r '.submitty_data_dir' "${CONF_DIR}/submitty.json")

SUBMITTY_DAEMON_JOB_Q="${SUBMITTY_DATA_DIR:?}/daemon_job_queue"

# Possible operations, "Job_Name:Job_Prefix"
# Note that these Job_Prefixes might not equal to the PHPs'
OPS=(
        "UpdateSystemInfo:sysinfo"
        "UpdateDockerImages:docker"
    )

# Execution date, will append to Job_Prefix
DATE_YMD="$(date +%Y%m%d)"

display_help() {
    op_size="${#OPS[@]}"
    echo "Usage:"
    echo -e "$0 \033[0;36m<JobName>\033[0m"
    echo -e "Dispatch \033[0;36m<JobName>\033[0m to the daemon job queue"

    for (( i=0; i<op_size; i++ )); do
        IFS=':' read -r name _ <<< "${OPS[i]}"
        echo -e "  \033[0;36m${name}\033[0m"
    done
}

# log error to stderr and exit
panic() {
    echo -e >&2 "$0:\033[1;31m [ERR!] $1\033[0m"
    exit 1
}

# log warning to stderr
warn() {
    echo -e >&2 "$0:\033[1;33m [WARN] $1\033[0m"
}

# log info to stderr
info() {
    echo -e >&2 "$0:\033[0;36m [INFO] $1\033[0m"
}


JOB_NAME=""
JOB_PREFX=""

get_job_index() {
    # length of OPS
    op_size=${#OPS[@]}

    # iterate and parse OPS, break if match
    for (( i=0; i<op_size; i++ )); do
        name=$(echo "${OPS[i]}" | cut -d':' -f1)
        [[ "$1" == "${name}" ]] && {
            IFS=':' read -r JOB_NAME JOB_PREFX <<< "${OPS[i]}"
            info "Got job ${JOB_NAME}, prefix ${JOB_PREFX}"
            break
        }
    done

    # check if any job scheme is selected
    [[ -z "${JOB_NAME}" ]] && {
        warn "Job name does not match to any job, see the help below"
        info "Job name: $1"
        display_help
        panic "No matched job"
    }
}


JSON_ARGS=""

dispatch() {
    JSON_ARGS="jq -n --arg job ${JOB_NAME}"

    info "${JSON_ARGS} '\$ARGS.named'"
    JSON_DATA=$(
        # shellcheck disable=2016
        ${JSON_ARGS} '$ARGS.named'
    ) || {
        jqVer=$(jq -V)
        panic "Failed to create json, jq version ${jqVer}"
    }

    info "Constructed json query:"
    echo "${JSON_DATA}" | {
        while IFS= read -r json_line; do
            info "${json_line}"
        done
    }

    info "Dispatching job"
    echo "${JSON_DATA}" > "${SUBMITTY_DAEMON_JOB_Q}/${JOB_PREFX}${DATE_YMD}.json" \
        || panic "Dispatching failed"
    info "Job dispatched"
}

restart_services() {
    services=(
        "submitty_autograding_shipper"
        "submitty_autograding_worker"
        "submitty_daemon_jobs_handler"
        "submitty_websocket_server"
        "nullsmtpd"
    )

    for service in "${services[@]}"; do
        sudo systemctl status "${service}" | grep -q 'active (running)'

        if [ $? -ne 0 ]; then
            echo "Restarting ${service}"
            sudo systemctl restart "${service}"
        fi
    done
}

# Check the dispatching queue
[[ ! -d "${SUBMITTY_DAEMON_JOB_Q}" ]] && {
    panic "Queue folder (${SUBMITTY_DAEMON_JOB_Q}) does not exist!"
}

restart_services

get_job_index "$@"
shift 1

dispatch
