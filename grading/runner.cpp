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

#include <stdlib.h>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>

#include "modules/modules.h"
#include "grading/TestCase.h"


#include "grading/TestCase.cpp"   /* should not #include a .cpp file */


int execute(const std::string &cmd, int seconds_to_run=5);
std::string to_string(int i);

int main(int argc, char *argv[]) {
  std::cout << "Running User Code..." << std::endl;

  // Make sure arguments are entered correctly
  if (argc != 1) {
    // Pass in the current working directory to run the programs
    std::cout << "Incorrect # of arguments:" << argc << std::endl;
    std::cout << "Usage : " << std::endl << "     ./runner" << std::endl;
    return 2;
  }


  /*
    // FIXME: commented out for now.   currently getting compiled in the grade_students script.
    //  (where should it really happen??)
  if (compile_command != "") {
    int compile = execute(compile_command + " 2>.compile_out.txt");
    if (compile) {
      cerr << "COMPILATION FAILED" << std::endl;
      exit(1);
    }
  }
  */

  // Run each test case and create output files
  for (unsigned int i = 0; i < num_testcases; i++) {
    std::cout << "========================================================" << std::endl;
    std::cout << "TEST " << i+1 << " " << testcases[i].command() << std::endl;

    std::string cmd = testcases[i].command();
    if (cmd != "" && cmd != "FILE_EXISTS") {
      // run the command, capturing STDOUT & STDERR
      int exit_no = execute(cmd + 
			    " 1>test" + to_string(i + 1) + "_cout.txt" +
			    " 2>test" + to_string(i + 1) + "_cerr.txt",
			    testcases[i].seconds_to_run());
      // append the test case # to the front of the output file (if it exists)
      if (testcases[i].raw_filename() != "" &&
	  access( testcases[i].raw_filename().c_str(), F_OK|R_OK|W_OK ) != -1) { /* file exists */
	execute ("mv "+testcases[i].raw_filename()+" "+testcases[i].filename());
      }
    }
  }

  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  // allow hwcron read access so the files can be copied back
  execute ("find . -user untrusted -exec chmod o+r {} \\;");

  return 0;
}

// ----------------------------------------------------------------

// Executes command (from shell) and returns error code (0 = success)
int execute(const std::string &cmd, int seconds_to_run) { 
  std::cout << "IN runner.cpp, going to execute '" << cmd << "'" << std::endl;

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);

  if (childPID == 0) {
    // CHILD PROCESS

    // limit CPU time (unfortunately this is *not* wall clock time)
    rlimit time_limit;
    time_limit.rlim_cur = time_limit.rlim_max = seconds_to_run*2;
    int set_success = setrlimit(RLIMIT_CPU, &time_limit);
    assert (set_success == 0);
    

    // want to put all child processes in the same process group so we
    // can kill them later

    // FIXME: unfortunately, if the student code also does a setpgid
    // this could lead to runaway grandchildren processes
    int pgrp = setpgid(getpid(), 0);
    assert(pgrp == 0);

    // FIXME: should replace this system with an exec, but dealing
    // with stdout/stderr command line redirection will need to be
    // handled separately.  If replaced with an exec, we can know the
    // actual child pid and RLIMIT creation of additional processes,
    // so then the setpgid problem noted above is eliminated.
    int child_result = system(cmd.c_str()); 

    // send the system status code back to the parent process
    std::cout << "    child_result = " << child_result << std::endl;
    exit(child_result);

  } else {
    // PARENT PROCESS
    std::cout << "childPID = " << childPID << std::endl;
    std::cout << "PARENT PROCESS START: ";
    fflush(stdout);
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
	  kill(-childPID, SIGKILL); 
	  usleep(1000); /* wait 1/1000th of a second for the process to die */
	}
      }
    } while (wpid == 0 && elapsed <= seconds_to_run);
    
    if (WIFEXITED(status)) {
      printf("Child exited, status=%d\n", WEXITSTATUS(status));
      result = WEXITSTATUS(status);
    }
    else if (WIFSIGNALED(status)) {
      printf("Child %d was terminated with a status of: %d \n", childPID, WTERMSIG(status));
      result = WTERMSIG(status);
    }

    std::cout << "PARENT PROCESS COMPLETE: ";
    fflush(stdout);
    parent_result = system("date");
    assert (parent_result == 0);
  }

  return result;
}

// ----------------------------------------------------------------

std::string to_string(int i) {
  std::ostringstream tmp;
  tmp << std::setfill('0') << std::setw(2) << i;
  return tmp.str();
}


