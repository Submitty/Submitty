"""
Helper to set resource limits in containers.

Contains default values for container gradeables and helper functions.
"""
import docker

"""
Default limits for containers
Units increment 1024 byte increments
"""
default_limits = {
    "RLIMIT_CPU": 20,            # 20 second CPU time
    "RLIMIT_STACK": 48828,       # 50 MB Stack
    "RLIMIT_DATA": 9765625       # 1GB Heap
}

"""
maps gradeable config file terms to docker ulimit terms
NPROC and AS are omitted due to unexpected behavior
"""
rlimit_to_ulimit_mapping = {
    "RLIMIT_CPU": "cpu",
    "RLIMIT_FSIZE": "fsize",
    "RLIMIT_DATA": "data",
    "RLIMIT_STACK": "stack",
    "RLIMIT_CORE": "core",
    "RLIMIT_RSS": "rss",
    "RLIMIT_NOFILE": "nofile",
    "RLIMIT_MEMLOCK": "memlock",
    "RLIMIT_LOCKS": "locks",
    "RLIMIT_SIGPENDING": "sigpending",
    "RLIMIT_MSGQUEUE": "msgqueue",
    "RLIMIT_NICE": "nice",
    "RLIMIT_RTPRIO": "rtprio",
    "RLIMIT_RTTIME": "rttime"
}


def build_ulimit_argument(resource_limits):
    """Given a dict of resource limits, builds argumnets for --ulimit flag in docker."""
    arguments = []

    # no resource limits specified, fall back to default
    if len(resource_limits) == 0:
        for resource, limit in default_limits.items():
            ulimit_name = rlimit_to_ulimit_mapping[resource]
            arguments += [docker.types.Ulimit(name=ulimit_name, soft=limit, hard=limit)]
        return arguments

    # instructor provided resource limits
    for resource, limit in resource_limits.items():
        if resource not in rlimit_to_ulimit_mapping:
            print('Unknown RLIMIT resource')
        else:
            ulimit_name = rlimit_to_ulimit_mapping[resource]
            arguments += [docker.types.Ulimit(name=ulimit_name, soft=limit, hard=limit)]
    return arguments
