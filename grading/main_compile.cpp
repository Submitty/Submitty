#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>
#include <set>
#include <fstream>
#include <dirent.h>

#include "TestCase.h"


#include "execute.h"

#include "default_config.h"

#define DIR_PATH_MAX 1000


// =====================================================================
// =====================================================================

void LoadDisallowedWords(const std::string &filename,
			 std::set<std::string> &disallowed_words,
			 std::set<std::string> &warning_words) {
  std::ifstream istr(filename.c_str());
  if (!istr) {
    system("pwd");
    system("whoami");
    system("ls -lta");
    std::cout << "file is " << filename << std::endl;
  }
  assert (istr);
  std::string token1, token2;
  while (istr >> token1 >> token2) {
    if (token1 == "disallow") {
      assert (warning_words.find(token2) == warning_words.end());
      disallowed_words.insert(token2);
    } else if (token1 == "warning") {
      assert (disallowed_words.find(token2) == disallowed_words.end());
      warning_words.insert(token2);
    } else {
      std::cout << "UNKNOWN TOKEN " << token1 << std::endl;
      exit(1);
    }
  }
}

void SearchForDisallowedWords(std::set<std::string> &disallowed_words,
			      std::set<std::string> &warning_words) {

  system ("ls -lta");
  system ("whoami");

  char buf[DIR_PATH_MAX];
  getcwd( buf, DIR_PATH_MAX );
  DIR* dir = opendir(buf);
  assert (dir != NULL);
  struct dirent *ent;

  bool disallowed = false;
  bool warning = false;

  while (1) {
    ent = readdir(dir);
    if (ent == NULL) break;
    if (ent->d_type != DT_REG) continue;
    std::string filename = ent->d_name;
    if (filename == "disallowed_words.txt") continue;
    if (filename == "my_compile.out") continue;

    for (std::set<std::string>::iterator itr = disallowed_words.begin(); itr != disallowed_words.end(); itr++) {
      int success = system ( (std::string("grep ")+(*itr)+" "+filename+" 1> /dev/null 2> /dev/null").c_str());
      if (success == 0) {
	std::cout << "DISALLOWED: " << filename << " contains '" << (*itr) << "'" << std::endl;
	disallowed = true;
      }
    }

    for (std::set<std::string>::iterator itr = warning_words.begin(); itr != warning_words.end(); itr++) {
      int success = system ( (std::string("grep ")+(*itr)+" "+filename+" 1> /dev/null 2> /dev/null").c_str());
      if (success == 0) {
	std::cout << "WARNING: " << filename << " contains '" << (*itr) << "'" << std::endl;
	warning = true;
      }
    }

  }
  closedir(dir);

  if (disallowed) {
    exit(1);
  }
}

// =====================================================================

int main(int argc, char *argv[]) {

  std::cout << "MAIN COMPILE" << std::endl;

  nlohmann::json config_json;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> config_json;

  std::cout << "JSON PARSED" << std::endl;

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";

  /* Check argument usage */
  if (argc == 5) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
  }
  else if (argc != 1) {
    std::cerr << "INCORRECT ARGUMENTS TO COMPILER" << std::endl;
    return 1;
  } 

  /*
  std::cout << "Scanning User Code..." << std::endl;

  std::set<std::string> disallowed_words;
  std::set<std::string> warning_words;
  LoadDisallowedWords("disallowed_words.txt",disallowed_words,warning_words);
  SearchForDisallowedWords(disallowed_words,warning_words);
  */

  std::cout << "Compiling User Code..." << std::endl;

  system("find . -type f");

#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  std::string replace_string_before = __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__;
  std::string replace_string_after  = CustomizeAutoGrading(rcsid);
  std::cout << "CUSTOMIZE AUTO GRADING for user '" << rcsid << "'" << std::endl;
  std::cout << "CUSTOMIZE AUTO GRADING replace " <<  replace_string_before << " with " << replace_string_after << std::endl;
#endif

  system ("ls -lta");

  // Run each COMPILATION TEST
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    TestCase my_testcase = TestCase::MakeTestCase((*tc)[i]);

    if (my_testcase.isCompilationTest()) {
      
      assert (my_testcase.numFileGraders() == 0);
      
      std::cout << "========================================================" << std::endl;
      std::cout << "TEST " << i+1 << " " << my_testcase.command() << " IS COMPILATION!" << std::endl;
      
      std::string cmd = my_testcase.command();
      assert (cmd != "");



#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
      std::cout << "BEFORE " << cmd << std::endl;
      while (1) {
	int location = cmd.find(replace_string_before);
	if (location == std::string::npos) 
	  break;
	cmd.replace(location,replace_string_before.size(),replace_string_after);
      }
      std::cout << "AFTER  " << cmd << std::endl;
#endif
      
      // run the command, capturing STDOUT & STDERR
      int exit_no = execute(cmd +
			    " 1>" + my_testcase.prefix() + "_STDOUT.txt" +
			    " 2>" + my_testcase.prefix() + "_STDERR.txt",
			    my_testcase.prefix() + "_execute_logfile.txt",
			    my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json())); 
      
      std::cout<< "Exited with exit_no: "<<exit_no<<std::endl;
    }
  }
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;

  system("find . -type f");

  return 0;
}

// ----------------------------------------------------------------

