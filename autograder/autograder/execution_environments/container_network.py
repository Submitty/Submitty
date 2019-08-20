import json
import os
import subprocess
import traceback
import time
from pwd import getpwnam
import shutil
import docker

from submitty_utils import dateutils
from . import secure_execution_environment
from .. import autograding_utils

class Container():
  """
  Containers are the building blocks of a container network. Containers know how to
  create, start, and cleanup after themselves. Note that a network of containers
  can be made up of 1 or more containers.
  """
  def __init__(self, container_info, untrusted_user, testcase_directory, more_than_one, is_test_environment, log_function):
    self.name = container_info['container_name']

    # If there are multiple containers, each gets its own directory under testcase_directory, otherwise,
    # we can just use testcase_directory
    self.directory = os.path.join(testcase_directory, self.name) if more_than_one else testcase_directory
    # Check if we are the router in a container network
    self.is_router = True if self.name == 'router' else False

    self.image = container_info['container_image']
    self.is_server = container_info['server']
    self.outgoing_connections = container_info['outgoing_connections']
    # If we are in production, we need to run as an untrusted user inside of our docker container.
    self.container_user_argument = str(getpwnam(untrusted_user).pw_uid)
    self.full_name = f'{untrusted_user}_{self.name}'
    # This will be populated later
    self.return_code = None
    self.log_function = log_function
    self.container = None
    # A socket for communication with the container.
    self.socket = None

    # Determine whether or not we need to pull the default submitty router into this container's directory.
    need_router = container_info.get('import_default_router', False)
    if self.name == 'router' and need_router:
        self.import_router = True
    else:
        self.import_router = False


  def create(self, execution_script, arguments, more_than_one):
    """ Create (but don't start) this container. """

    client = docker.from_env()

    mount = {
        self.directory : {
          'bind' : self.directory,
          'mode' : 'rw'
        }
      }

    # Only pass container name to testcases with greater than one container. (Doing otherwise breaks compilation)
    container_name_argument = ['--container_name', self.name] if more_than_one else list()
    # A server container does not run student code, but instead hosts a service (e.g. a database.)
    if self.is_server:
      self.container = client.containers.create(self.image, stdin_open = True, tty = True, network = 'none',
                               volumes = mount, working_dir = self.directory, name = self.full_name)
    else:
      command = [execution_script,] + arguments + container_name_argument
      self.container = client.containers.create(self.image, command = command, stdin_open = True, tty = True,
                                                network = 'none', user = self.container_user_argument, volumes=mount,
                                                working_dir = self.directory, hostname = self.name, name = self.full_name)



    dockerlaunch_done = dateutils.get_current_time()
    self.log_function(f'docker container {self.container.short_id} created')


  def start(self, logfile):
    self.container.start()
    self.socket = self.container.attach_socket(params={'stdin': 1, 'stream': 1})


  def cleanup_container(self):
    """ Remove this container. """
    status = self.container.wait()
    self.return_code = status['StatusCode']

    self.container.remove(force=True)
    self.log_function(f'{dateutils.get_current_time()} docker container {self.container.short_id} destroyed')


class ContainerNetwork(secure_execution_environment.SecureExecutionEnvironment):
  """ 
  A Container Network ensures a secure execution environment by executing instances of student code
  within a secure Docker Container. To add an extra layer of security, files and directories are carefully
  permissioned, and code is executed as a limited-access, untrusted user. Therefore, code is effectively
  run in a Jailed Sandbox within the container. Containers may be networked together to test networked
  gradeables. 
  """
  def __init__(self, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
               testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment):
    super().__init__(job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
                     testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment)

    containers = list()
    container_specs = testcase_info.get('containers', list())
    solution_container_specs = testcase_info.get('solution_containers', list())
    
    # If there are container specifications in the complete_config, create objects for them,
    # else, create a single default container.
    if len(container_specs) > 0:
      greater_than_one = True if len(container_specs) > 1 else False
      for container_spec in container_specs:
        containers.append(Container(container_spec, untrusted_user, os.path.join(self.tmp_work, testcase_directory), greater_than_one, self.is_test_environment, self.log_message))
    else:
      container_spec = {
        'container_name'  : f'temporary_container',
        'container_image' : 'ubuntu:custom', 
        'server' : False,
        'outgoing_connections' : []
      }
      containers.append(Container(container_spec, untrusted_user, os.path.join(self.tmp_work, testcase_directory), False, self.is_test_environment, self.log_message))
    self.containers = containers

    # Solution containers are a network of containers which run instructor code.
    # We instantiate objects for them in a similar manner to the way that we instantiate
    # execution containers (above), but do not add a default container if they are not present.
    solution_containers = list()
    greater_than_one_solution_container = True if len(solution_container_specs) > 1 else False
    for solution_container_spec in solution_container_specs:
      solution_containers.append(Container(solution_container_spec, untrusted_user, self.random_output_directory, greater_than_one_solution_container, self.is_test_environment, self.log_message))
    self.solution_containers = solution_containers

    # Check for dispatcher actions (standard input)
    self.dispatcher_actions = testcase_info.get('dispatcher_actions', list())

    self.single_port_per_container = testcase_info.get('single_port_per_container', False)
    # As new container networks are generated, they will be appended to this list.
    self.networks = list()


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
    containers = list()
    for container in all_containers:
      if container.name != 'router' and container.is_server == True:
        containers.append(container)
    return containers


  def get_standard_containers(self, all_containers):
    """ Given a set of containers, return all non-router, non-server containers. """
    containers = list()
    for container in all_containers:
      if container.name != 'router' and container.is_server == False:
        containers.append(container)
    return containers


  def create_containers(self, containers, script, arguments):
    """ Given a set of containers, create each of them. """
    try:
        self.verify_execution_status()
    except Exception as e:
      self.log_stack_trace(traceback.format_exc())
      self.log("ERROR: Could not verify execution mode status.")
      return

    more_than_one = True if len(containers) > 1 else False
    for container in containers:
      my_script = os.path.join(container.directory, script)
      container.create(my_script, arguments, more_than_one)

  
  def network_containers(self, containers):
    """ Given a set of containers, network them per their specifications. """
    if len(containers) <= 1:
      return
    client = docker.from_env()

    none_network = client.networks.get('none')
    #remove all containers from the none network
    for container in containers:
      none_network.disconnect(container.container, force=True)

    if self.get_router(containers) is not None:
      self.network_containers_with_router(containers)
    else:
      self.network_containers_routerless(containers)

    # Provide an initialization file to each container.
    self.create_knownhosts_txt(containers)


  def network_containers_routerless(self, containers):
    """ If there is no router, all containers are added to the same network. """
    client = docker.from_env()
    network_name = f'{self.untrusted_user}_routerless_network'

    #create the global network
    network = client.networks.create(network_name, driver='bridge', internal=True)

    for container in containers:
      network.connect(container.container, aliases=[container.name,])

    self.networks.append(network)


  def network_containers_with_router(self, containers):
    """ 
    If there is a router, all containers are added to their own network, on which the only other
    endpoint is the router, which has been aliased to impersonate all other reachable endpoints.
    """
    client = docker.from_env()

    router = self.get_container_with_name('router', containers).container
    router_connections = dict()

    for container in containers:
      network_name = f"{container.full_name}_network"

      if container.name == 'router':
        continue


      actual_name  = '{0}_Actual'.format(container.name)
      network = client.networks.create(network_name, driver='bridge', internal=True)
      network.connect(container.container, aliases=[actual_name,])
      self.networks.append(network)

      #The router pretends to be all dockers on this network.
      aliases = []
      for connected_machine in container.outgoing_connections:
        if connected_machine == container.name:
            continue
        if not container.name in router_connections:
            router_connections[container.name] = list()
        if not connected_machine in router_connections:
            router_connections[connected_machine] = list()
        #The router must be in both endpoints' network, and must connect to all endpoints on a network simultaneously,
        #  so we group together all connections here, and then connect later.
        router_connections[container.name].append(connected_machine)
        router_connections[connected_machine].append(container.name)
    
    # Connect the router to all networks.
    for startpoint, endpoints in router_connections.items():
      full_startpoint_name = f'{self.untrusted_user}_{startpoint}'
      network_name = f"{full_startpoint_name}_network"

      aliases = []
      for endpoint in endpoints:
        if endpoint in aliases:
          continue
        aliases.append('--alias')
        aliases.append(endpoint)
      network = self.get_network_with_name(network_name)
      network.connect(router, aliases=aliases)

  def cleanup_networks(self):
    """ Destroy all created networks. """
    for network in self.networks:
      try:
        network.remove()
        self.log_message(f'{dateutils.get_current_time()} docker network {network} destroyed')
      except Exception as e:
        self.log_message(f'{dateutils.get_current_time()} ERROR: Could not remove docker network {network}')
    self.networks.clear()

  def create_knownhosts_txt(self, containers):
    """ 
    Given a set of containers, add initialization files to each 
    container's directory which specify how to connect to other endpoints
    on the container's network (hostname, port).
    """
    tcp_connection_list = list()
    udp_connection_list = list()
    current_tcp_port = 9000
    current_udp_port = 15000

    sorted_containers = sorted(containers, key=lambda x: x.name)
    for container in sorted_containers:
      if self.single_port_per_container:
        tcp_connection_list.append([container.name, current_tcp_port])
        udp_connection_list.append([container.name, current_udp_port])
        current_tcp_port += 1
        current_udp_port += 1  
      else:
        for connected_machine in container.outgoing_connections:
          if connected_machine == container.name:
              continue

          tcp_connection_list.append([container.name, connected_machine,  current_tcp_port])
          udp_connection_list.append([container.name, connected_machine,  current_udp_port])
          current_tcp_port += 1
          current_udp_port += 1

    #writing complete knownhosts csvs to input directory'
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
      action_type  = action_obj["action"]

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
        self.send_message_to_processes(containers, action_obj["string"], action_obj["containers"])
      elif action_type in ['stop', 'start', 'kill']:
        self.send_message_to_processes(containers, f"SUBMITTY_SIGNAL:{action_type.upper()}\n", action_obj['containers'])
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


  #targets must hold names/keys for the processes dictionary
  def send_message_to_processes(self, containers, message, targets):
    """ Given containers, targets, and a message, deliver the message to the target containers. """
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
    self._run_pre_commands(self.directory)


  def setup_for_execution_testcase(self, testcase_dependencies):
    """ For every container, set up its directory for execution. """
    os.chdir(self.tmp_work)
    for container in self.containers:
      self._setup_single_directory_for_execution(container.directory, testcase_dependencies)

      # Copy in the submitty_router if necessary.
      if container.import_router:
        router_path = os.path.join(self.tmp_autograding, "bin", "submitty_router.py")
        self.log_message(f"COPYING:\n\t{router_path}\n\t{container.directory}")
        shutil.copy(router_path, container.directory)
        autograding_utils.add_all_permissions(container.directory)
    self._run_pre_commands(self.directory)

  def setup_for_random_output(self, testcase_dependencies):
    """ For every container, set up its directory for random output generation. """
    os.chdir(self.tmp_work)
    for container in self.solution_containers:
      self._setup_single_directory_for_random_output(container.directory, testcase_dependencies)

      if container.import_router:
        router_path = os.path.join(self.tmp_autograding, "bin", "submitty_router.py")
        self.log_message(f"COPYING:\n\t{router_path}\n\t{container.directory}")
        shutil.copy(router_path, container.directory)
        autograding_utils.add_all_permissions(container.directory)
    
    self._run_pre_commands(self.random_output_directory)


  def setup_for_archival(self, overall_log):
    """ For every container, set up its directory for archival. """

    self.setup_for_testcase_archival(overall_log)
    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')
    
    for container in self.containers:
      if len(self.containers) > 1:
        public_dir = os.path.join(self.tmp_results,"results_public", self.name, container.name)
        details_dir = os.path.join(self.tmp_results, "details", self.name, container.name)
        os.mkdir(public_dir)
        os.mkdir(details_dir)

  def execute_random_input(self, untrusted_user, executable, arguments, logfile, cwd):
    """ Generate random input for this container using its testcase specification. """

    container_spec = {
        'container_name'  : f'{untrusted_user}_temporary_container',
        'container_image' : 'ubuntu:custom', 
        'server' : False,
        'outgoing_connections' : []
    }
    # Create a container to generate random input inside of.
    container = Container( container_spec, untrusted_user, self.random_input_directory, False, self.is_test_environment, self.log_message)
    execution_script = os.path.join(container.directory, executable)
    
    try:
      container.create(execution_script, arguments, False)
      container.start(logfile)
      container.process.wait()
    except Exception as e:
      self.log_message('ERROR generating random input using docker. See stack trace output for more details.')
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
      # (e.g. test vs production environment).
      self.verify_execution_status()
    except Exception as e:
      self.log_stack_trace(traceback.format_exc())
      self.log_message("ERROR: Could not verify execution mode status.")
      return

    try:
      self.create_containers( containers, script, arguments)
      self.network_containers(containers)
    except Exception as e:
      self.log_message('ERROR: Could not create or network containers. See stack trace output for more details.')
      self.log_stack_trace(traceback.format_exc())

    try:
      router = self.get_router(containers)
      # First start the router a second before any other container, giving it time to initialize.
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

    except Exception as e:
      self.log_message('ERROR grading using docker. See stack trace output for more details.')
      self.log_stack_trace(traceback.format_exc())
      return_code = -1

    try:
      # Clean up all containers. (Cleanup waits until they are finished)
      # Note: All containers should eventually terminate, as their executable will kill them for time.
      for container in containers:
        container.cleanup_container()
    except Exception as e:
      self.log_message('ERROR cleaning up docker containers. See stack trace output for more details.')
      self.log_stack_trace(traceback.format_exc())
    
    # Cleanup the all networks.
    try:
      self.cleanup_networks()
    except Exception as e:
      self.log_message('ERROR cleaning up docker networks. See stack trace output for more details.')
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
