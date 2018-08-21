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
                    # returns a dictionary where container_name maps to outgoing connections and container image
                    container_info = find_container_information(testcases[testcase_num -1], testcase_num)
                    # Creates folders for each docker container if there are more than one. Otherwise, we grade in testcase_folder.
                    # Updates container_info so that each docker has a 'mounted_directory' element
                    create_container_subfolders(container_info, testcase_folder, which_untrusted)
                    # Launches containers with the -d option. Gives them the names specified in container_info. Updates container info
                    #    to store container_ids.
                    launch_containers(container_info, testcase_folder, job_id,is_batch_job,which_untrusted,
                                                      item_name,grading_began)
                    # Networks containers together if there are more than one of them. Modifies container_info to store 'network'
                    #   The name of the docker network it is connected to.
                    network_containers(container_info,testcase_folder,os.path.join(tmp_work, "test_input"),job_id,is_batch_job,
                                        which_untrusted,item_name,grading_began)
                    print('NETWORKED CONTAINERS')
                    #The containers are now ready to execute.

                    processes = list()

                    #Set up the mounted folders before any dockers start running in case large file transfer sizes cause delay.
                    for name, info in container_info.items():
                        mounted_directory = info['mounted_directory']
                        #Copies the code needed to run into mounted_directory
                        #TODO this can eventually be extended so that only the needed code is copied in.
                        setup_folder_for_grading(mounted_directory, tmp_work, job_id, tmp_logs)


                    #TODO: START THE ROUTER FIRST, THEN GIVE IT A SECOND
                    if 'router' in container_info:
                      name = 'router'
                      info = container_info['router']
                      c_id = info['container_id']
                      mounted_directory = info['mounted_directory']
                      full_name = '{0}_{1}'.format(which_untrusted, 'router')
                      print('spinning up docker {0} with root directory {1} and c_id {2}'.format(full_name, mounted_directory, c_id))
                      p = subprocess.Popen(['docker', 'exec', '-w', mounted_directory,
                                                        c_id,
                                                        os.path.join(mounted_directory, 'my_runner.out'),
                                                        queue_obj['gradeable'],
                                                        queue_obj['who'],
                                                        str(queue_obj['version']),
                                                        submission_string,
                                                        str(testcase_num),
                                                        name],
                                                        stdout=logfile)
                      processes.append(p)
                    time.sleep(1)
                    for name, info in container_info.items():
                        if name == 'router':
                          continue
                        c_id = info['container_id']
                        mounted_directory = info['mounted_directory']
                        full_name = '{0}_{1}'.format(which_untrusted, name)
                        print('spinning up docker {0} with root directory {1} and c_id {2}'.format(full_name, mounted_directory, c_id))
                        #TODO: is it possible to synchronize execution to a greater extent?
                        p = subprocess.Popen(['docker', 'exec', '-w', mounted_directory,
                                                          c_id,
                                                          os.path.join(mounted_directory, 'my_runner.out'),
                                                          queue_obj['gradeable'],
                                                          queue_obj['who'],
                                                          str(queue_obj['version']),
                                                          submission_string,
                                                          str(testcase_num),
                                                          name],
                                                          stdout=logfile)
                        processes.append(p)
                    #Now that all dockers are running, wait on their return code for success or failure. If any fail, we count it
                    #   as a total failure.
                    for process in processes:
                        process.wait()
                        rc = process.returncode
                        runner_success = rc if first_testcase else max(runner_success, rc)
                        first_testcase = False

                except Exception as e:
                    print('An error occurred when grading by docker.')
                    traceback.print_exc()
                finally:
                    clean_up_containers(container_info,job_id,is_batch_job,which_untrusted,item_name,grading_began)
                    print("CLEANED UP CONTAINERS")
            else:
                try:
                    # Move the files necessary for grading (runner, inputs, etc.) into the testcase folder.
                    setup_folder_for_grading(testcase_folder, tmp_work, job_id, tmp_logs)
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

def setup_folder_for_grading(target_folder, tmp_work, job_id, tmp_logs):
    #The paths to the important folders.
    tmp_work_test_input = os.path.join(tmp_work, "test_input")
    tmp_work_subission = os.path.join(tmp_work, "submitted_files")
    tmp_work_compiled = os.path.join(tmp_work, "compiled_files")
    tmp_work_checkout = os.path.join(tmp_work, "checkout")
    my_runner = os.path.join(tmp_work,"my_runner.out")

    #copy the required files to the test directory
    grade_item.copy_contents_into(job_id,tmp_work_test_input,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_subission ,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_compiled  ,target_folder,tmp_logs)
    grade_item.copy_contents_into(job_id,tmp_work_checkout  ,target_folder,tmp_logs)
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
def find_container_information(testcase, testcase_num):
  if not 'containers' in testcase:
    raise SystemExit("Error, this container's testcase {0} is missing the 'containers' field".format(testcase_num))

  container_info = {}
  instructor_container_specification = testcase['containers']

  num = 0
  for container_spec in instructor_container_specification:
    # Get the name, image, and outgoing_connections out of the instructor specification, filling in defaults if necessary.
    # Container name will always be set, and is populated by the complete config if not specified by the instructor
    container_name  = container_spec['container_name']
    #TODO the default container image should eventually be stored somewhere rather than hardcoded.
    #container_image = container_spec['container_image'] if container_spec['container_image'] != '' else "ubuntu:custom"
    #outgoing_conns  = container_spec['outgoing_connections']

    #TODO temp version until auto-population is implemented
    if 'container_image' in container_spec and container_spec['container_image'] != '':
      container_image = container_spec['container_image']
    else:
      container_image = 'ubuntu:custom'

    if 'outgoing_connections' in container_spec:
      outgoing_conns = container_spec['outgoing_connections']
    else:
      outgoing_conns = []


    container_info_element = create_container_info_element(container_image, outgoing_conns)
    container_info[container_name] = container_info_element
    num += 1

  if len(container_info) > 1 and 'router' not in container_info:
    container_info['router'] = container_info_element("ubuntu:custom", [])

  return container_info


#Create an element to add to the container_information dictionary
def create_container_info_element(container_image, outgoing_connections, container_id=''):
  element = {'outgoing_connections' : outgoing_connections, 'container_image' : container_image}
  if container_id != '':
    element['container_id'] = container_id
  return element

#Create the subdirectories needed for the containers and specify permissions.
def create_container_subfolders(container_info, target_folder, which_untrusted):

  for name, info in container_info.items():
      mounted_directory = os.path.join(target_folder, name) if len(container_info) > 1 else target_folder
      if mounted_directory != target_folder:
        os.makedirs(mounted_directory)
      grade_item.untrusted_grant_rwx_access(which_untrusted, mounted_directory)
      container_info[name]['mounted_directory'] = mounted_directory

#dockers will either be a list of docker names or None.
#If dockers is a list of docker names, spin up a docker for each name.
#If dockers is none, create default docker names.
def launch_containers(container_info, target_folder, job_id,is_batch_job,which_untrusted,submission_path,grading_began):
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
                                    submission_path,grading_began)
    #add the container_id to the container info for use later.
    container_info[name]['container_id'] = container_id

#Launch a single docker.
def launch_container(container_name, container_image, mounted_directory,job_id,is_batch_job,which_untrusted,submission_path,grading_began):
  #TODO error handling.
  this_container = subprocess.check_output(['docker', 'run', '-t', '-d','-v', mounted_directory + ':' + mounted_directory,
                                           '--name', container_name,
                                           container_image]).decode('utf8').strip()
  dockerlaunch_done =dateutils.get_current_time()
  dockerlaunch_time = (dockerlaunch_done-grading_began).total_seconds()
  grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"dcct:",dockerlaunch_time,
                                  "docker container {0} created".format(this_container))
  return this_container


#Connect dockers in a network, updates the container_info with network names, and creates knownhosts.csv
def network_containers(container_info,target_folder,test_input_folder,job_id,is_batch_job,which_untrusted,submission_path,grading_began):
  #if there are multiple containers, create a router and a network. Otherwise, return. 
  if len(container_info) <= 1:
    return

  router_name = "{0}_router".format(which_untrusted)

  #TODO this is where we create the connections text file.
  current_port = 9000
  connection_list = []
  router_connections = {}
  for name, info in sorted(container_info.items()):
      my_name = "{0}_{1}".format(which_untrusted, name)
      network_name = "{0}_network".format(my_name)
      #pass in docker names by environment variables -e DOCKER_NAME={0}
      if name == 'router':
        continue
      if len('outgoing_connections') == 0:
        continue

      container_info[name]['network'] = network_name
      actual_name  = '{0}_Actual'.format(name)
      print('adding {0} to network {1} with actual name {2}'.format(name,network_name, actual_name))
      subprocess.check_output(['docker', 'network', 'create', '--driver', 'bridge', network_name]).decode('utf8').strip()
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
          connection_list.append([name, connected_machine, str(current_port)])
          current_port +=1
  
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


  #writing complete knownhosts csv to input directory
  knownhosts_location = os.path.join(test_input_folder, 'knownhosts.csv')
  with open(knownhosts_location, 'w') as csvfile:
    csvwriter = csv.writer(csvfile)
    for tup in connection_list:
      csvwriter.writerow(tup)



def clean_up_containers(container_info,job_id,is_batch_job,which_untrusted,submission_path,grading_began):
    # First, clean up the dockers.
    for name, info in container_info.items():
        c_id = info['container_id']
        subprocess.call(['docker', 'rm', '-f', c_id])

        dockerdestroy_done=dateutils.get_current_time()
        dockerdestroy_time = (dockerdestroy_done-grading_began).total_seconds()
        grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"ddt:",
                                        dockerdestroy_time, "docker container {0} destroyed".format(name))
    #Networks must be removed AFTER all docker endpoints have been shut down.
    for name, info in container_info.items():
        if 'network' in info:
            network_name = info['network']
            subprocess.call(['docker', 'network', 'rm', network_name])
            network_destroy_done=dateutils.get_current_time()
            network_destroy_time = (network_destroy_done-grading_began).total_seconds()
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,submission_path,"ddt:",
                                            network_destroy_time,"docker network {0} destroyed".format(network_name))

