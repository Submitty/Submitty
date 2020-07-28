import json
import os
import traceback
import time
from pwd import getpwnam
from timeit import default_timer as timer
import shutil
import docker

from submitty_utils import dateutils
from . import secure_execution_environment, rlimit_utils
from .. import autograding_utils


class Container():
    """
    Containers are the building blocks of a container network. Containers know how to
    create, start, and cleanup after themselves. Note that a network of containers
    can be made up of 1 or more containers.
    """
    def __init__(self, container_info, untrusted_user, testcase_directory, more_than_one,
                 is_test_environment, log_function, log_meta):
        self.name = container_info['container_name']

        # If there are multiple containers, each gets its own directory under
        # testcase_directory, otherwise, we can just use testcase_directory
        if more_than_one:
            self.directory = os.path.join(testcase_directory, self.name)
        else:
            self.directory = testcase_directory
        # Check if we are the router in a container network
        self.is_router = True if self.name == 'router' else False

        self.image = container_info['container_image']
        self.is_server = container_info['server']
        self.outgoing_connections = container_info['outgoing_connections']
        self.container_rlimits = container_info['container_rlimits']
        self.container_grading_time = 0
        # If we are in production, we need to run as an untrusted user inside of
        # our docker container.
        self.container_user_argument = str(getpwnam(untrusted_user).pw_uid)
        self.full_name = f'{untrusted_user}_{self.name}'
        self.tcp_port_range = container_info['tcp_port_range']
        self.udp_port_range = container_info['udp_port_range']
        # This will be populated later
        self.return_code = None
        self.log_function = log_function
        self.log_meta = log_meta
        self.container = None
        # A socket for communication with the container.
        self.socket = None
        # Maps a network name to an ip address
        self.ip_address_map = {}

        # Determine whether or not we need to pull the default submitty router into
        # this container's directory.
        need_router = container_info.get('import_default_router', False)
        if self.name == 'router' and need_router:
            self.import_router = True
        else:
            self.import_router = False

    def create(self, execution_script, arguments, more_than_one):
        """ Create (but don't start) this container. """
        container_create_time = timer()
        self.log_meta('CREATE BEGIN', self.full_name)

        client = docker.from_env()

        mount = {
            self.directory: {
                'bind': self.directory,
                'mode': 'rw'
            }
        }

        # Only pass container name to testcases with greater than one container.
        # (Doing otherwise breaks compilation)
        container_name_argument = ['--container_name', self.name] if more_than_one else []
        # A server container does not run student code, but instead hosts
        # a service (i.e. a database.)

        try:
            if self.is_server:
                self.container = client.containers.create(
                    self.image,
                    stdin_open=True,
                    tty=True,
                    network='none',
                    volumes=mount,
                    working_dir=self.directory,
                    name=self.full_name
                )
            else:
                container_ulimits = rlimit_utils.build_ulimit_argument(
                    self.container_rlimits,
                    self.image
                )
                command = [execution_script, ] + arguments + container_name_argument
                self.container = client.containers.create(
                    self.image,
                    command=command,
                    ulimits=container_ulimits,
                    stdin_open=True,
                    tty=True,
                    network='none',
                    user=self.container_user_argument,
                    volumes=mount,
                    working_dir=self.directory,
                    hostname=self.name,
                    name=self.full_name
                )
        except docker.errors.ImageNotFound:
            self.log_function(f'ERROR: The image {self.image} is not available on this worker')
            client.close()
            raise

        self.log_meta(
            'CREATE END',
            self.full_name,
            self.container.short_id,
            timer() - container_create_time
        )
        client.close()

    def start(self, logfile):
        container_start_time = timer()
        self.log_meta('START BEGIN', self.full_name, self.container.short_id)

        self.container.start()
        self.socket = self.container.attach_socket(params={'stdin': 1, 'stream': 1})

        self.container_grading_time = timer()
        self.log_meta(
            'START END',
            self.full_name,
            self.container.short_id,
            timer() - container_start_time
        )

    def set_ip_address(self, network_name, ip_address):
        self.ip_address_map[network_name] = ip_address

    def get_ip_address(self, network_name):
        return self.ip_address_map[network_name]

    def cleanup_container(self, logfile):
        """ Remove this container. """
        if not self.is_server:
            status = self.container.wait()
            self.return_code = status['StatusCode']

        logs = self.container.logs(stdout=True, stderr=False).decode('utf-8')
        print(f'Log entry for {self.name}:\n', file=logfile)
        print(logs, file=logfile)
        print('\n', file=logfile)

        self.socket._response.close()
        self.socket.close()
        self.socket._response = None

        self.container.remove(force=True)
        self.container.client.api.close()
        self.container.client.close()
        self.log_function(
            f'{dateutils.get_current_time()} docker container '
            f'{self.container.short_id} destroyed'
        )
        self.log_meta(
            'DESTROY',
            self.full_name,
            self.container.short_id, timer() - self.container_grading_time
        )


class ContainerNetwork(secure_execution_environment.SecureExecutionEnvironment):
    """
    A Container Network ensures a secure execution environment by executing instances of student
    code within a secure Docker Container. To add an extra layer of security, files and directories
    are carefully permissioned, and code is executed as a limited-access, untrusted user.
    Therefore, code is effectively run in a Jailed Sandbox within the container. Containers may
    be networked together to test networked gradeables.
    """
    def __init__(self, config, job_id, untrusted_user, testcase_directory, is_vcs,
                 is_batch_job, complete_config_obj, testcase_info, autograding_directory,
                 log_path, stack_trace_log_path, is_test_environment):
        super().__init__(config, job_id, untrusted_user, testcase_directory, is_vcs,
                         is_batch_job, complete_config_obj, testcase_info, autograding_directory,
                         log_path, stack_trace_log_path, is_test_environment)

        containers = []
        container_specs = testcase_info.get('containers', [])
        solution_container_specs = testcase_info.get('solution_containers', [])
        gradeable_rlimits = complete_config_obj.get('resource_limits', {})
        # If there are container specifications in the complete_config, create objects for them,
        # else, create a single default container.
        if len(container_specs) > 0:
            greater_than_one = True if len(container_specs) > 1 else False
            current_tcp_port = 9000
            current_udp_port = 15000
            for container_spec in container_specs:
                container_spec['container_rlimits'] = gradeable_rlimits
                container_spec['tcp_port_range'] = (
                    current_tcp_port,
                    current_tcp_port + container_spec.get('number_of_ports', 1) - 1
                )
                container_spec['udp_port_range'] = (
                    current_udp_port,
                    current_udp_port + container_spec.get('number_of_ports', 1) - 1
                )
                current_udp_port += container_spec.get('number_of_ports', 1)
                current_tcp_port += container_spec.get('number_of_ports', 1)
                containers.append(
                    Container(
                        container_spec,
                        untrusted_user,
                        os.path.join(self.tmp_work, testcase_directory),
                        greater_than_one,
                        self.is_test_environment,
                        self.log_message,
                        self.log_container_meta
                    )
                )
        else:
            container_spec = {
                'container_name': 'temporary_container',
                'container_image': 'submitty/autograding-default:latest',
                'server': False,
                'outgoing_connections': [],
                'container_rlimits': gradeable_rlimits,
                'tcp_port_range': (9000, 9000),
                'udp_port_range': (1500, 1500)
            }
            containers.append(
                Container(
                    container_spec,
                    untrusted_user,
                    os.path.join(self.tmp_work, testcase_directory),
                    False,
                    self.is_test_environment,
                    self.log_message,
                    self.log_container_meta
                )
            )
        self.containers = containers

        # Solution containers are a network of containers which run instructor code.
        # We instantiate objects for them in a similar manner to the way that we instantiate
        # execution containers (above), but do not add a default container if they are not present.
        solution_containers = []
        greater_than_one_solution_container = True if len(solution_container_specs) > 1 else False
        current_tcp_port = 9000
        current_udp_port = 15000
        for solution_container_spec in solution_container_specs:
            solution_container_spec['container_rlimits'] = gradeable_rlimits
            solution_container_spec['tcp_port_range'] = (
                current_tcp_port,
                current_tcp_port + solution_container_spec.get('number_of_ports', 1) - 1
            )
            solution_container_spec['udp_port_range'] = (
                current_udp_port,
                current_udp_port + solution_container_spec.get('number_of_ports', 1) - 1
            )
            current_udp_port += solution_container_spec.get('number_of_ports', 1)
            current_tcp_port += solution_container_spec.get('number_of_ports', 1)
            solution_containers.append(
                Container(
                    solution_container_spec,
                    untrusted_user,
                    self.random_output_directory,
                    greater_than_one_solution_container,
                    self.is_test_environment,
                    self.log_message,
                    self.log_container_meta
                )
            )
        self.solution_containers = solution_containers

        # Check for dispatcher actions (standard input)
        self.dispatcher_actions = testcase_info.get('dispatcher_actions', [])

        # As new container networks are generated, they will be appended to this list.
        self.networks = []

    ###########################################################
    #
    # Container Network Functions
    #
    ###########################################################
    def get_router(self, containers):
        """ Given a set of containers, return the router. """
        for container in containers:
            if container.name == 'router':
                return container
        return None

    def get_server_containers(self, all_containers):
        """ Given a set of containers, return any server containers. """
        containers = []
        for container in all_containers:
            if container.name != 'router' and container.is_server is True:
                containers.append(container)
        return containers

    def get_standard_containers(self, all_containers):
        """ Given a set of containers, return all non-router, non-server containers. """
        containers = []
        for container in all_containers:
            if container.name != 'router' and container.is_server is False:
                containers.append(container)
        return containers

    def create_containers(self, containers, script, arguments):
        """ Given a set of containers, create each of them. """
        try:
            self.verify_execution_status()
        except Exception:
            self.log_stack_trace(traceback.format_exc())
            self.log_message("ERROR: Could not verify execution mode status.")
            return

        more_than_one = len(containers) > 1
        created_containers = []
        for container in containers:
            my_script = os.path.join(container.directory, script)
            try:
                container.create(my_script, arguments, more_than_one)
                created_containers.append(container)
            except Exception:
                self.log_message(
                  f"ERROR: Could not create container {container.name}"
                  f" with image {container.image}"
                )
                self.log_stack_trace(traceback.format_exc())

                # Failing to create a container is a critical error.
                # Try to clean up any containers we successfully created, then raise.
                for c in created_containers:
                    try:
                        c.cleanup_container()
                    except Exception:
                        pass
                raise

    def network_containers(self, containers):
        """ Given a set of containers, network them per their specifications. """
        if len(containers) <= 1:
            return
        client = docker.from_env()
        none_network = client.networks.get('none')
        client.close()

        # Remove all containers from the none network
        for container in containers:
            none_network.disconnect(container.container, force=True)

        if self.get_router(containers) is not None:
            self.network_containers_with_router(containers)
        else:
            self.network_containers_routerless(containers)

        # Provide an initialization file to each container.
        self.create_knownhosts_txt(containers)
        self.create_knownhosts_json(containers)

    def network_containers_routerless(self, containers):
        """ If there is no router, all containers are added to the same network. """
        client = docker.from_env()
        network_name = f'{self.untrusted_user}_routerless_network'

        # Assumes untrustedXX naming scheme, where XX is a number
        untrusted_num = int(self.untrusted_user.replace('untrusted', '')) + 100
        subnet = 1
        ip_address_start = f'10.{untrusted_num}.{subnet}'
        ipam_pool = docker.types.IPAMPool(subnet=f'{ip_address_start}.0/24')
        ipam_config = docker.types.IPAMConfig(pool_configs=[ipam_pool])
        # Create the global network
        # TODO: Can fail on ip conflict.
        network = client.networks.create(
            network_name,
            driver='bridge',
            ipam=ipam_config,
            internal=True
        )
        client.close()

        host = 2
        for container in containers:
            ip_address = f'{ip_address_start}.{host}'
            network.connect(
                container.container,
                ipv4_address=ip_address,
                aliases=[container.name, ]
            )
            container.set_ip_address(network_name, ip_address)
            host += 1
        self.networks.append(network)

    def network_containers_with_router(self, containers):
        """
        If there is a router, all containers are added to their own network, on which the only
        other endpoint is the router, which has been aliased to impersonate all other reachable
        endpoints.
        """
        client = docker.from_env()
        router = self.get_container_with_name('router', containers)
        router_connections = {}
        network_num = 10
        subnet = 1
        # Assumes untrustedXX naming scheme, where XX is a number
        untrusted_num = int(self.untrusted_user.replace('untrusted', '')) + 100
        container_to_subnet = {}

        for container in containers:
            network_name = f"{container.full_name}_network"
            if container.name == 'router':
                continue
            # We are creating a new subnet with a new subnet number
            subnet += 1
            # We maintain a map of container_name to subnet for use by the router.
            container_to_subnet[container.name] = subnet
            actual_name = '{0}_Actual'.format(container.name)

            # Create the network with the appropriate iprange
            ipam_pool = docker.types.IPAMPool(subnet=f'{network_num}.{untrusted_num}.{subnet}.0/24')
            ipam_config = docker.types.IPAMConfig(pool_configs=[ipam_pool])
            network = client.networks.create(
                network_name,
                ipam=ipam_config,
                driver='bridge',
                internal=True
            )

            # We connect the container with host=2. Later we'll connect the router with host=3
            container_ip = f'{network_num}.{untrusted_num}.{subnet}.2'
            container.set_ip_address(network_name, container_ip)

            network.connect(container.container, ipv4_address=container_ip, aliases=[actual_name, ])
            self.networks.append(network)

            # The router pretends to be all dockers on this network.
            if len(container.outgoing_connections) == 0:
                connected_machines = [x.name for x in containers]
            else:
                connected_machines = container.outgoing_connections

            for connected_machine in connected_machines:
                if connected_machine == 'router':
                    continue
                if connected_machine == container.name:
                    continue

                if container.name not in router_connections:
                    router_connections[container.name] = []

                if connected_machine not in router_connections:
                    router_connections[connected_machine] = []

                # The router must be in both endpoints' network, and must connect to
                # all endpoints on a network simultaneously, so we group together
                # all connections here, and then connect later.
                router_connections[container.name].append(connected_machine)
                router_connections[connected_machine].append(container.name)
        # Connect the router to all networks.
        for startpoint, endpoints in router_connections.items():
            full_startpoint_name = f'{self.untrusted_user}_{startpoint}'
            network_name = f"{full_startpoint_name}_network"
            # Store the ip address of the router on this network
            router_ip = f'{network_num}.{untrusted_num}.{container_to_subnet[startpoint]}.3'
            router.set_ip_address(network_name, router_ip)

            aliases = []
            for endpoint in endpoints:
                if endpoint in aliases:
                    continue
                aliases.append(endpoint)
            network = self.get_network_with_name(network_name)
            network.connect(router.container, ipv4_address=router_ip, aliases=aliases)
        client.close()

    def cleanup_networks(self):
        """ Destroy all created networks. """
        for network in self.networks:
            try:
                network.remove()
                network.client.api.close()
                network.client.close()
                self.log_message(
                  f'{dateutils.get_current_time()} '
                  f'destroying docker network {network}'
                )
            except Exception:
                self.log_message(
                  f'{dateutils.get_current_time()} ERROR: Could not remove docker '
                  f'network {network}'
                )
        self.networks.clear()

    def create_knownhosts_json(self, containers):
        """
        Given a set of containers, add initialization files to each
        container's directory which specify how to connect to other endpoints
        on the container's network (hostname, port).
        """

        # Writing complete knownhost JSON to the container directory
        router = self.get_router(containers)

        sorted_networked_containers = sorted(containers, key=lambda x: x.name)
        for container in sorted_networked_containers:
            knownhosts_location = os.path.join(container.directory, 'knownhosts.json')
            container_knownhost = {'hosts': {}}

            if len(container.outgoing_connections) == 0:
                connections = [x.name for x in containers]
            else:
                connections = container.outgoing_connections

            if container.name not in connections:
                connections.append(container.name)

            sorted_connections = sorted(connections)
            for connected_container_name in sorted_connections:
                connected_container = self.get_container_with_name(
                    connected_container_name,
                    containers
                )
                network_name = f"{container.full_name}_network"
                # If there is a router, the router is impersonating all other
                # containers, but has only one ip address.
                if router is not None:
                    # Even if we are injecting the router, we know who WE are.
                    if container.name == 'router' and connected_container_name == 'router':
                        continue
                    elif container.name == connected_container_name:
                        network_name = f"{container.full_name}_network"
                        ip_address = container.get_ip_address(network_name)
                    # If this node is not the router, we must inject the router
                    elif container.name != 'router':
                        # Get the router's ip on the container's network
                        network_name = f"{container.full_name}_network"
                        ip_address = router.get_ip_address(network_name)
                    else:
                        # If we are the router, get the connected container's ip on its own network
                        network_name = f"{self.untrusted_user}_{connected_container_name}_network"
                        ip_address = connected_container.get_ip_address(network_name)
                else:
                    ip_address = connected_container.get_ip_address(
                        f'{self.untrusted_user}_routerless_network'
                    )

                container_knownhost['hosts'][connected_container.name] = {
                    'tcp_start_port': connected_container.tcp_port_range[0],
                    'tcp_end_port': connected_container.tcp_port_range[1],
                    'udp_start_port': connected_container.udp_port_range[0],
                    'udp_end_port': connected_container.udp_port_range[1],
                    'ip_address': ip_address
                }

            with open(knownhosts_location, 'w') as outfile:
                json.dump(container_knownhost, outfile, indent=4)
            autograding_utils.add_all_permissions(knownhosts_location)

    def create_knownhosts_txt(self, containers):
        """
        Given a set of containers, add initialization files to each
        container's directory which specify how to connect to other endpoints
        on the container's network (hostname, port).
        """
        tcp_connection_list = []
        udp_connection_list = []

        sorted_containers = sorted(containers, key=lambda x: x.name)
        for container in sorted_containers:
            tcp_connection_list.append([container.name, container.tcp_port_range[0]])
            udp_connection_list.append([container.name, container.udp_port_range[0]])

        # Writing complete knownhosts csvs to input directory'
        networked_containers = self.get_standard_containers(containers)
        router = self.get_router(containers)

        if router is not None:
            networked_containers.append(router)

        sorted_networked_containers = sorted(networked_containers, key=lambda x: x.name)
        for container in sorted_networked_containers:
            knownhosts_location = os.path.join(container.directory, 'knownhosts_tcp.txt')
            with open(knownhosts_location, 'w') as outfile:
                for tup in tcp_connection_list:
                    outfile.write(" ".join(map(str, tup)) + '\n')
                    outfile.flush()
            autograding_utils.add_all_permissions(knownhosts_location)

            knownhosts_location = os.path.join(container.directory, 'knownhosts_udp.txt')
            with open(knownhosts_location, 'w') as outfile:
                for tup in udp_connection_list:
                    outfile.write(" ".join(map(str, tup)) + '\n')
                    outfile.flush()
            autograding_utils.add_all_permissions(knownhosts_location)

    ###########################################################
    #
    # Dispatcher Functions
    #
    ###########################################################
    def process_dispatcher_actions(self, containers):
        """
        Deliver actions (stdin, delay, stop, start, kill)
        to a set of containers per their testcase specification.
        """
        for action_obj in self.dispatcher_actions:
            action_type = action_obj["action"]

            if action_type == "delay":
                time_to_delay = float(action_obj["seconds"])
                while time_to_delay > 0 and self.at_least_one_alive(containers):
                    if time_to_delay >= .1:
                        time.sleep(.1)
                    else:
                        time.sleep(time_to_delay)
                    # This can go negative (subtracts .1 even in the else case) but that's fine.
                    time_to_delay -= .1
            elif action_type == "stdin":
                self.send_message_to_processes(
                    containers,
                    action_obj["string"],
                    action_obj["containers"]
                )
            elif action_type in ['stop', 'start', 'kill']:
                self.send_message_to_processes(
                    containers,
                    f"SUBMITTY_SIGNAL:{action_type.upper()}\n",
                    action_obj['containers']
                )
            # A .1 second delay after each action to keep things flowing smoothly.
            time.sleep(.1)

        if len(self.dispatcher_actions) > 0:
            names = [c.name for c in containers]
            self.send_message_to_processes(containers, "SUBMITTY_SIGNAL:FINALMESSAGE\n", names)

    def get_container_with_name(self, name, containers):
        """ Given a name, grab the corresponding container. """
        for container in containers:
            if container.name == name:
                return container
        return None

    def get_network_with_name(self, name):
        """ Given a name, grab the corresponding container. """
        for network in self.networks:
            if network.name == name:
                return network
        return None

    # Targets must hold names/keys for the processes dictionary
    def send_message_to_processes(self, containers, message, targets):
        """
        Given containers, targets, and a message, deliver the message to the target containers.
        """
        for target in targets:
            container = self.get_container_with_name(target, containers)
            container.container.reload()
            if container.container.status != 'exited':
                os.write(container.socket.fileno(), message.encode('utf-8'))
            else:
                pass

    def at_least_one_alive(self, containers):
        """ Check that at least one of a set of containers is running. """
        for container in self.get_standard_containers(containers):
            # Update container variables so that status is accurate.
            container.container.reload()
            if container.container.status != 'exited':
                return True
        return False

    ###########################################################
    #
    # Overridden Secure Execution Environment Functions
    #
    ###########################################################
    def setup_for_compilation_testcase(self):
        """ For every container, set up its directory for compilation. """
        os.chdir(self.tmp_work)

        for container in self.containers:
            self._setup_single_directory_for_compilation(container.directory)
            # Run any necessary pre_commands
            self._run_pre_commands(container.directory)

    def setup_for_execution_testcase(self, testcase_dependencies):
        """ For every container, set up its directory for execution. """
        os.chdir(self.tmp_work)
        for container in self.containers:
            self._setup_single_directory_for_execution(container.directory, testcase_dependencies)
            self._run_pre_commands(container.directory)

            # Copy in the submitty_router if necessary.
            if container.import_router:
                router_path = os.path.join(self.tmp_autograding, "bin", "submitty_router.py")
                self.log_message(f"COPYING:\n\t{router_path}\n\t{container.directory}")
                shutil.copy(router_path, container.directory)
                autograding_utils.add_all_permissions(container.directory)

    def setup_for_random_output(self, testcase_dependencies):
        """ For every container, set up its directory for random output generation. """
        os.chdir(self.tmp_work)
        for container in self.solution_containers:
            self._setup_single_directory_for_random_output(
                container.directory,
                testcase_dependencies
            )
            self._run_pre_commands(container.directory)

            if container.import_router:
                router_path = os.path.join(self.tmp_autograding, "bin", "submitty_router.py")
                self.log_message(f"COPYING:\n\t{router_path}\n\t{container.directory}")
                shutil.copy(router_path, container.directory)
                autograding_utils.add_all_permissions(container.directory)

    def setup_for_archival(self, overall_log):
        """ For every container, set up its directory for archival. """

        self.setup_for_testcase_archival(overall_log)

        for container in self.containers:
            if len(self.containers) > 1:
                public_dir = os.path.join(
                    self.tmp_results,
                    "results_public",
                    self.name,
                    container.name
                )
                details_dir = os.path.join(self.tmp_results, "details", self.name, container.name)
                os.mkdir(public_dir)
                os.mkdir(details_dir)

    def execute_random_input(self, untrusted_user, executable, arguments, logfile, cwd):
        """ Generate random input for this container using its testcase specification. """

        container_spec = {
            'container_name': f'{untrusted_user}_temporary_container',
            'container_image': 'submitty/autograding-default:latest',
            'server': False,
            'outgoing_connections': []
        }
        # Create a container to generate random input inside of.
        container = Container(
            container_spec,
            untrusted_user,
            self.random_input_directory,
            False,
            self.is_test_environment,
            self.log_message,
            self.log_container_meta
        )
        execution_script = os.path.join(container.directory, executable)
        try:
            container.create(execution_script, arguments, False)
            container.start(logfile)
            container.process.wait()
        except Exception:
            self.log_message(
                'ERROR generating random input using docker. '
                'See stack trace output for more details.'
            )
            self.log_stack_trace(traceback.format_exc())
        finally:
            container.cleanup_container()

        return container.return_code

    def execute_random_output(self, untrusted_user, script, arguments, logfile, cwd=None):
        """
        Random output execution is analogous to execution, but with slightly different arguments
        and a different network of containers.
        """
        return self.execute_helper(self.solution_containers, script, arguments, logfile)

    def execute(self, untrusted_user, script, arguments, logfile, cwd=None):
        """ Run an execution step using our container network specification. """
        return self.execute_helper(self.containers, script, arguments, logfile)

    def execute_helper(self, containers, script, arguments, logfile):
        """ Create, Start, Monitor/Deliver input to a network of containers. """
        try:
            # Make certain we are executing in the environment in which we say we are
            # (i.e. test vs production environment).
            self.verify_execution_status()
        except Exception:
            self.log_stack_trace(traceback.format_exc())
            self.log_message("ERROR: Could not verify execution mode status.")
            return

        try:
            self.create_containers(containers, script, arguments)
            self.network_containers(containers)
        except Exception:
            self.log_message(
                'ERROR: Could not create or network containers. '
                'See stack trace output for more details.'
            )
            self.log_stack_trace(traceback.format_exc())
            return -1

        try:
            router = self.get_router(containers)
            # First start the router a second before any other container,
            # giving it time to initialize.
            if router is not None:
                router.start(logfile)
                time.sleep(2)
            # Next start any server containers, giving them time to initialize.
            for container in self.get_server_containers(containers):
                container.start(logfile)
            # Finally, start the standard (assignment) containers.
            for container in self.get_standard_containers(containers):
                container.start(logfile)

            # Deliver dispatcher actions.
            self.process_dispatcher_actions(containers)

        except Exception:
            self.log_message(
                'ERROR grading using docker. See stack trace output for more details.'
            )
            self.log_stack_trace(traceback.format_exc())
            return_code = -1

        try:
            # Clean up all containers. (Cleanup waits until they are finished)
            # Note: All containers should eventually terminate, as their executable will
            # kill them for time.
            for container in self.get_standard_containers(containers):
                container.cleanup_container(logfile)
            for container in self.get_server_containers(containers):
                container.cleanup_container(logfile)
            if router is not None:
                router.cleanup_container(logfile)
        except Exception:
            self.log_message(
                'ERROR cleaning up docker containers. See stack trace output for more details.'
            )
            self.log_stack_trace(traceback.format_exc())

        # Cleanup the all networks.
        try:
            self.cleanup_networks()
        except Exception:
            self.log_message(
                'ERROR cleaning up docker networks. See stack trace output for more details.'
            )
            self.log_stack_trace(traceback.format_exc())

        # A zero return code means execution went smoothly
        return_code = 0
        # Check the return codes of the standard (non server/router) containers
        # to see if they finished properly. Note that this return code is yielded by
        # main runner/validator/compiler. We return the first non-zero return code we encounter.
        for container in self.get_standard_containers(containers):
            if container.return_code != 0:
                return_code = container.return_code
                break
        return return_code
