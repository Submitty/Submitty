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
      //      assert (testcases[i].numFileComparisons() >= 1);
      if (testcases[i].numFileComparisons() > 0 && 
	  testcases[i].raw_filename(0) != "" &&
	  access( testcases[i].raw_filename(0).c_str(), F_OK|R_OK|W_OK ) != -1) { /* file exists */
	execute ("/bin/mv "+testcases[i].raw_filename(0)+" "+testcases[i].filename(0));
      }
    }
  }

  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  // allow hwcron read access so the files can be copied back
  execute ("/usr/bin/find . -user untrusted -exec chmod o+r {} ;");
  
  return 0;
}

// ----------------------------------------------------------------

#define MAX_STRING_LENGTH 100
#define MAX_NUM_STRINGS 20

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
      my_args.push_back(tmp);
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
    //std::cout << "'" << my_args[i] << "' ";
    my_char_args[i+1] = (char*) my_args[i].c_str();
  }
  my_char_args[my_args.size()+1] = (char *)NULL;  // FIXME: casting away the const :(

  //std::cout << std::endl << std::endl;


  // FIXME: if we want to assert or print stuff afterward, we should save
  // the originals and restore after the exec fails.


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
int execute(const std::string &cmd, int seconds_to_run) {
  std::cout << "IN runner.cpp, going to execute '" << cmd << "'" << std::endl;

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

// ----------------------------------------------------------------

std::string to_string(int i) {
  std::ostringstream tmp;
  tmp << std::setfill('0') << std::setw(2) << i;
  return tmp.str();
}


