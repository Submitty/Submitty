import json
import os
import subprocess
import traceback
import time
from pwd import getpwnam

from submitty_utils import dateutils
from . import secure_execution_environment
from .. import autograding_utils

class Container():
  # Container info should have name, image, server (true, false), and outgoing_connections
  def __init__(self, container_info, untrusted_user, testcase_directory, greater_than_one, is_test_environment, log_function):
    self.name = container_info['container_name']
    self.directory = os.path.join(testcase_directory, self.name) if greater_than_one else testcase_directory
    self.is_router = True if self.name == 'router' else False

    self.image = container_info['container_image']
    self.is_server = container_info['server']
    self.outgoing_connections = container_info['outgoing_connections']
    self.container_user_argument = list()  if is_test_environment else ['-u', str(getpwnam(untrusted_user).pw_uid)]
    self.full_name = f'{untrusted_user}_{self.name}'

    # This will be populated later
    self.container_id = None
    self.network = None
    self.process = None
    self.return_code = None
    self.log_function = log_function

    if self.name == 'router' and container_info.get('import_default_router', False):
      self.import_router = True
    else:
      self.import_router = False

  def create(self, execution_script, arguments, more_than_one):

    # Only pass container name to testcases with greater than one container. (Doing otherwise breaks compilation)
    conatiner_name_argument = ['--container_name', self.name] if more_than_one else list()
    if self.is_server:
      container_id = subprocess.check_output(['docker', 'create', '-i', '--network', 'none',
                                              '-v', f'{self.directory}:{self.directory}',
                                              '-w', self.directory,
                                              '--name', self.full_name,
                                              self.image
                                             ]).decode('utf8').strip()
    else:
      """'-u', self.untrusted_uid,"""
      this_container = subprocess.check_output(['docker', 'create', '-i', '--network', 'none']
                                                + self.container_user_argument + 
                                                ['-v', self.directory + ':' + self.directory,
                                                '-w', self.directory,
                                                '--hostname', self.name,
                                                '--name', self.full_name,
                                                self.image,
                                                execution_script]
                                                + arguments
                                                + conatiner_name_argument
                                              ).decode('utf8').strip()
    self.network = 'none'
    dockerlaunch_done = dateutils.get_current_time()
    self.log_function(f'docker container {this_container} created')
    self.container_id = this_container


  def start(self, logfile):
    asdf = ' '.join(['docker', 'start', '-i', '--attach', self.container_id])
    self.process = subprocess.Popen(['docker', 'start', '-i', '--attach', self.container_id], stdout=logfile,stdin=subprocess.PIPE)


  def cleanup(self):
    print('cleaning up')
    self.process.wait()
    self.return_code = self.process.returncode

    subprocess.call(['docker', 'rm', '-f', self.container_id])
    self.log_function(f'{dateutils.get_current_time()} docker container {self.container_id} destroyed')

    if self.network != 'none':
      try:
        subprocess.call(['docker', 'network', 'rm', self.network])
        self.log_function(f'{dateutils.get_current_time()} docker network {self.network} destroyed')
      except Exception as e:
        pass

class ContainerNetwork(secure_execution_environment.SecureExecutionEnvironment):
  def __init__(self, job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
               testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment):
    super().__init__(job_id, untrusted_user, testcase_directory, is_vcs, is_batch_job, complete_config_obj, 
                     testcase_info, autograding_directory, log_path, stack_trace_log_path, is_test_environment)

    containers = list()
    container_specs = testcase_info.get('containers', list())
    
    if len(container_specs) > 0:
      greater_than_one = True if len(container_specs) > 1 else False
      for container_spec in container_specs:
        containers.append(Container(container_spec, untrusted_user, os.path.join(self.tmp_work, testcase_directory), greater_than_one, self.is_test_environment, self.log_message))
    else:
      container_spec = {
        'container_name'  : f'{untrusted_user}_temporary_container',
        'container_image' : 'ubuntu:custom', 
        'server' : False,
        'outgoing_connections' : []
      }
      containers.append(Container(container_spec, untrusted_user, os.path.join(self.tmp_work, testcase_directory), False, self.is_test_environment, self.log_message))

    self.containers = containers
    self.dispatcher_actions = testcase_info.get('dispatcher_actions', list())

    self.single_port_per_container = testcase_info.get('single_port_per_container', False)


  ###########################################################
  #
  # Container Network Functions
  #
  ###########################################################

  def get_router(self):
    for container in self.containers:
      if container.name == 'router':
        return container 
    return None


  def get_server_containers(self):
    containers = list()
    for container in self.containers:
      if container.name != 'router' and container.is_server == True:
        containers.append(container)
    return containers


  def get_standard_containers(self):
    containers = list()
    for container in self.containers:
      if container.name != 'router' and container.is_server == False:
        containers.append(container)
    return containers


  def create_containers(self, script, arguments):

    try:
        self.verify_execution_status()
    except Exception as e:
      self.log_stack_trace(traceback.format_exc())
      self.log("ERROR: Could not verify execution mode status.")
      return

    more_than_one = True if len(self.containers) > 1 else False
    for container in self.containers:
      my_script = os.path.join(container.directory, script)
      container.create(my_script, arguments, more_than_one)

  
  def network_containers(self):
    if len(self.containers) <= 1:
      return

    #remove all containers from the none network
    for container in self.containers:
      subprocess.check_output(['docker', 'network','disconnect', 'none', container.container_id]).decode('utf8').strip()

    if self.get_router() is not None:
      self.network_containers_with_router()
    else:
      self.network_containers_routerless()

    self.create_knownhosts_txt()


  def network_containers_routerless(self):
    network_name = f'{self.untrusted_user}_routerless_network'

    #create the global network
    subprocess.check_output(['docker', 'network', 'create', '--internal', '--driver', 'bridge', network_name]).decode('utf8').strip()

    for container in self.containers:
        container.network = network_name
        subprocess.check_output(['docker', 'network', 'connect', '--alias', container.name, network_name, container.full_name]).decode('utf8').strip()


  def network_containers_with_router(self):
    #if there are multiple containers, create a router and a network. Otherwise, return.
    router_name = f"{self.untrusted_user}_router"
    router_connections = dict()

    #TODO SORT
    for container in self.containers:
      network_name = f"{container.full_name}_network"

      if container.full_name == 'router':
        continue

      container.network = network_name
      actual_name  = '{0}_Actual'.format(container.full_name)
      subprocess.check_output(['docker', 'network', 'create', '--internal', '--driver', 'bridge', container.network]).decode('utf8').strip()
      subprocess.check_output(['docker', 'network', 'connect', '--alias', actual_name, container.network, container.full_name]).decode('utf8').strip()

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
      network_name = f"{self.untrusted_user}_{startpoint}_network"
      print(network_name)

      aliases = []
      for endpoint in endpoints:
        if endpoint in aliases:
          continue
        aliases.append('--alias')
        aliases.append(endpoint)

      lis = ['docker', 'network', 'connect'] + aliases + [network_name, router_name]
      print(' '.join(lis))
      subprocess.check_output(['docker', 'network', 'connect'] + aliases + [network_name, router_name]).decode('utf8').strip()


  def create_knownhosts_txt(self):
    tcp_connection_list = list()
    udp_connection_list = list()
    current_tcp_port = 9000
    current_udp_port = 15000

    sorted_containers = sorted(self.containers, key=lambda x: x.name)
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
    networked_containers = self.get_standard_containers()
    router = self.get_router()

    if router is not None:
        networked_containers.append(router)

    sorted_networked_containers = sorted(networked_containers, key=lambda x: x.name)
    for container in sorted_networked_containers:
        knownhosts_location = os.path.join(container.directory, 'knownhosts_tcp.txt')
        with open(knownhosts_location, 'w') as outfile:
            print(f'WRITING KNOWNHOSTS {knownhosts_location}')
            print(tcp_connection_list)
            for tup in tcp_connection_list:
                outfile.write(" ".join(map(str, tup)) + '\n')
                outfile.flush()

        knownhosts_location = os.path.join(container.directory, 'knownhosts_udp.txt')
        with open(knownhosts_location, 'w') as outfile:
            for tup in udp_connection_list:
                outfile.write(" ".join(map(str, tup)) + '\n')
                outfile.flush()


  ###########################################################
  #
  # Dispatcher Functions
  #
  ###########################################################

  def process_dispatcher_actions(self):
    for action_obj in self.dispatcher_actions:
      action_type  = action_obj["action"]

      if action_type == "delay":
        time_to_delay = float(action_obj["seconds"])
        while time_to_delay > 0 and self.at_least_one_alive():
          if time_to_delay >= .1:
            time.sleep(.1)
          else:
            time.sleep(time_to_delay)
          # This can go negative (subtracts .1 even in the else case) but that's fine.
          time_to_delay -= .1
      elif action_type == "stdin":
        send_message_to_processes(action_obj["string"], action_obj["containers"])
      elif action_type in ['stop', 'start', 'kill']:
        send_message_to_processes(f"SUBMITTY_SIGNAL:{action_type.upper()}\n", action_obj['containers'])
      # A .1 second delay after each action to keep things flowing smoothly.
      time.sleep(.1)

  def get_container_with_name(self, name):
    for container in self.containers:
      if container.name == name:
        return container
    return None


  #targets must hold names/keys for the processes dictionary
  def send_message_to_processes(self, message, targets):
      for target in targets:
        container = get_container_with_name(target)
        # poll returns None if the process is still running.
        if container.process.poll() == None:
            container.process.stdin.write(message.encode('utf-8'))
            container.process.stdin.flush()
        else:
            pass


  def at_least_one_alive(self):
    for container in self.get_standard_containers():
      if container.process.poll() == None:
        return True
    return False


  def wait_until_standard_containers_finish(self):
    still_going = True
    while still_going:
      still_going = self.at_least_one_alive()
      time.sleep(.1)


  ###########################################################
  #
  # Overridden Functions
  #
  ###########################################################


  def setup_for_compilation_testcase(self):
    os.chdir(self.tmp_work)

    for container in self.containers:
      self._setup_single_directory_for_compilation(container.directory)
      # Run any necessary pre_commands
    self._run_pre_commands()


  def setup_for_execution_testcase(self, testcase_dependencies):
    os.chdir(self.tmp_work)
    for container in self.containers:
      self._setup_single_directory_for_execution(container.directory, testcase_dependencies)

      if container.import_default_router:
          if self.is_test_environment:
              self.log_message("ERROR: The default router should not be used in a test environment, please include a custom router.")
          else:
              router_path = os.path.join(self.SUBMITTY_INSTALL_DIR, "src", 'grading','python','submitty_router.py')
              self.log_message(f"COPYING:\n\t{router_path}\n\t{container.directory}")
              shutil.copy(router_path, container.directory)
    
    self._run_pre_commands()


  def setup_for_archival(self, overall_log):
    """
    Archive the results of an execution and validation.
    """
    self.setup_for_testcase_archival()
    test_input_path = os.path.join(self.tmp_autograding, 'test_input_path')
    
    for container in self.containers:
      if len(self.containers) > 1:
        public_dir = os.path.join(self.tmp_results,"results_public", self.name, container.name)
        details_dir = os.path.join(self.tmp_results, "details", self.name, container.name)
        os.mkdir(public_dir)
        os.mkdir(details_dir)
      # Remove any files that are also in the test output folder
      autograding_utils.remove_test_input_files(overall_log, test_input_path, container.directory)    


  def execute(self, untrusted_user, script, arguments, logfile, cwd=None):
    if cwd is None:
      cwd = self.directory
    self.create_containers(script, arguments)
    self.network_containers()

    router = self.get_router()
    # First start the router a second before any other container.
    if router is not None:
      router.start(logfile)
      time.sleep(1)

    # Next start any server containers
    for container in self.get_server_containers():
      container.start(logfile)

    # Finally, start the standard (assignment) containers
    for container in self.get_standard_containers():
      container.start(logfile)

    # Deliver dispatcher actions
    self.process_dispatcher_actions()

    # When we are done with the dispatcher actions, keep running until all
    # student process' finish.
    self.wait_until_standard_containers_finish()

    # Now stop any unfinished router/server containers, 
    # and cleanup after all containers.
    for container in self.containers:
      container.cleanup()

    # A zero return code means execution went smoothly
    return_code = 0
    # Check the return codes of the standard (non server/router) containers
    # to see if they finished properly. Note that this return code is yielded by
    # main runner/validator/compiler.
    for container in self.get_standard_containers():
      if container.return_code != 0:
        return_code = container.return_code
        break
    return return_code
