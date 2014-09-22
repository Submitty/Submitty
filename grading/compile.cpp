/* FILENAME: compile.cpp
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


#include <config.h>

//                                              5 seconds,                 100 kb
int execute(const std::string &cmd, int seconds_to_run=5, int file_size_limit=100000);
std::string to_string(int i);

int main(int argc, char *argv[]) {

  // Make sure arguments are entered correctly
  if (argc != 1) {
    // Pass in the current working directory to run the programs
    std::cout << "Incorrect # of arguments:" << argc << std::endl;
    std::cout << "Usage : " << std::endl << "     ./compile" << std::endl;
    return 2;
  }


  std::cout << "Compiling User Code..." << std::endl;


  // Run each COMPILATION TEST
  for (unsigned int i = 0; i < num_testcases; i++) {
    if (!testcases[i].isCompilationTest()) continue;
    assert (testcases[i].numFileGraders() == 0);

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST " << i+1 << " " << testcases[i].command() << " IS COMPILATION!" << std::endl;

    std::string cmd = testcases[i].command();
    assert (cmd != "");

    //std::cout << "LS:\n";
    //execute("/bin/pwd",5);
    //execute("/bin/ls -lta *cpp",4);
    //system("/bin/ls -lta *cpp");
    //std::cout << "LS DONE!\n";

    // run the command, capturing STDOUT & STDERR
    int exit_no = execute(cmd + 
			  " 1>test" + to_string(i + 1) + "_cout.txt" +
			  " 2>test" + to_string(i + 1) + "_cerr.txt",
			  testcases[i].seconds_to_run(),
			  10000000); // 10 mb

    //std::cout << "AFTER LS:\n";
    //execute("/bin/ls -a *",4);
    //std::cout << "AFTER LS DONE!\n";

  }

  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  // allow hwcron read access so the files can be copied back
  //  execute ("/usr/bin/find . -user untrusted -exec chmod o+r {} ;");
  
  return 0;
}

// ----------------------------------------------------------------

std::string to_string(int i) {
  std::ostringstream tmp;
  tmp << std::setfill('0') << std::setw(2) << i;
  return tmp.str();
}


