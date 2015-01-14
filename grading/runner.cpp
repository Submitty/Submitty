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

#include "config.h"

#include "execute.h"

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

  setenv("DYNAMORIO_CONFIGDIR", ".", 1);

  // Run each test case and create output files
  for (unsigned int i = 0; i < num_testcases; i++) {
    if (testcases[i].isFileExistsTest()) continue;
    if (testcases[i].isCompilationTest()) continue;

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST " << i+1 << " " << testcases[i].command() << std::endl;

    std::string cmd = testcases[i].command();
    assert (cmd != "");

    std::string logfile = std::string("test") + to_string(i + 1) + "_execute_logfile.txt";
      // run the command, capturing STDOUT & STDERR
    int exit_no = execute(cmd +
			  " 1>test" + to_string(i + 1) + "_STDOUT.txt" +
			  " 2>test" + to_string(i + 1) + "_STDERR.txt",
			  logfile,
			  testcases[i].seconds_to_run(),
			  max_output_size);


    /*
    // append the test case # to the front of the output file (if it exists)
    //      assert (testcases[i].numFileComparisons() >= 1);
    if (exit_no == 1){
      std::ofstream cerr_out (std::string("test" + to_string(i + 1) + "_cerr.txt").c_str(), std::ofstream::out | std::ofstream::app);
        cerr_out << "Program exited with errors (exit code was not 0)\n";
        std::cout << "Running terminated, code 1" << std::endl;

        cerr_out.close();
    }
    else if (exit_no == 2){
      std::ofstream cerr_out (std::string("test" + to_string(i + 1) + "_cerr.txt").c_str(), std::ofstream::out | std::ofstream::app);
        cerr_out << "Running terminated, exceeded max limits\n";
        std::cout << "Running terminated, exceeded max limits, code 2" << std::endl;

        cerr_out.close();
    }
    else if (exit_no == 3){
      std::ofstream cerr_out (std::string("test" + to_string(i + 1) + "_cerr.txt").c_str(), std::ofstream::out | std::ofstream::app);
        cerr_out << "Running terminated, time elapsed was longer that allocated time\n";
        std::cout << "Running terminated, time elapsed was longer that allocated time code 3" << std::endl;

        cerr_out.close();
    }
    */

    if (testcases[i].numFileGraders() > 0 &&
	testcases[i].raw_filename(0) != "" &&
	access( testcases[i].raw_filename(0).c_str(), F_OK|R_OK|W_OK ) != -1) { // file exists 
      execute ("/bin/mv "+testcases[i].raw_filename(0)+" "+testcases[i].filename(0),
	       "/dev/null",
	       max_cputime,max_output_size);
    }

    //execute ("ls","/dev/null", max_cputime,max_output_size);


    //}
  }

  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  // allow hwcron read access so the files can be copied back


  execute ("/usr/bin/find . -user untrusted -exec /bin/chmod o+r {} ;",
	   "/dev/null", 
	   10*max_cputime,
	   10*max_output_size,
	   0);

  return 0;
}

// ----------------------------------------------------------------

std::string to_string(int i) {
  std::ostringstream tmp;
  tmp << std::setfill('0') << std::setw(2) << i;
  return tmp.str();
}
