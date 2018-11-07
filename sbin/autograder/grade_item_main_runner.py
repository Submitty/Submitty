import configparser
import json
import os
import tempfile
import shutil
import subprocess
import stat
import time
import dateutil
import dateutil.parser
import urllib.parse
import string
import random
import socket
import zipfile
import traceback
import csv
import json
from pwd import getpwnam

from submitty_utils import dateutils, glob
from . import grade_item, grade_items_logging, write_grade_history, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
    SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    DAEMON_UID = OPEN_JSON['daemon_uid']


def executeTestcases(complete_config_obj, tmp_logs, tmp_work, queue_obj, submission_string, item_name, USE_DOCKER, OBSOLETE_CONTAINER,
                      which_untrusted, job_id, grading_began):

    queue_time_longstring = queue_obj["queue_time"]
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    is_batch_job_string = "BATCH" if is_batch_job else "INTERACTIVE"
    runner_success = -1
    first_testcase = True
    # run the run.out as the untrusted user
    with open(os.path.join(tmp_logs,"runner_log.txt"), 'w') as logfile:
        print ("LOGGING BEGIN my_runner.out",file=logfile)
        logfile.flush()
        testcases = complete_config_obj["testcases"]

        # we start counting from one.
        for testcase_num in range(1, len(testcases)+1):
            if 'type' in testcases[testcase_num-1]:
              if testcases[testcase_num-1]['type'] == 'FileCheck' or testcases[testcase_num-1]['type'] == 'Compilation':
                continue
            #make the tmp folder for this testcase.
            testcase_folder = os.path.join(tmp_work, "test{:02}".format(testcase_num))
            os.makedirs(testcase_folder)

            os.chdir(testcase_folder)

            if USE_DOCKER:
                try:
                    use_router = testcases[testcase_num-1]['use_router']
                    single_port_per_container = testcases[testcase_num-1]['single_port_per_container']
                    # returns a dictionary where container_name maps to outgoing connections and container image
                    container_info = find_container_information(testcases[testcase_num -1], testcase_num, use_router,tmp_work)
                    # Creates folders for each docker container if there are more than one. Otherwise, we grade in testcase_folder.
                    # Updates container_info so that each docker has a 'mounted_directory' element
                    create_container_subfolders(container_info, testcase_folder, which_untrusted)
                    # Launches containers with the -d option. Gives them the names specified in container_info. Updates container info
                    #    to store container_ids.
                    launch_containers(container_info, testcase_folder, job_id,is_batch_job,which_untrusted,
                                         item_name,grading_began, queue_obj,submission_string,testcase_num)
                    # Networks containers together if there are more than one of them. Modifies container_info to store 'network'
                    #   The name of the docker network it is connected to.
                    network_containers(container_info,os.path.join(tmp_work, "test_input"),which_untrusted,use_router,single_port_per_container)
                    print('NETWORKED CONTAINERS')
                    #The containers are now ready to execute.

                    processes = dict()

                    #Set up the mounted folders before any dockers start running in case large file transfer sizes cause delay.
                    for name, info in container_info.items():
                        mounted_directory = info['mounted_directory']
                        #Copies the code needed to run into mounted_directory
                        #TODO this can eventually be extended so that only the needed code is copied in.
                        setup_folder_for_grading(mounted_directory, tmp_work, job_id, tmp_logs,testcases[testcase_num-1])


                    # Start the docker containers.
                    # Start the router first if in router mode
                    if 'router' in container_info and use_router:
                      info = container_info['router']
                      c_id = info['container_id']
                      mounted_directory = info['mounted_directory']
                      full_name = '{0}_{1}'.format(which_untrusted, 'router')
                      print('spinning up docker {0} with c_id {1}'.format(full_name, c_id))
                      p = subprocess.Popen(['docker', 'start', '-i', '--attach', c_id], stdout=logfile,stdin=subprocess.PIPE)
                      processes['router'] = p
                      time.sleep(1)

                    for name, info in container_info.items():
                        if name == 'router' and use_router:
                          continue
                        c_id = info['container_id']
                        mounted_directory = info['mounted_directory']
                        full_name = '{0}_{1}'.format(which_untrusted, name)
                        print('spinning up docker {0} with c_id {1}'.format(full_name, c_id))
                        p = subprocess.Popen(['docker', 'start', '-i', '--attach', c_id], stdout=logfile,stdin=subprocess.PIPE)
                        processes[name] = p
                    # Handle the dispatcher actions
                    dispatcher_actions = testcases[testcase_num -1]["dispatcher_actions"]

                    #if there are dispatcher actions, give the student code a second to start up.
                    if len(dispatcher_actions) > 0:
                      time.sleep(1)

                    #TODO add error handling once we've encountered some errors.
                    for action_obj in dispatcher_actions:
                      action_type  = action_obj["action"]

                      if action_type == "delay":
                          #todo add some protections here.
                          time_in_seconds = float(action_obj["seconds"])
                          while time_in_seconds > 0 and at_least_one_alive(processes):
                            if time_in_seconds >= .1:
                              time.sleep(.1)
                            else:
                              time.sleep(time_in_seconds)
                            #can go negative (subtracts .1 even in the else case) but that's fine.
                            time_in_seconds -= .1
                      elif action_type == "stdin":
                          string = action_obj["string"]
                          targets = action_obj["containers"]
                          for target in targets:
                              p = processes[target]
                              # poll returns None if the process is still running.
                              if p.poll() == None:
                                  p.stdin.write(string.encode('utf-8'))
                                  p.stdin.flush()
                              else:
                                  pass

                    #Now that all dockers are running, wait on their return code for success or failure. If any fail, we count it
                    #   as a total failure.
                    for name, process in processes.items():
                        process.wait()
                        rc = process.returncode
                        runner_success = rc if first_testcase else max(runner_success, rc)
                        first_testcase = False

                except Exception as e:
                    print('An error occurred when grading by docker.')
                    traceback.print_exc()
                finally:
                    clean_up_containers(container_info,job_id,is_batch_job,which_untrusted,item_name,grading_began,use_router)
                    print("CLEANED UP CONTAINERS")
            else:
                try:
                    # Move the files necessary for grading (runner, inputs, etc.) into the testcase folder.
                    setup_folder_for_grading(testcase_folder, tmp_work, job_id, tmp_logs,testcases[testcase_num-1])
                    my_testcase_runner = os.path.join(testcase_folder, 'my_runner.out')
                    runner_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                                      which_untrusted,
                                                      my_testcase_runner,
                                                      queue_obj["gradeable"],
                                                      queue_obj["who"],
                                                      str(queue_obj["version"]),
                                                      submission_string,
                                                      str(testcase_num)],
                                                      stdout=logfile)
                except Exception as e:
                    print ("ERROR caught runner.out exception={0}".format(str(e.args[0])).encode("utf-8"),file=logfile)
                    traceback.print_exc()
            logfile.flush()
            os.chdir(tmp_work)

        
        print ("LOGGING END my_runner.out",file=logfile)
        logfile.flush()

        killall_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                           which_untrusted,
                                           os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "killall.py")],
                                          stdout=logfile)

        print ("KILLALL COMPLETE my_runner.out",file=logfile)
        logfile.flush()

        if killall_success != 0:
            msg='RUNNER ERROR: had to kill {} process(es)'.format(killall_success)
            print ("pid",os.getpid(),msg)
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"","",msg)
    return runner_success


def at_least_one_alive(processes):
  for name, process in processes.items():
    if process.poll() == None:
      return True
  return False


def setup_folder_for_grading(target_folder, tmp_work, job_id, tmp_logs, testcase):
    #The paths to the important folders.
    tmp_work_test_input = os.path.join(tmp_work, "test_input")
    tmp_work_submission = os.path.join(tmp_work, "submitted_files")
    tmp_work_compiled = os.path.join(tmp_work, "compiled_files")
    tmp_work_checkout = os.path.join(tmp_work, "checkout")
    my_runner = os.path.join(tmp_work,"my_runner.out")

    #######################################################################################
    #
    # PRE-COMMANDS
    #
    ######################################################################################

    pre_commands = testcase.get('pre_commands', [])

    for pre_command in pre_commands:
      command = pre_command['command']
      option  = pre_command['option']
      source_testcase   = pre_command["testcase"]
      source_directory  = pre_command['source']
      source = os.path.join(source_testcase,source_directory)
      destination = pre_command['destination']
      # pattern is not currently in use.
      #pattern    = pre_command['pattern']

      if command == 'cp':
        #currently ignoring option
        if not os.path.isdir(os.path.join(tmp_work,source)):
          try:
            shutil.copy(os.path.join(tmp_work,source),os.path.join(target_folder,destination))
          except Exception as e:
            print("encountered a copy error")
            traceback.print_exc()
            #TODO: can we pass something useful to students?
            pass
        else:
          try:
            grade_item.copy_contents_into(job_id,os.path.join(tmp_work,source),os.path.join(target_folder,destination),tmp_logs)
          except Exception as e:
            print("encountered a copy error")
            traceback.print_exc()
            #TODO: can we pass something useful to students?
            pass
      else:
        print("Invalid pre-command '{0}'".format(command))

    #TODO: pre-commands may eventually wipe the following logic out.
    #copy the required files to the test directory
    grade_item.copy_contents_into(job_id,tmp_work_submission ,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_compiled  ,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_checkout  ,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_test_input,target_folder,tmp_logs)
    #copy the compiled runner to the test directory
    shutil.copy(my_runner,target_folder)

    grade_item.add_permissions_recursive(target_folder,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH,
                                      stat.S_IRUSR | stat.S_IWUSR | stat.S_IXUSR | stat.S_IRGRP | stat.S_IWGRP | stat.S_IXGRP | stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)

#docker information is of the form:
# {
#   'name' : {
#     'image' : DOCKER IMAGE,
#     'outgoing_connections' : [OTHER DOCKER NAMES],
#   }
# }
def find_container_information(testcase, testcase_num, use_router, tmp_work):
  if not 'containers' in testcase:
    raise SystemExit("Error, this container's testcase {0} is missing the 'containers' field".format(testcase_num))

  container_info = {}
  instructor_container_specification = testcase['containers']

  insert_default = False
  num = 0
  for container_spec in instructor_container_specification:
    # Get the name, image, and outgoing_connections out of the instructor specification, filling in defaults if necessary.
    # Container name will always be set, and is populated by the complete config if not specified by the instructor
    container_name  = container_spec['container_name']

    if container_name == "router":
      insert_default = container_spec.get('import_default_router', False)

    container_image = container_spec['container_image']
    outgoing_conns  = container_spec['outgoing_connections']

    container_info_element = create_container_info_element(container_image, outgoing_conns)
    container_info[container_name] = container_info_element
    num += 1

  if use_router:
    #backwards compatibility
    if 'router' not in container_info:
      insert_default_router(tmp_work)
      container_info['router'] = container_info_element("ubuntu:custom", [])

    if insert_default:
      print("Inserting default router")
      insert_default_router(tmp_work)

  return container_info

#Create an element to add to the container_information dictionary
def create_container_info_element(container_image, outgoing_connections, container_id=''):
  element = {'outgoing_connections' : outgoing_connections, 'container_image' : container_image}
  if container_id != '':
    element['container_id'] = container_id
  return element

def insert_default_router(tmp_work):
  tmp_work_test_input = os.path.join(tmp_work, "test_input")
  router_path = os.path.join(SUBMITTY_INSTALL_DIR, "src", 'grading','python','submitty_router.py')
  print("COPYING:\n\t{0}\n\t{1}".format(router_path, tmp_work_test_input))
  shutil.copy(router_path, tmp_work_test_input)


#Create the subdirectories needed for the containers and specify permissions.
def create_container_subfolders(container_info, target_folder, which_untrusted):

  for name, info in container_info.items():
      mounted_directory = os.path.join(target_folder, name) if len(container_info) > 1 else target_folder
      if mounted_directory != target_folder:
        os.makedirs(mounted_directory)
      grade_item.untrusted_grant_rwx_access(which_untrusted, mounted_directory)
      container_info[name]['mounted_directory'] = mounted_directory

def launch_containers(container_info, target_folder, job_id,is_batch_job,which_untrusted,submission_path,grading_began,
                                                                                queue_obj,submission_string,testcase_num):
  #We must construct every container requested in container_info.
  for name in container_info:
    #Grading happens in target_folder if there is only one docker. Otherwise, it happens in target_docker/container_name
    if len(container_info) == 1:
      mounted_directory = target_folder
    else:
      mounted_directory = os.path.join(target_folder, name)

    #container_name is used only at the system level, and is used to ensure uniqueness.
    container_name = "{0}_{1}".format(which_untrusted, name)
    container_image = container_info[name]['container_image']
    # Launch the requeseted container
    container_id = launch_container(container_name, container_image, mounted_directory, job_id, is_batch_job, which_untrusted,
                                    submission_path,grading_began,queue_obj,submission_string,testcase_num,name)
    #add the container_id to the container info for use later.
    container_info[name]['container_id'] = container_id

#Launch a single docker.
def launch_container(container_name, container_image, mounted_directory,job_id,is_batch_job,which_untrusted,submission_path,
                                                                  grading_began,queue_obj,submission_string,testcase_num,name):
  #TODO error handling.
  untrusted_uid = str(getpwnam(which_untrusted).pw_uid)
  this_container = subprocess.check_output(['docker', 'create', '-i', '-u', untrusted_uid, '--network', 'none',
                                           '-v', mounted_directory + ':' + mounted_directory,
                                           '-w', mounted_directory,
                                           '--name', container_name,
                                           container_image,
                                           #The command to be run.
                                           os.path.join(mounted_directory, 'my_runner.out'),
                                             queue_obj['gradeable'],
                                             queue_obj['who'],
                                             str(queue_obj['version']),
                                             submission_string,
                                             str(testcase_num),
                                             name
                                           ]).decode('utf8').strip()

  dockerlaunch_done =dateutils.get_current_time()
  dockerlaunch_time = (dockerlaunch_done-grading_began).total_seconds()
  grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"dcct:",dockerlaunch_time,
                                  "docker container {0} created".format(this_container))
  return this_container



def network_containers(container_info,test_input_folder,which_untrusted, use_router,single_port_per_container):
  if len(container_info) <= 1:
    return

  #remove all containers from the none network
  for name, info in sorted(container_info.items()):
    container_id = info['container_id']
    subprocess.check_output(['docker', 'network','disconnect', 'none', container_id]).decode('utf8').strip()


  if use_router:
    network_containers_with_router(container_info,which_untrusted)
  else:
    network_containers_routerless(container_info,which_untrusted)

  create_knownhosts_txt(container_info,test_input_folder,single_port_per_container)

def network_containers_routerless(container_info,which_untrusted):
  network_name = '{0}_routerless_network'.format(which_untrusted)

  #create the global network
  subprocess.check_output(['docker', 'network', 'create', '--internal', '--driver', 'bridge', network_name]).decode('utf8').strip()

  for name, info in sorted(container_info.items()):
      my_full_name = "{0}_{1}".format(which_untrusted, name)
      #container_info[name]['network'] = network_name
      print('adding {0} to network {1}'.format(name,network_name))
      subprocess.check_output(['docker', 'network', 'connect', '--alias', name, network_name, my_full_name]).decode('utf8').strip()

#Connect dockers in a network, updates the container_info with network names, and creates knownhosts.csv
def network_containers_with_router(container_info,which_untrusted):
  #if there are multiple containers, create a router and a network. Otherwise, return.
  router_name = "{0}_router".format(which_untrusted)
  router_connections = {}

  for name, info in sorted(container_info.items()):
      my_name = "{0}_{1}".format(which_untrusted, name)
      network_name = "{0}_network".format(my_name)

      if name == 'router':
        continue

      container_info[name]['network'] = network_name
      actual_name  = '{0}_Actual'.format(name)
      print('adding {0} to network {1} with actual name {2}'.format(name,network_name, actual_name))
      subprocess.check_output(['docker', 'network', 'create', '--internal', '--driver', 'bridge', network_name]).decode('utf8').strip()
      subprocess.check_output(['docker', 'network', 'connect', '--alias', actual_name, network_name, my_name]).decode('utf8').strip()

      #The router pretends to be all dockers on this network.
      aliases = []
      for connected_machine in info['outgoing_connections']:
          if connected_machine == name:
              continue
          if not name in router_connections:
              router_connections[name] = list()
          if not connected_machine in router_connections:
              router_connections[connected_machine] = list()
          #The router must be in both endpoints' network, and must connect to all endpoints on a network simultaneously,
          #  so we group together all connections here, and then connect later.
          router_connections[name].append(connected_machine)
          router_connections[connected_machine].append(name)
  
  # Connect the router to all networks.
  for startpoint, endpoints in router_connections.items():
      full_startpoint_name = "{0}_{1}".format(which_untrusted, startpoint)
      network_name = "{0}_network".format(full_startpoint_name)

      aliases = []
      for endpoint in endpoints:
          if endpoint in aliases:
            continue
          aliases.append('--alias')
          aliases.append(endpoint)

      print('adding router to {0} with aliases {1}'.format(network_name, aliases))
      subprocess.check_output(['docker', 'network', 'connect'] + aliases + [network_name, router_name]).decode('utf8').strip()



def create_knownhosts_txt(container_info,test_input_folder,single_port_per_container):
  tcp_connection_list = list()
  udp_connection_list = list()
  current_tcp_port = 9000
  current_udp_port = 15000

  for name, info in sorted(container_info.items()):
      if single_port_per_container:
          tcp_connection_list.append([name, current_tcp_port])
          udp_connection_list.append([name, current_udp_port])
          current_tcp_port += 1
          current_udp_port += 1  
      else:
          for connected_machine in info['outgoing_connections']:
              if connected_machine == name:
                  continue

              tcp_connection_list.append([name, connected_machine,  current_tcp_port])
              udp_connection_list.append([name, connected_machine,  current_udp_port])
              current_tcp_port += 1
              current_udp_port += 1

  #writing complete knownhosts csvs to input directory
  knownhosts_location = os.path.join(test_input_folder, 'knownhosts_tcp.txt')
  with open(knownhosts_location, 'w') as outfile:
    for tup in tcp_connection_list:
      outfile.write(" ".join(map(str, tup)) + '\n')
      outfile.flush()

  knownhosts_location = os.path.join(test_input_folder, 'knownhosts_udp.txt')
  with open(knownhosts_location, 'w') as outfile:
    for tup in udp_connection_list:
      outfile.write(" ".join(map(str, tup)) + '\n')
      outfile.flush()


def clean_up_containers(container_info,job_id,is_batch_job,which_untrusted,submission_path,grading_began,use_router):
    # First, clean up the dockers.
    for name, info in container_info.items():
        c_id = info['container_id']
        subprocess.call(['docker', 'rm', '-f', c_id])

        dockerdestroy_done=dateutils.get_current_time()
        dockerdestroy_time = (dockerdestroy_done-grading_began).total_seconds()
        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"ddt:",
                                        dockerdestroy_time, "docker container {0} destroyed".format(name))

    if not use_router:
      network_name = '{0}_routerless_network'.format(which_untrusted)
      subprocess.call(['docker', 'network', 'rm', network_name])
      network_destroy_done=dateutils.get_current_time()
      network_destroy_time = (network_destroy_done-grading_began).total_seconds()
      grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"ddt:",
                                      network_destroy_time,"docker network {0} destroyed".format(network_name))
    else:
      #Networks must be removed AFTER all docker endpoints have been shut down.
      for name, info in container_info.items():
          if 'network' in info:
              network_name = info['network']
              subprocess.call(['docker', 'network', 'rm', network_name])
              network_destroy_done=dateutils.get_current_time()
              network_destroy_time = (network_destroy_done-grading_began).total_seconds()
              grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"ddt:",
                                              network_destroy_time,"docker network {0} destroyed".format(network_name))

