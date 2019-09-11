import docker

default_limits = {
	"RLIMIT_CPU" : 20
}

# maps gradeable config file terms to docker ulimit terms
rlimit_to_ulimit_mapping = {
	"RLIMIT_CPU" : "cpu",
	"RLIMIT_FSIZE" : "fsize",
	"RLIMIT_DATA" : "data",
	"RLIMIT_STACK" : "stack",
	"RLIMIT_CORE" : "core",
	"RLIMIT_RSS" : "rss",
	"RLIMIT_NPROC" : "nproc",
	"RLIMIT_NOFILE" : "nofile",
	"RLIMIT_MEMLOCK" : "memlock",
	"RLIMIT_LOCKS" : "locks",
	"RLIMIT_SIGPENDING" : "sigpending",
	"RLIMIT_MSGQUEUE" : "msgqueue",
	"RLIMIT_NICE" : "nice",
	"RLIMIT_RTPRIO" : "rtprio",
	"RLIMIT_RTTIME" : "rttime"
}

# builds options for the --ulimit flag in docker run
def build_ulimit_argument(resource_limits):
	arguments = []

	# no resource limits specified, fall back to default
	if len(resource_limits) == 0:
		for resource, limit in default_limits.items():
			arguments += [docker.types.Ulimit(name=rlimit_to_ulimit_mapping[resource], hard=limit)]
		return arguments

	# instructor provided resource limits
	for resource, limit in resource_limits.items():
		if resource not in rlimit_to_ulimit_mapping:
			print('Unknown RLIMIT resource')
		else:
			arguments += [docker.types.Ulimit(name=rlimit_to_ulimit_mapping[resource], hard=limit)]
	return arguments

		