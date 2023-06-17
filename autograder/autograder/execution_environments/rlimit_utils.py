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
    "RLIMIT_STACK": 976563,      # 100 MB Stack
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


def build_ulimit_argument(resource_limits, container_image):
    """Build --ulimit arguemtns for a particular gradeable."""
    arguments = []
    client = docker.from_env()

    # fill in default resource limits
    for resource, limit in default_limits.items():
        if resource in resource_limits:
            continue
        ulimit_name = rlimit_to_ulimit_mapping[resource]
        ulimit_arg = docker.types.Ulimit(name=ulimit_name, soft=limit, hard=limit)

        if ulimit_name == 'data':
            check_heap_size(client, ulimit_arg, container_image)
        arguments.append(ulimit_arg)

    # fill in instructor resource limits if specified
    for resource, limit in resource_limits.items():

        # Not setting a docker limit should be equivalent to infinity.
        if limit == 'RLIM_INFINITY':
            continue

        if resource in rlimit_to_ulimit_mapping:
            ulimit_name = rlimit_to_ulimit_mapping[resource]
            ulimit_arg = docker.types.Ulimit(name=ulimit_name, soft=limit, hard=limit)

            if ulimit_name == 'data':
                check_heap_size(client, ulimit_arg, container_image)
            arguments.append(ulimit_arg)
    return arguments


def check_heap_size(client, ulimit, container_image):
    """Verify that the 'data' ulimit is at least the image size."""
    image = client.images.get(container_image)
    image_attributes = image.attrs
    image_size = int(image_attributes['Size'])

    # adds a buffer of the image size to the HEAP
    if ulimit['Soft'] < image_size:
        ulimit['Soft'] += image_size
        ulimit['Hard'] += image_size
