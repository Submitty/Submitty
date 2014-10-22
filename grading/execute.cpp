/* FILENAME: runner.cpp
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   Chris Berger
 *   Jesse Freitas
 *   Severin Ibarluzea
 *   Kiana McNellis
 *   Kienan Knight-Boehm
 *   Sam Seng
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 *
*/

#include <sys/time.h>
#include <sys/resource.h>
#include <sys/wait.h>
#include <signal.h>
#include <unistd.h>
#include <fcntl.h>

#include <stdlib.h>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>

#include "modules/modules.h"
#include "grading/TestCase.h"

// ----------------------------------------------------------------

#define MAX_STRING_LENGTH 100
#define MAX_NUM_STRINGS 20
#define DIR_PATH_MAX 1000

#include <dirent.h>

bool wildcard_match(const std::string &pattern, const std::string &thing) {
  int wildcard_loc = pattern.find("*");
  assert (wildcard_loc != std::string::npos);

  std::string before = pattern.substr(0,wildcard_loc);
  std::string after = pattern.substr(wildcard_loc+1,pattern.size()-wildcard_loc-1);

  //  std::cout << "BEFORE " << before << " AFTER" << after << std::endl;

  // FIXME: we only handle a single wildcard!
  assert (after.find("*") == std::string::npos);
  assert (before.find("*") == std::string::npos);

  if (thing.size() < after.size()+before.size()) return false;

  std::string thing_before = thing.substr(0,wildcard_loc);
  std::string thing_after = thing.substr(thing.size()-after.size(),after.size());

  //  std::cout << "THINGBEFORE " << thing_before << " THINGAFTER" << thing_after << std::endl;

  if (before == thing_before && after == thing_after)
    return true;

  return false;
}



void wildcard_expansion(std::vector<std::string> &my_args, const std::string &pattern) {
  if (pattern.find("*") != std::string::npos) {
    std::cout << "WILDCARD DETECTED:" << pattern << std::endl;
    char buf[DIR_PATH_MAX]; 
    getcwd( buf, DIR_PATH_MAX ); 
    DIR* dir = opendir(buf);
    assert (dir != NULL);
    struct dirent *ent;
    while (1) {
      ent = readdir(dir);
      if (ent == NULL) break;
      std::string thing = ent->d_name;
      if (wildcard_match(pattern,thing)) {
	my_args.push_back(thing);
	std::cout << "   MATCHED! " << thing << std::endl;
      }
    }
    closedir(dir);
  } else {
    my_args.push_back(pattern);
  }
}


// This function only returns on failure to exec
int exec_this_command(const std::string &cmd) {

  // to avoid creating extra layers of processes, use exec and not
  // system or the shell

  // first we need to parse the command line
  std::string my_program;
  std::vector<std::string> my_args;
  std::string my_stdin;
  std::string my_stdout;
  std::string my_stderr;

  // FIXME: command line parsing is a bit fragile.  Could be made more robust
  std::stringstream ss(cmd);
  std::string tmp;
  while (ss >> tmp) {
    assert (tmp.size() >= 1);
    if (my_program == "") {
      // program name
      my_program = tmp;
    }
    else if (tmp.size() >= 1 && tmp.substr(0,1) == "<") {
      // stdin
      assert (tmp.size() > 1);
      my_stdin = tmp.substr(1,tmp.size()-1);
    }
    else if (tmp.size() >= 2 && tmp.substr(0,2) == "1>") {
      // stdout
      assert (tmp.size() > 2);
      my_stdout = tmp.substr(2,tmp.size()-2);
    }
    else if (tmp.size() >= 2 && tmp.substr(0,2) == "2>") {
      // stderr
      my_stderr = tmp.substr(2,tmp.size()-2);
    }
    else {
      wildcard_expansion(my_args,tmp);
    }
  }

  /*
  // FOR DEBUGGING
  std::cout << std::endl << std::endl;
  std::cout << "MY PROGRAM: '" << my_program << "'" << std::endl;
  std::cout << "MY STDIN:  '" << my_stdin  << "'" << std::endl;
  std::cout << "MY STDOUT:  '" << my_stdout  << "'" << std::endl;
  std::cout << "MY STDERR:  '" << my_stderr  << "'" << std::endl;
  std::cout << "MY ARGS (" << my_args.size() << ") :";
  */

  char** const my_char_args = new char * [my_args.size()+2];  // yes, there is a memory leak here
  my_char_args[0] = (char*) my_program.c_str();
  for (int i = 0; i < my_args.size(); i++) {
    std::cout << "'" << my_args[i] << "' ";
    my_char_args[i+1] = (char*) my_args[i].c_str();
  }
  my_char_args[my_args.size()+1] = (char *)NULL;  // FIXME: casting away the const :(
  std::cout << std::endl << std::endl;


  // FIXME: if we want to assert or print stuff afterward, we should save
  // the originals and restore after the exec fails.


  std::cout << "going to exec: ";
  for (int i = 0; i < my_args.size()+1; i++) {
    std::cout << my_char_args[i] << " ";
  }
  std::cout << std::endl;


  /*
  char* path = getenv("PATH");
  std::string stringpath = (path==NULL) ? "" : std::string(path);
  std::cout << "my path = " << stringpath << std::endl;
  setenv("PATH","/usr/bin/",1);

  path = getenv("PATH");
  stringpath = (path==NULL) ? "" : std::string(path);
  std::cout << "my path edited = " << stringpath << std::endl;
  */

  if (my_stdin != "") {
    int new_stdinfd  = open(my_stdin.c_str()  , O_RDONLY );
    int stdinfd = fileno(stdin);
    close(stdinfd);
    dup2(new_stdinfd, stdinfd);
  }
  if (my_stdout != "") {
    int new_stdoutfd = creat(my_stdout.c_str(), S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH );
    int stdoutfd = fileno(stdout);
    close(stdoutfd);
    dup2(new_stdoutfd, stdoutfd);
  }
  if (my_stderr != "") {
    int new_stderrfd = creat(my_stderr.c_str(), S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH );
    int stderrfd = fileno(stderr);
    close(stderrfd);
    dup2(new_stderrfd, stderrfd);
  }

  int child_result =  execv ( my_program.c_str(), my_char_args );
  // if exec does not fail, we'll never get here

  return child_result;
}


// Executes command (from shell) and returns error code (0 = success)
int execute(const std::string &cmd, int seconds_to_run, int file_size_limit) {
  std::cout << "IN EXECUTE:  '" << cmd << "'" << std::endl;

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);

  if (childPID == 0) {
    // CHILD PROCESS

    int set_success;

    // limit CPU time (unfortunately this is *not* wall clock time)
    rlimit time_limit;
    time_limit.rlim_cur = time_limit.rlim_max = seconds_to_run*2;
    set_success = setrlimit(RLIMIT_CPU, &time_limit);
    assert (set_success == 0);


    // FIXME read in the file size from the configuration
    // limit size of files created by the process
    rlimit fsize_limit;
    fsize_limit.rlim_cur = fsize_limit.rlim_max = file_size_limit; //100000;  // 100 kilobytes
    set_success = setrlimit(RLIMIT_FSIZE, &fsize_limit);
    assert (set_success == 0);


    // Student's shouldn't be forking & making threads/processes...
    // but if they do, let's set them in the same process group
    int pgrp = setpgid(getpid(), 0);
    assert(pgrp == 0);

    int child_result;
    child_result = exec_this_command(cmd);

    // send the system status code back to the parent process
    //std::cout << "    child_result = " << child_result << std::endl;
    exit(child_result);

  } else {
    // PARENT PROCESS
    std::cout << "childPID = " << childPID << std::endl;
    std::cout << "PARENT PROCESS START: " << std::endl;
    int parent_result = system("date");
    assert (parent_result == 0);
    //std::cout << "  parent_result = " << parent_result << std::endl;

    float elapsed = 0;
    int status;
    pid_t wpid = 0;
    do {
      wpid = waitpid(childPID, &status, WNOHANG);
      if (wpid == 0) {
	if (elapsed < seconds_to_run) {
	  // sleep 1/10 of a second
	  usleep(100000);
	  elapsed+= 0.1;
	}
	else {
	  std::cout << "Killing child process" << childPID << " after " << elapsed << " seconds elapsed." << std::endl;
	  // the '-' here means to kill the group
	  kill(childPID, SIGKILL);
	  kill(-childPID, SIGKILL);
	  usleep(1000); /* wait 1/1000th of a second for the process to die */
	}
      }
    } while (wpid == 0);

    if (WIFEXITED(status)) {
      printf("Child exited, status=%d\n", WEXITSTATUS(status));
      result = WEXITSTATUS(status);
    }
    else if (WIFSIGNALED(status)) {
      printf("Child %d was terminated with a status of: %d \n", childPID, WTERMSIG(status));
      result = WTERMSIG(status);
    }

    std::cout << "PARENT PROCESS COMPLETE: " << std::endl;
    parent_result = system("date");
    assert (parent_result == 0);
  }

  return result;
}
