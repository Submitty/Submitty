#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>

#include "config.h"
#include "execute.h"



// =====================================================================
// =====================================================================


int main(int argc, char *argv[]) {
  std::cout << "Running User Code..." << std::endl;

  // Make sure arguments are entered correctly
  if (argc != 1) {
    // Pass in the current working directory to run the programs
    std::cout << "Incorrect # of arguments:" << argc << std::endl;
    std::cout << "Usage : " << std::endl << "     ./runner" << std::endl;
    return 2;
  }

  // necessary since the untrusted user does not have a home directory
  setenv("DYNAMORIO_CONFIGDIR", ".", 1);

  // Run each test case and create output files
  for (unsigned int i = 0; i < testcases.size(); i++) {
    if (testcases[i].isFileExistsTest()) continue;
    if (testcases[i].isCompilationTest()) continue;

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST " << i+1 << " " << testcases[i].command() << std::endl;
    
    std::string cmd = testcases[i].command();
    assert (cmd != "");

    std::string logfile = testcases[i].prefix() + "_execute_logfile.txt";
    // run the command, capturing STDOUT & STDERR
    int exit_no = execute(cmd +
			  " 1>" + testcases[i].prefix() + "_STDOUT.txt" +
			  " 2>" + testcases[i].prefix() + "_STDERR.txt",
			  logfile,
			  testcases[i].get_test_case_limits());
    
    // rename any key files created by this test case to prepend the test number
    for (int f = 0; f < testcases[i].numFileGraders(); f++) {
      std::string raw_filename = testcases[i].raw_filename(f);
      std::string filename     = testcases[i].filename(f);
      if (raw_filename != "" &&
	access( raw_filename.c_str(), F_OK|R_OK|W_OK ) != -1) { // file exists 
	execute ("/bin/mv "+raw_filename+" "+filename,
		 "/dev/null",
		 testcases[i].get_test_case_limits()); 

      }
    }

  }
  
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  
  return 0;
}

// =====================================================================
// =====================================================================
