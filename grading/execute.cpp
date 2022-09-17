#include <sys/time.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <signal.h>
#include <unistd.h>
#include <fcntl.h>
#include <dirent.h>

#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>
#include <map>
#include <set>
#include <chrono>
#include <iomanip>

// for system call filtering
#include <seccomp.h>
#include <elf.h>
#include <sys/poll.h>


#include "TestCase.h"
#include "execute.h"
#include "error_message.h"
#include "window_utils.h"

extern const int CPU_TO_WALLCLOCK_TIME_BUFFER;  // defined in default_config.h


#define DIR_PATH_MAX 1000


// defined in seccomp_functions.cpp


#define SUBMITTY_INSTALL_DIRECTORY  std::string("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__")


// =====================================================================================
// =====================================================================================


bool system_program(const std::string &program, std::string &full_path_executable, const bool running_in_docker) {

  std::map<std::string,std::string> allowed_system_programs = {

    // Basic System Utilities (for debugging)
    { "ls",                      "/bin/ls", },
    { "time",                    "/usr/bin/time" },
    { "mv",                      "/bin/mv" },
    { "cp",                      "/bin/cp" },
    { "chmod",                   "/bin/chmod" },
    { "find",                    "/usr/bin/find" },
    { "cat",                     "/bin/cat" },
    { "compare",                 "/usr/bin/compare" }, //image magick!
    { "mogrify",                 "/usr/bin/mogrify" }, //image magick!
    { "convert",                 "/usr/bin/convert" }, //image magick!
    { "wkhtmltoimage",           "/usr/bin/wkhtmltoimage" },
    { "wkhtmltopdf",             "/usr/bin/wkhtmltopdf" },
    { "xvfb-run",                "/xvfb-run" },   // allow htmltoimage and htmltopdf to run headless
    { "cut",                     "/usr/bin/cut" },
    { "sort",                    "/usr/bin/sort" },
    { "grep",                    "/bin/grep" },
    { "sed",                     "/bin/sed" },
    { "awk",                     "/usr/bin/awk" },
    { "pwd",                     "/bin/pwd" },
    { "env",                     "/usr/bin/env" },
    { "pdftotext",               "/usr/bin/pdftotext" },
    { "pdflatex",                "/usr/bin/pdflatex" },
    { "wc",                      "/usr/bin/wc" },
    { "head",                    "/usr/bin/head" },
    { "tail",                    "/usr/bin/tail" },
    { "uniq",                    "/usr/bin/uniq" },
    { "echo",                    "/bin/echo" },

    // Submitty Analysis Tools
    { "submitty_count",          SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/count" },
    { "commonast", 		 SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/commonast.py"},

    // Submitty Analysis ToolsTS
    { "submitty_count_ts",          SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisToolsTS/build/submitty_count_ts" },
    { "submitty_diagnostics_ts", 		 SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisToolsTS/build/submitty_diagnostics_ts"},

    // for Computer Science I
    { "python",                  "/usr/bin/python" },
    { "python2",                 "/usr/bin/python2" },
    { "python2.7",               "/usr/bin/python2.7" },
    { "python3",                 "/usr/bin/python3" },
    { "python3.5",               "/usr/bin/python3.5" },
    { "python3.6",               "/usr/bin/python3.6" },
    { "pylint",                  "/usr/local/bin/pylint" },

    // for Data Structures
    { "g++",                     "/usr/bin/g++" },
    { "clang++",                 "/usr/bin/clang++" },
    { "drmemory",                SUBMITTY_INSTALL_DIRECTORY+"/drmemory/bin64/drmemory" },
    { "valgrind",                "/usr/bin/valgrind" },

    // for Computer Organization
    { "spim",                    "/usr/bin/spim" },
    { "clang",                   "/usr/bin/clang" },
    { "gdb",                     "/usr/bin/gdb" },

    // for Principles of Software
    { "java",                    "/usr/bin/java" },
    { "javac",                   "/usr/bin/javac" },
    { "mono",                    "/usr/bin/mono" },   // should put more checks here, only run with "mono dafny/Dafny.exe "

    // for Operating Systems
    { "gcc",                     "/usr/bin/gcc" },
    { "strings",                 "/usr/bin/strings" },

    // for Programming Languages
    { "swipl",                   "/usr/bin/swipl" },
    { "plt-r5rs",                "/usr/bin/plt-r5rs" },
    { "ozc",                     "/usr/bin/ozc" },
    { "ozengine",                "/usr/bin/ozengine" },

    // for Program Analysis course
    { "ghc",                     "/usr/bin/ghc" },
    { "ocaml",                   "/usr/bin/ocaml" },
    { "ocamllex",                "/usr/bin/ocamllex" },
    { "ocamlyacc",               "/usr/bin/ocamlyacc" },
    { "z3",                      SUBMITTY_INSTALL_DIRECTORY+"/tools/z3" },

    // for Cmake & Make
    { "cmake",                   "/usr/bin/cmake" },
    { "make",                    "/usr/bin/make" },

    // for Network Programming
    { "timeout",                 "/usr/bin/timeout" },
    { "mpicc.openmpi",           "/usr/bin/mpicc.openmpi" },
    { "mpirun.openmpi",          "/usr/bin/mpirun.openmpi" },
    { "mpirun",                  "/usr/local/mpich-3.2/bin/mpirun"},
    { "mpicc",                   "/usr/local/mpich-3.2/bin/mpicc"},
    { "expect",                  "/usr/bin/expect" },
    { "sleep",                   "/bin/sleep" },

    // for Distributed Systems
    { "script",                  "/usr/bin/script" },

    // for LLVM / Compiler class
    { "lex",                     "/usr/bin/lex" },
    { "flex",                    "/usr/bin/flex" },
    { "yacc",                    "/usr/bin/yacc" },
    { "bison",                   "/usr/bin/bison" },

    // for graphics/window interaction
    { "scrot",                   "/usr/bin/scrot"}, //screenshot utility
    { "xdotool",                 "/usr/bin/xdotool"}, //keyboard/mouse input
    { "wmctrl",                  "/usr/bin/wmctrl"}, //bring window into focus
    { "xwininfo",                "/usr/bin/xwininfo"}, // get info about window

    // for Debugging
    { "strace",                  "/usr/bin/strace" },

    //Matlab
    { "matlab",                  "/usr/local/bin/matlab" }

  };

  if(running_in_docker){
    allowed_system_programs.insert({"bash", "/bin/bash"});
    allowed_system_programs.insert({"php",  "/usr/bin/php"});
  }
  // find full path name
  std::map<std::string,std::string>::const_iterator itr = allowed_system_programs.find(program);
  if (itr != allowed_system_programs.end()) {
    full_path_executable = itr->second;
    return true;
  }

  // did they already use the full path name?
  for (itr = allowed_system_programs.begin(); itr != allowed_system_programs.end(); itr++) {
    if (itr->second == program) {
      full_path_executable = program;
      return true;
    }
  }

  // not an allowed system program
  return false;
}


std::set<std::string> get_compiled_executables(const nlohmann::json &whole_config) {
  std::set<std::string> answer;
  assert (whole_config != nlohmann::json());

  nlohmann::json testcases = whole_config.value("testcases",nlohmann::json());
  for (nlohmann::json::iterator itr = testcases.begin(); itr != testcases.end(); itr++) {
    if (itr->value("type","") == "Compilation") {
      if (itr->find("executable_name") != itr->end()) {
        std::vector<std::string> executable_names = stringOrArrayOfStrings(*itr,"executable_name");
        for (unsigned int i = 0; i < executable_names.size(); i++) {
          answer.insert(executable_names[i]);
        }
      }
    }
  }
  return answer;
}


bool local_executable (const std::string &program, const nlohmann::json &whole_config) {
  return true;
  // assert (program.size() > 3);
  // assert (program.substr(0,2) == "./");

  // std::set<std::string> executables = get_compiled_executables(whole_config);

  // if (executables.find(program.substr(2,program.size())) != executables.end() || isInstructor) {
  //   return true;
  // }

  // std::cout << "WARNING: The local program '" << program
  //           << "' is not compiled by the assignment configuration." << std::endl;;
  // std::cout << "CONFIGURATION COMPILED EXECUTABLES: ";
  // for (std::set<std::string>::iterator itr = executables.begin(); itr != executables.end(); itr++) {
  //   std::cout << " './" << *itr << "'";
  // }
  // if (executables.size() == 0) { std::cout << " (none)" << std::endl; }
  // std::cout << std::endl;

  // return false;
}


std::string validate_program(const std::string &program, const nlohmann::json &whole_config) {
  std::string full_path_executable;
  assert (program.size() >= 1);
  if (program.size() > 2 && program.substr(0,2) == "./") {
    if (local_executable(program,whole_config)) {
      return program;
    }
    std::string message = "ERROR: This local program '" + program + "' looks suspicious.\n"
      + "  Check your assignment configuration.";
    std::cout << message << std::endl;
    std::cerr << message << std::endl;
    exit(1);
  } else {
    std::string mode = whole_config.value("autograding_method", "");
    bool running_in_docker = (mode == "docker") ? true : false;
    if (system_program(program,full_path_executable,running_in_docker)) {
      return full_path_executable;
    }

    std::string message = "ERROR: This system program '" + program + "' is not on the allowed safelist.\n"
      + "  Contact the Submitty administrators for permission to use this program.";
    std::cout << message << std::endl;
    std::cerr << message << std::endl;
    exit(1);
  }
}


void validate_filename(const std::string &filename) {
  assert (filename.size() >= 1);
  if (filename[0] == '-') {
    std::cout << "WARNING: command line filename looks suspicious '" << filename << "'" << std::endl;
    std::cerr << "WARNING: command line filename looks suspicious '" << filename << "'" << std::endl;
    // note: cannot enforce this when running drmemory on a program with options :(
    //exit(1);
  }
}


std::string validate_option(const std::string &program, const std::string &option) {

  const std::map<std::string,std::map<std::string,std::string> > option_replacements = {
    { "/usr/bin/javac",
      { { "submitty_jacocoagent.jar",         SUBMITTY_INSTALL_DIRECTORY+"/java_tools/jacoco/jacocoagent.jar" },
        { "submitty_jacococli.jar",           SUBMITTY_INSTALL_DIRECTORY+"/java_tools/jacoco/jacococli.jar" },
        { "submitty_junit.jar",               SUBMITTY_INSTALL_DIRECTORY+"/java_tools/JUnit/junit-4.12.jar" },
        { "submitty_hamcrest.jar",            SUBMITTY_INSTALL_DIRECTORY+"/java_tools/hamcrest/hamcrest-core-1.3.jar" },
        { "submitty_junit/",                  SUBMITTY_INSTALL_DIRECTORY+"/java_tools/JUnit/" },
        // older, requested version:
        { "submitty_soot.jar",                SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/soot-develop.jar" },
        { "submitty_rt.jar",                  SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/rt.jar" }
        // most recent libraries:
        //{ "submitty_soot.jar",                SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/sootclasses-trunk.jar" },
        //{ "submitty_soot_dependencies.jar",   SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/sootclasses-trunk-jar-with-dependencies.jar" },
        //{ "submitty_rt.jar",                  "/usr/lib/jvm/java-8-oracle/jre/lib/rt.jar" }
      }
    },
    { "/usr/bin/java",
      { { "submitty_jacocoagent.jar",         SUBMITTY_INSTALL_DIRECTORY+"/java_tools/jacoco/jacocoagent.jar" },
        { "submitty_jacococli.jar",           SUBMITTY_INSTALL_DIRECTORY+"/java_tools/jacoco/jacococli.jar" },
        { "submitty_junit.jar",               SUBMITTY_INSTALL_DIRECTORY+"/java_tools/JUnit/junit-4.12.jar" },
        { "submitty_hamcrest.jar",            SUBMITTY_INSTALL_DIRECTORY+"/java_tools/hamcrest/hamcrest-core-1.3.jar" },
        { "submitty_junit/",                  SUBMITTY_INSTALL_DIRECTORY+"/java_tools/JUnit/" },
        // older, requested version:
        { "submitty_soot.jar",                SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/soot-develop.jar" },
        { "submitty_rt.jar",                  SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/rt.jar" }
        // most recent libraries:
        //{ "submitty_soot.jar",                SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/sootclasses-trunk.jar" },
        //{ "submitty_soot_dependencies.jar",   SUBMITTY_INSTALL_DIRECTORY+"/java_tools/soot/sootclasses-trunk-jar-with-dependencies.jar" },
        //{ "submitty_rt.jar",                  "/usr/lib/jvm/java-8-oracle/jre/lib/rt.jar" }
      }
    },
    { "/usr/bin/mono",
      { { "submitty_dafny",         SUBMITTY_INSTALL_DIRECTORY+"/Dafny/dafny/Dafny.exe" }
      }
    }
  };

  // see if this program has option replacements
  std::map<std::string,std::map<std::string,std::string> >::const_iterator itr = option_replacements.find(program);
  if (itr != option_replacements.end()) {
    std::string answer = option;
    // loop over all replacements and see if any match
    std::map<std::string,std::string>::const_iterator itr2;
    for (itr2 = itr->second.begin(); itr2 != itr->second.end(); itr2++) {
      std::string pattern = itr2->first;
      std::string::size_type pos = answer.find(pattern);
      if (pos == std::string::npos) continue;
      std::string before = answer.substr(0,pos);
      std::string after =  answer.substr(pos+pattern.length(),answer.length()-pos-pattern.length());
      answer = before + itr2->second + after;
    }
    if (option != answer) {
      std::cout << "REPLACE OPTION '" << option << "' with '" << answer << "'" << std::endl;
    }
    return answer;
  }

  // otherwise, just use the option
  return option;

  /*
  static std::string last_option = "";
  assert (option.size() >= 1);
  if (option[0] == '-') {
    // probably a normal option
  } else if (last_option == "-o" &&
       option.size() > 4 &&
       option.substr(option.size()-4,4) == ".out") {
    // ok, it's an executable name
  } else if (local_executable(program)) {
    // custom
  } else {
    std::cout << "WARNING: command line option looks suspicious '" << option << "'" << std::endl;
    std::cerr << "WARNING: command line option looks suspicious '" << option << "'" << std::endl;
    //exit(1);
  }
  last_option = option;
  */
}

// =====================================================================================

std::string replace_slash_with_double_underscore(const std::string& input) {
  std::string answer;
  for (int i = 0; i < input.size(); i++) {
    if (input[i] != '/') answer.push_back(input[i]);
    else {
      answer.push_back('_');
      answer.push_back('_');
    }
  }
  return answer;
}

std::string escape_spaces(const std::string& input) {
  std::string answer;
  for (int i = 0; i < input.size(); i++) {
    if (input[i] != ' ') answer.push_back(input[i]);
    else {
      answer.push_back('\\');
      answer.push_back(' ');
    }
  }
  return answer;
}

// =====================================================================

bool wildcard_match(const std::string &pattern, const std::string &thing) {
  //  std::cout << "WILDCARD MATCH? " << pattern << " " << thing << std::endl;

  int wildcard_loc = pattern.find("*");
  if (wildcard_loc == std::string::npos) {
    return pattern == thing;
  }
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

  if (before == thing_before && after == thing_after) {
    //std::cout << "RETURN TRUE" << std::endl;
    return true;
  }

  //std::cout << "return false" << std::endl;
  return false;
}


bool contains_unescaped_wildcard(const std::string &pattern) {

  std::cout << "CHECK FOR UNESCAPED WILDCARD '" << pattern << "'" << std::endl;
  int loc = pattern.find("*");
  if (loc == std::string::npos) return false;
  if (loc == 0 || pattern[loc-1] != '\\') return true;
  return false;
}

bool contains_escaped_wildcard(const std::string &pattern) {
  int loc = pattern.find("*");
  if (loc == std::string::npos) return false;
  if (loc > 0 && pattern[loc-1] == '\\') return true;
  return false;
}

std::string replace_escaped_wildcard(const std::string &input) {

  std::string answer;
  std::cout << "BEFORE '" << input << "'" << std::endl;
    for (int i = 0; i < input.size(); i++) {
    if (i+1 < input.size() && input[i] == '\\' && input[i+1] == '*') {
      i++;
    }
    answer += input[i];
  }
  std::cout << "AFTER '" << answer << "'" << std::endl;
  return answer;
}


void wildcard_expansion(std::vector<std::string> &my_finished_args, const std::string &full_pattern, std::ostream &logfile) {

  //std::cout << "IN WILDCARD EXPANSION " << full_pattern << std::endl;

  // if the pattern does not contain a wildcard, just return that
  if (full_pattern.find("*") == std::string::npos) {
    std::ifstream istr(full_pattern);
    if (istr.good()) {
      my_finished_args.push_back(full_pattern);
      return;
    } else {
      std::cout << "ERROR: FOUND NO MATCHES" << std::endl;
      return;
    }
  }

  std::vector<std::string> my_args;

  //  std::cout << "WILDCARD DETECTED:" << full_pattern << std::endl;

  // otherwise, if our pattern contains directory structure, first remove that
  std::string directory = "";
  std::string file_pattern = full_pattern;
  while (1) {
    size_t location = file_pattern.find("/");
    if (location == std::string::npos) { break; }
    directory += file_pattern.substr(0,location+1);
    file_pattern = file_pattern.substr(location+1,file_pattern.size()-location-1);
  }
  std::cout << "  directory: " << directory << std::endl;
  std::cout << "  file_pattern " << file_pattern << std::endl;

  // FIXME: could extend this to allow a wildcard in the directory name
  // confirm that the directory does not contain a wildcard
  assert (directory.find("*") == std::string::npos);
  // confirm that the file pattern does contain a wildcard
  assert (file_pattern.find("*") != std::string::npos);

  int count_matches = 0;

  // combine the current directory plus the directory portion of the pattern
  char buf[DIR_PATH_MAX];
  getcwd( buf, DIR_PATH_MAX );
  std::string d = std::string(buf)+"/"+directory;
  // note: we don't want the trailing "/"
  if (d.size() > 0 && d[d.size()-1] == '/') {
    d = d.substr(0,d.size()-1);
  }
  DIR* dir = opendir(d.c_str());
  if (dir != NULL) {

    // loop over all files in the directory, see which ones match the pattern
    struct dirent *ent;
    while (1) {
      ent = readdir(dir);
      if (ent == NULL) break;
      std::string thing = ent->d_name;
      if (wildcard_match(file_pattern,thing)) {
        std::cout << "   MATCHED!  '" << thing << "'" << std::endl;
        validate_filename(directory+thing);
        my_args.push_back(directory+thing);
        count_matches++;
      } else {
        //std::cout << "   no match  '" << thing << "'" << std::endl;
      }
    }
    closedir(dir);
  }

  if (count_matches == 0) {
    std::cout << "ERROR: WILDCARD FOUND NO MATCHES" << std::endl;
  }

  // sort the matches, so things are deterministic (and unix wildcard is sorted)
  std::sort(my_args.begin(),my_args.end());
  my_finished_args.insert(my_finished_args.end(), my_args.begin(), my_args.end());
}

// =====================================================================================
// =====================================================================================

std::string get_program_name(const std::string &cmd, const nlohmann::json &whole_config) {
  std::string my_program;
  std::stringstream ss(cmd);

  ss >> my_program;
  assert (my_program.size() >= 1);

  std::string full_path_executable = validate_program(my_program, whole_config);
  return full_path_executable;
}


std::vector<std::string> break_into_tokens(const std::string &cmd) {
  std::vector<std::string> answer;
  std::string current_token;
  std::stringstream ss(cmd);
  char c;
  bool quoted = false;

  while (ss >> std::noskipws >> c) {
    //std::cout << "char is '" << c << "'" << std::endl;

    // ESCAPED CHARACTER
    if (c == '\\') {
      ss >> std::noskipws >> c;
      current_token.push_back(c);
    }

    // SINGLE QUOTED STRING
    else if (c =='\'') {

    }

    // WHITE SPACE
    else if (c == ' ' ||
             c == '\t' ||
             c == '\n') {
      if (current_token != "") {
        answer.push_back(current_token);
        //std::cout << "--->  '" << current_token << "'" << std::endl;
        current_token = "";
      }
    }

    // ALL OTHER CHARACTERS
    else {
      current_token.push_back(c);
    }
  }

  // HANDLE LAST TOKEN
  if (current_token != "") {
    //std::cout << "--->  '" << current_token << "'" << std::endl;
    answer.push_back(current_token);
  }

  return answer;
}


void parse_command_line(const std::string &cmd,
      std::string &my_program,
      std::vector<std::string> &my_args,
      std::string &my_stdin,
      std::string &my_stdout,
      std::string &my_stderr,
      std::ofstream &logfile,
      const nlohmann::json &whole_config) {

  std::cout << "PARSE COMMAND LINE " << cmd << std::endl;

  my_args.clear();
  my_program = my_stdin = my_stdout = my_stderr = "";

  std::vector<std::string> tokens = break_into_tokens(cmd);

  for (int i = 0; i < tokens.size(); i++) {
    std::cout << "TOKEN " << std::setw(3) << i << " IS '" << tokens[i] << "'" << std::endl;
  }

  int which = 0;
  while (which < tokens.size()) {
    std::string token = tokens[which];

    // grab the program name
    if (my_program == "") {
      assert (my_args.size() == 0);
      // program name
      my_program = validate_program(token, whole_config);
      assert (my_program != "");
    }

    // look for stdin/stdout/stderr
    else if (token.substr(0,1) == "<") {
      assert (my_stdin == "");
      if (token.size() == 1) {
        which++;
        assert (which < tokens.size());
        my_stdin = tokens[which];
      } else {
        my_stdin = token.substr(1,token.size()-1);
      }
      validate_filename(my_stdin);
    }
    else if (token.size() >= 2 && token.substr(0,2) == "1>") {
      assert (my_stdout == "");
      if (token.size() == 2) {
        which++;
        assert (which < tokens.size());
        my_stdout = tokens[which];
      } else {
        my_stdout = token.substr(2,token.size()-2);
      }
      validate_filename(my_stdout);
    }
    else if (token.size() >= 1 && token.substr(0,1) == ">") {
      assert (my_stdout == "");
      if (token.size() == 1) {
        which++;
        assert (which < tokens.size());
        my_stdout = tokens[which];
      } else {
        my_stdout = token.substr(1,token.size()-1);
      }
      validate_filename(my_stdout);
    }
    else if (token.size() >= 2 && token.substr(0,2) == "2>") {
      assert (my_stderr == "");
      if (token.size() == 2) {
        which++;
        assert (which < tokens.size());
        my_stderr = tokens[which];
      } else {
        my_stderr = token.substr(2,token.size()-2);
      }
      validate_filename(my_stderr);
    }

    // remainder of the arguments
    else if (contains_unescaped_wildcard(token)) { //token.find("*") != std::string::npos) {
      wildcard_expansion(my_args,token,logfile);
    }

    // special exclude file option
    // FIXME: this is ugly, don't know how I want it to be done though
    else if (token == "-EXCLUDE_FILE") {
      which++;
      assert (which < tokens.size());
      token = tokens[which];
      std::cout << "EXCLUDE THIS FILE " << token << std::endl;
      for (std::vector<std::string>::iterator itr = my_args.begin();
           itr != my_args.end(); ) {
        if (*itr == token) {  std::cout << "FOUND IT!" << std::endl; itr = my_args.erase(itr);  }
        else { itr++; }
      }
    }

    else {

      if (contains_escaped_wildcard(token)) {
	token = replace_escaped_wildcard(token);
      }

      // validate_filename(token);
      // validate_option(my_program,token);
      //std::cout << "before TOKEN IS " << token << std::endl;
      token = validate_option(my_program,token);
      //std::cout << "after  TOKEN IS " << token << std::endl;
      my_args.push_back(token);
    }

    which++;
  }



  // Usually we should call python with a single argument, the script to run.
  if (my_program.find("python") != std::string::npos) {
    if (my_args.size() == 0) {
      // If nothing matched the wild card search
      std::cout << "ERROR!  ATTEMPTING TO RUN PYTHON IN INTERACTIVE MODE" << std::endl;
      logfile << "ERROR!  ATTEMPTING TO RUN PYTHON IN INTERACTIVE MODE" << std::endl;
      // FIXME:  Hack a file name for now, but this should be handled more elegantly
      my_args.push_back(" ");
      // because we don't want to run in interactive mode and wait for it to time out!
    } else if (my_args.size() > 1) {
      // a common student error is to submit multiple .py files where
      // only one is expected and we want to run 'python *.py'
      int python_file_count = 0;
      for (int i = 0; i < my_args.size(); i++) {
        unsigned int pos = my_args[i].find(".py");
        if (pos != std::string::npos &&
            pos == (my_args[i].size())-3) {
          python_file_count++;
        }
      }
      if (python_file_count == 0) {
        std::cout << "WARNING!  RUNNING PYTHON WITH NO .py FILES" << std::endl;
        logfile << "WARNING!  RUNNING PYTHON WITH NO .py FILES" << std::endl;
      }
      if (python_file_count > 1) {
        std::cout << "WARNING!  RUNNING PYTHON WITH MULTIPLE .py FILES" << std::endl;
        logfile << "WARNING!  RUNNING PYTHON WITH MULTIPLE .py FILES" << std::endl;
      }
      if (python_file_count != 1) {
        for (int i = 0; i < my_args.size(); i++) {
          logfile << my_args[i] << " ";
        }
        logfile << std::endl;
      }
    }
  }



  // FOR DEBUGGING
  std::cout << std::endl << std::endl;
  std::cout << "MY PROGRAM: '" << my_program << "'" << std::endl;
  std::cout << "MY STDIN:  '" << my_stdin  << "'" << std::endl;
  std::cout << "MY STDOUT:  '" << my_stdout  << "'" << std::endl;
  std::cout << "MY STDERR:  '" << my_stderr  << "'" << std::endl;
  std::cout << "MY ARGS (" << my_args.size() << ") :";
  for (int i = 0; i < my_args.size(); i++) {
    std::cout << "'" << my_args[i] << "' ";
  }
  std::cout << "\n" << std::endl;
}

// =====================================================================================
// =====================================================================================
// =====================================================================================

void OutputSignalErrorMessageToExecuteLogfile(int what_signal, std::ofstream &logfile) {


#if 0

  // default message (may be overwritten with more specific message below)
  std::stringstream ss;
  ss << "ERROR: Child terminated with signal " << what_signal;
  std::string message = ss.str();

  // reference: http://man7.org/linux/man-pages/man7/signal.7.html
  if        (what_signal == SIGHUP    /*  1        Term  Hangup detected on controlling terminal or death of controlling process   */) {
  } else if (what_signal == SIGINT    /*  2        Term  Interrupt from keyboard  */) {
  } else if (what_signal == SIGQUIT   /*  3        Core  Quit from keyboard  */) {
  } else if (what_signal == SIGILL    /*  4        Core  Illegal Instruction  */) {
  } else if (what_signal == SIGABRT   /*  6        Core  Abort signal from abort(3)  */) {
    message = "ERROR: ABORT SIGNAL";
  } else if (what_signal == SIGFPE    /*  8        Core  Floating point exception  */) {
    message = "ERROR: FLOATING POINT ERROR";
  } else if (what_signal == SIGKILL   /*  9        Term  Kill signal  */) {
    message = "ERROR: KILL SIGNAL";
  } else if (what_signal == SIGSEGV   /* 11        Core  Invalid memory reference  */) {
    message = "ERROR: INVALID MEMORY REFERENCE";
  } else if (what_signal == SIGPIPE   /* 13        Term  Broken pipe: write to pipe with no readers  */) {
  } else if (what_signal == SIGALRM   /* 14        Term  Timer signal from alarm(2)  */) {
  } else if (what_signal == SIGTERM   /* 15        Term  Termination signal  */) {
  } else if (what_signal == SIGUSR1   /* 30,10,16  Term  User-defined signal 1  */) {
  } else if (what_signal == SIGUSR2   /* 31,12,17  Term  User-defined signal 2  */) {
  } else if (what_signal == SIGCHLD   /* 20,17,18  Ign   Child stopped or terminated  */) {
  } else if (what_signal == SIGCONT   /* 19,18,25  Cont  Continue if stopped  */) {
  } else if (what_signal == SIGSTOP   /* 17,19,23  Stop  Stop process  */) {
  } else if (what_signal == SIGTSTP   /* 18,20,24  Stop  Stop typed at terminal  */) {
  } else if (what_signal == SIGTTIN   /* 21,21,26  Stop  Terminal input for background process  */) {
  } else if (what_signal == SIGTTOU   /* 22,22,27  Stop  Terminal output for background process  */) {
  } else if (what_signal == SIGBUS    /* 10,7,10   Core  Bus error (bad memory access)  */) {
    message = "ERROR: BUS ERROR (BAD MEMORY ACCESS)";
  } else if (what_signal == SIGPOLL   /*           Term  Pollable event (Sys V). Synonym for SIGIO  */) {
  } else if (what_signal == SIGPROF   /* 27,27,29  Term  Profiling timer expired  */) {
  } else if (what_signal == SIGSYS    /* 12,31,12  Core  Bad argument to routine (SVr4)  */) {
    std::cout << "********************************\nDETECTED BAD SYSTEM CALL\n***********************************" << std::endl;
    message = "ERROR: DETECTED BAD SYSTEM CALL, please report this error to submitty@cs.rpi.edu";
  } else if (what_signal == SIGTRAP   /*  5        Core  Trace/breakpoint trap  */) {
  } else if (what_signal == SIGURG    /* 16,23,21  Ign   Urgent condition on socket (4.2BSD)  */) {
  } else if (what_signal == SIGVTALRM /* 26,26,28  Term  Virtual alarm clock (4.2BSD)  */) {
  } else if (what_signal == SIGXCPU   /* 24,24,30  Core  CPU time limit exceeded (4.2BSD)  */) {
    message = "ERROR: CPU TIME LIMIT EXCEEDED";
  } else if (what_signal == SIGXFSZ   /* 25,25,31  Core  File size limit exceeded (4.2BSD  */) {
    message = "ERROR: FILE SIZE LIMIT EXCEEDED";
  } else {
  }

  // output message to behind-the-scenes logfile (stdout), and to execute logfile (available to students)
  std::cout << message << std::endl;
  logfile   << message << "\nProgram Terminated " << std::endl;

}
#endif


    std::string message = RetrieveSignalErrorMessage(what_signal);


    // output message to behind-the-scenes logfile (stdout), and to execute
    // logfile (available to students)
    std::cout << message << std::endl;
    logfile   << message << "\nProgram Terminated " << std::endl;

}

// =====================================================================================
// =====================================================================================


// This function only returns on failure to exec
int exec_this_command(const std::string &cmd, std::ofstream &logfile, const nlohmann::json &whole_config,
                      std::string program_name, const nlohmann::json &test_case_limits,
                      const nlohmann::json &assignment_limits, const bool timestamped_stdout) {

  /*************************************************
  *
  * COMMAND LINE PARSING
  *
  **************************************************/

  // the default umask is 0027, so we need edit so that we can make
  // these files 'other read', so that we can read them when we switch
  // users
  mode_t everyone_read = S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH;
  mode_t prior_umask = umask(S_IWGRP | S_IWOTH);  // save the prior umask


  std::string my_program;
  std::vector<std::string> my_args;
  std::string my_stdin;
  std::string my_stdout;
  std::string my_stderr;
  parse_command_line(cmd, my_program, my_args, my_stdin, my_stdout, my_stderr, logfile, whole_config);



  // SECCOMP:  Used to restrict allowable system calls.
  // First we determine if the program we will run is a 64 or 32 bit
  // executable (the system calls are different on 64 vs. 32 bit)
  Elf64_Ehdr elf_hdr;
  // std::cout << "reading " <<  my_program << std::endl;
  int fd = open(my_program.c_str(), O_RDONLY);
  if (fd == -1) {
    //perror("can't open");
    // std::cout << "ERROR: cannot open program '" << my_program << '"' << std::endl;
    exit(1);
  }
  int res = read(fd, &elf_hdr, sizeof(elf_hdr));
  if (res < sizeof(elf_hdr)) {
    // perror("can't read ");
    // std::cout << "ERROR: cannot read program" << std::endl;
    exit(1);
  }
  int prog_is_32bit;
  if (elf_hdr.e_machine == EM_386) {
    // std::cout << "this is a 32 bit program" << std::endl;
    prog_is_32bit = 1;
  }
  else {
    // std::cout << "this is a 64 bit program" << std::endl;
    prog_is_32bit = 0;
  }
  // END SECCOMP

  //if (SECCOMP_ENABLED != 0) {
  // std::cout << "seccomp filter enabled" << std::endl;
  //} else {
  //std::cout << "********** SECCOMP FILTER DISABLED *********** " << std::endl;
  // }


  char** temp_args = new char* [my_args.size()+2];   //memory leak here
  temp_args[0] = (char*) my_program.c_str();
  for (int i = 0; i < my_args.size(); i++) {
    //std::cout << "'" << my_args[i] << "' ";
    temp_args[i+1] = (char*) my_args[i].c_str();
  }
  temp_args[my_args.size()+1] = (char *)NULL;

  char** const my_char_args = temp_args;

  //std::cout << std::endl << std::endl;


  // print out the command line to be executed
  std::cout << "going to exec: ";
  for (int i = 0; i < my_args.size()+1; i++) {
    std::cout << my_char_args[i] << " ";
  }
  std::cout << std::endl;


  /*************************************************
  *
  * APPLY RLIMITS / SET PROCESS GROUP
  *
  **************************************************/

  enable_all_setrlimit(program_name,test_case_limits,assignment_limits);

  // Student's shouldn't be forking & making threads/processes...
  // but if they do, let's set them in the same process group
  int pgrp = setpgid(getpid(), 0);
  assert(pgrp == 0);

  /*************************************************
  *
  * REDIRECT STDIN/OUT/ERR
  *
  **************************************************/

  // FIXME: if we want to assert or print stuff afterward, we should save
  // the originals and restore after the exec fails.
  if (my_stdin != "") {
    std::cout << "PIPED STDIN FILE DETECTED. DISPATCHER ACTIONS WILL BE IGNORED." << std::endl;
    int new_stdinfd  = open(my_stdin.c_str()  , O_RDONLY );
    int stdinfd = fileno(stdin);
    close(stdinfd);
    dup2(new_stdinfd, stdinfd);
  }

  int my_pipe[2];
  int stdoutfd = fileno(stdout);
  int pid = -1;
  int READ_END = 0;
  int WRITE_END = 1;

  if (my_stdout != "") {
    // If we aren't timestamping, STDOUT goes straight to a file.
    if(!timestamped_stdout){
      int new_stdoutfd = open(my_stdout.c_str(), O_WRONLY|O_CREAT|O_APPEND, everyone_read);
      close(stdoutfd);
      dup2(new_stdoutfd, stdoutfd);
    }
    // If we ARE timestamping, we send our stdout to a pipe for processing by a child process.
    else{
      pipe(my_pipe);
      pid = fork();
      // If we are the child
      if(pid == 0){
        close(my_pipe[WRITE_END]);
        timestamp_stdout( my_stdout, my_pipe[READ_END]);
      }
    }


  }
  if (my_stderr != "") {
    int new_stderrfd = open(my_stderr.c_str(), O_WRONLY|O_CREAT|O_APPEND, everyone_read);
                     //creat(my_stderr.c_str(), everyone_read );
    int stderrfd = fileno(stderr);
    close(stderrfd);
    dup2(new_stderrfd, stderrfd);
  }








  /*************************************************
  *
  * SET UP THE PATH
  *
  **************************************************/


  // The path is probably empty, we need to add /usr/bin to the path
  // since we get a "collect2 ld not found" error from g++ otherwise
  char* my_path = getenv("PATH");
  if (my_path != NULL) {
    // std::cout << "WARNING: PATH NOT EMPTY, PATH= " << (my_path ? my_path : "<empty>") << std::endl;
  }
  setenv("PATH", "/usr/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/sbin:/bin", 1);

  my_path = getenv("PATH");

  // std::cout << "PATH post= " << (my_path ? my_path : "<empty>") << std::endl;

  // set the locale so that special characters (e.g., the copyright
  // symbol) are not interpreted as ascii
  setenv("LC_ALL", "en_US.UTF-8", 1);


  // Set a default for OpenMP desired threads
  // Can be overridden by student to be higher or lower.
  // Instructor still controls the RLIMIT_NPROC max threads.
  // (without this, default desired threads may be based on the system specs)
  setenv("OMP_NUM_THREADS","4",1);


  // Set an environment variable to override the defaults for the
  // initial java virtual machine heap (xms) and maximum virtual
  // machine heap.
  setenv("JAVA_TOOL_OPTIONS","-Xms128m -Xmx256m",1);
  // NOTE: Instructors can still override this setting with the
  // command line.  E.g.,
  //    java -Xms128m -Xmx256m -cp . MyProgram


  // Haskell compiler needs a home environment variable (but it can be anything)
  setenv("HOME","/tmp",1);


  // print this out here (before losing our output)
  //  if (SECCOMP_ENABLED != 0) {
  // std::cout << "going to install syscall filter for " << my_program << std::endl;
  //}

  /*************************************************
  *
  * APPLY SECCOMP
  *
  **************************************************/

  // SECCOMP: install the filter (system calls restrictions)
  if (install_syscall_filter(prog_is_32bit, my_program,logfile, whole_config)) {
    logfile << "seccomp filter install failed" << std::endl;
    return 1;
  }
  // END SECCOMP


  /*************************************************
  *
  * RUN (execv) THE STUDENT CODE
  *
  **************************************************/

  if(timestamped_stdout){
    close(my_pipe[READ_END]);
    close(stdoutfd);
    dup2(my_pipe[WRITE_END], stdoutfd);
  }



  // TO AVOID CREATING EXTRA LAYERS OF PROCESSES, USE EXEC RATHER THAN SYSTEM OR THE SHELL
  int child_result =  execv ( my_program.c_str(), my_char_args );


  // if exec does not fail, we'll never get here

  umask(prior_umask);  // reset to the prior umask
  //Stop the timestamp process if it exists.
  if(pid != -1 && timestamped_stdout){
    close(my_pipe[WRITE_END]);
    close(stdoutfd);
  }

  return child_result;
}


// =====================================================================================
// =====================================================================================

std::string output_of_system_command(const char* cmd) {
    char buffer[128];
    std::string result = "";
    std::shared_ptr<FILE> pipe(popen(cmd, "r"), pclose);
    if (!pipe) throw std::runtime_error("popen() failed!");
    while (!feof(pipe.get())) {
        if (fgets(buffer, 128, pipe.get()) != NULL)
            result += buffer;
    }
    return result;
}

int resident_set_size(int childPID) {
  // get all of the processes owned by the current user (untrustedXX)
  std::string command = std::string("ps xw o user:15,pid:10,rss:10,cmd | grep untrusted");

  // for debugging, print this output to the log
  //std::cout << "system ( '" + command + "' )" << std::endl;
  //system (command.c_str());
  // now sum up the resident set size column of the output
  std::string command2 = command + " | awk '{ sum += $3 } END { print sum }'";
  std::string output = output_of_system_command(command2.c_str());
  std::stringstream ss(output);
  int mem;

  if (ss >> mem) {
    return mem;
  };
  return -1;
}



void TerminateProcess(float &elapsed, int childPID) {
  static int kill_counter = 0;
  // the '-' here means to kill the group
  int success_kill_a = kill(childPID, SIGKILL);
  int success_kill_b = kill(-childPID, SIGKILL);
  kill_counter++;
  if (success_kill_a != 0 || success_kill_b != 0) {
    std::cout << "ERROR! kill pid " << childPID << " was not successful" << std::endl;
  }
  if (kill_counter >= 5) {
    std::cout << "ERROR! kill counter for pid " << childPID << " is " << kill_counter << std::endl;
    std::cout << "  Check /var/log/syslog (or other logs) for possible kernel bug \n"
        << "  or hardware bug that is preventing killing this job. " << std::endl;
  }
  usleep(10000); /* wait 1/100th of a second for the process to die */
  elapsed+=0.001;
}

//Thread function for dispatcher actions
void cin_reader(std::mutex* lock, std::queue<std::string>* input_queue, bool* CHILD_NOT_TERMINATED){
  std::cout << "thread function " << getpid() << std::endl;
  std::string my_string;

  std::ofstream dispatched_actions_file;

  dispatched_actions_file.open ("dispatched_actions.txt", std::ofstream::app);

  try
  {
    struct pollfd fds;
    int ret;
    fds.fd = 0; /* this is STDIN */
    fds.events = POLLIN;

    while(*CHILD_NOT_TERMINATED) {
      ret = poll(&fds, 1, 100); //.1 second timeout

      if (ret > 0){
        std::getline (std::cin,my_string);
        std::cout << "Cin received: " << my_string << std::endl;

        lock->lock();
        input_queue->push(my_string);
        lock->unlock();
        dispatched_actions_file << getTimestamp() << my_string  << std::endl;
      }
    }
  }
  catch (const std::exception &exc)
  {
      // catch anything thrown within try block that derives from std::exception
      std::cout << exc.what();
  }

  std::cout << "exiting thread function" << std::endl;
}

// From https://gist.github.com/bschlinker/844a88c09dcf7a61f6a8df1e52af7730
std::string getTimestamp() {
  // get a precise timestamp as a string
  const auto now = std::chrono::system_clock::now();
  const auto nowAsTimeT = std::chrono::system_clock::to_time_t(now);
  const auto nowMs = std::chrono::duration_cast<std::chrono::milliseconds>(
      now.time_since_epoch()) % 1000;
  std::stringstream nowSs;
  nowSs
      << std::put_time(std::localtime(&nowAsTimeT), "%Y/%m/%d %T")
      << '.' << std::setfill('0') << std::setw(3) << nowMs.count() << " ";
  return nowSs.str();
}

//Thread function for timestamping stdout
void timestamp_stdout(std::string filename, int pipe){

  std::ofstream stdout_file;
  std::ofstream timestamped_stdout;

  stdout_file.open (filename.c_str());

  size_t pos = filename.find(".txt");
  filename.erase(pos, filename.length());
  std::string timestamped_filename = filename + "_TIMESTAMPED.txt";
  timestamped_stdout.open (timestamped_filename.c_str());

  try
  {
    char ch;
    std::string s;
    FILE *stream = fdopen (pipe, "r");
    while ((ch = fgetc (stream)) != EOF){
      if (ch != '\n')
          s.push_back(ch);
      else
      {
        stdout_file << s << std::endl;
        timestamped_stdout << getTimestamp() << s  << std::endl;
        s.clear();
      }
    }
    //Make sure we're not leaving anything in the buffer
    if(s.length() > 0){
      stdout_file << s << std::endl;
      timestamped_stdout << getTimestamp() << s  << std::endl;
    }
    fclose (stream);
  }
  catch (const std::exception &exc)
  {
      std::cout << "Exception thrown" << std::endl;
      // catch anything thrown within try block that derives from std::exception
      std::cout << exc.what();
  }


  stdout_file.close();
  timestamped_stdout.close();
  exit(0);
}


// Executes command (from shell) and returns error code (0 = success)
int execute(const std::string &cmd,
      const std::vector<nlohmann::json> actions,
      const std::vector<nlohmann::json> dispatcher_actions,
      const std::string &execute_logfile,
      const nlohmann::json &test_case_limits,
      const nlohmann::json &assignment_limits,
      const nlohmann::json &whole_config,
      const bool windowed,
      const std::string display_variable2,
      const bool timestamped_stdout) {

  if (windowed == false) {
    std::cout << "Execution has no graphics display" << std::endl;
  } else {
    std::cout << "Graphics display is enabled and set to: '" << display_variable2 << "'" << std::endl;
  }

  std::string display_variable = display_variable2;
  if (display_variable == "NO_DISPLAY_SET") {
    display_variable = ":0";
  }


  std::set<std::string> invalid_windows;
  bool window_mode = windowed; //Tells us if the process is expected to spawn a window. (additional support later)

  int num_dispatched_actions = dispatcher_actions.size();

  //Dispatcher actions variables
  std::mutex lock;
  std::queue<std::string> input_queue;

  //check if there are any actions present which require a window.
  if(actions.size() > 0){
    std::cout << "Received " << actions.size() << " actions" << std::endl; //useful debug line.

    for(std::vector<nlohmann::json>::const_iterator it = actions.begin(); it != actions.end(); ++it){
      if(isWindowedAction(*it)){
        window_mode = true;
        break;
      }
    }
  }

  std::cout << "IN EXECUTE:  '" << cmd << "'" << std::endl;
  std::cout << "identified " << dispatcher_actions.size() << " dispatcher actions" << std::endl;

  std::ofstream logfile(execute_logfile.c_str(), std::ofstream::out | std::ofstream::app);

  //If we want windowed mode, but there is no display set.
  if(window_mode && display_variable == "NO_DISPLAY_SET"){
    std::cout << "ERROR: Attempting to grade a windowed gradeable with no display variable set." << std::endl;
    logfile << "ERROR: Attempting to grade a windowed gradeable with no display variable set." << std::endl;
    return -1;
  }

  if(window_mode){
    std::cout << "Window mode activated." << std::endl;
    std::cout << "Going to setenv DISPLAY to " << display_variable << std::endl;
    setenv("DISPLAY", display_variable.c_str(), 1);
    invalid_windows = snapshotOfActiveWindows();
  }

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  int time_kill=0;
  int memory_kill=0;

  //used to pipe cin to the running student process (dispatcher_actions)
  int dispatcherpipe[2];
  pipe(dispatcherpipe);

  //used to synchronize pipe setup.
  lock.lock();
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);

  std::string program_name = get_program_name(cmd,whole_config);
  int seconds_to_run = get_the_limit(program_name,RLIMIT_CPU,test_case_limits,assignment_limits);

  int allowed_rss_memory = get_the_limit(program_name,RLIMIT_RSS,test_case_limits,assignment_limits);

  if (childPID == 0) {

    if(num_dispatched_actions > 0){
      close(dispatcherpipe[1]); //close write end of the pipe
      close(0); //close stdin
      dup2(dispatcherpipe[0], 0); //copy read end of the pipe onto stdin.
      close(dispatcherpipe[0]); // close read end of the pipe
    }
    int child_result = exec_this_command(cmd,logfile,whole_config, program_name,test_case_limits,assignment_limits, timestamped_stdout);

    // send the system status code back to the parent process
    //std::cout << "    child_result = " << child_result << std::endl;
    exit(child_result);
  }
  else {
      // PARENT PROCESS
      std::thread cin_thread;
      bool CHILD_NOT_TERMINATED = true;

      bool dispatcher_actions_ended = true;
      bool allowed_to_die = false;
      bool override = false;
      if(num_dispatched_actions > 0){
        dispatcher_actions_ended = false;
        close(dispatcherpipe[0]); // close the read end of the pipe
        cin_thread = std::thread(cin_reader, &lock, &input_queue, &CHILD_NOT_TERMINATED);
      }

      std::cout << "childPID = " << childPID << std::endl;
      std::cout << "PARENT PROCESS START: " << std::endl;
      //allow the cin thread to begin reading.
      lock.unlock();
      int parent_result = system("date");
      assert (parent_result == 0);
      //std::cout << "  parent_result = " << parent_result << std::endl;
      float elapsed = 0;
      int status;
      pid_t wpid = 0;
      float next_checkpoint = 0;
      std::string windowName;
      int rss_memory = 0;
      int max_rss_memory = 0;
      int actions_taken = 0;
      do {
          //dispatcher actions
          if(!input_queue.empty()){
            lock.lock();
            std::string popped = input_queue.front();
            std::string popped_nl = popped + "\n";
            input_queue.pop();
            lock.unlock();

            char piped_message[popped_nl.length()];
            strncpy(piped_message, popped_nl.c_str(),popped_nl.length()); //ignore the null byte

            if(popped == "SUBMITTY_SIGNAL:STOP"){
              std::cout << "Sending interrupt to student process." << std::endl;
              kill(childPID, SIGINT);
              kill(-childPID, SIGINT);
              close(dispatcherpipe[1]);
            }else if(popped == "SUBMITTY_SIGNAL:START"){
              //If the child is still alive, terminate it.
              if(wpid == 0){
                kill(childPID, SIGINT);
                kill(-childPID, SIGINT);
                close(dispatcherpipe[1]);
              }
              pipe(dispatcherpipe);
              childPID = fork();
              // ensure fork was successful
              assert (childPID >= 0);

              if (childPID == 0) {
                // CHILD PROCESS
                //enable_all_setrlimit(program_name,test_case_limits,assignment_limits);

                // Student's shouldn't be forking & making threads/processes...
                // but if they do, let's set them in the same process group
                int pgrp = setpgid(getpid(), 0);
                assert(pgrp == 0);

                if(num_dispatched_actions > 0){
                  close(dispatcherpipe[1]); //close write end of the pipe
                  close(0); //close stdin
                  dup2(dispatcherpipe[0], 0); //copy read end of the pipe onto stdin.
                  close(dispatcherpipe[0]); // close read end of the pipe
                }
                int child_result;
                child_result = exec_this_command(cmd,logfile,whole_config, program_name,test_case_limits,assignment_limits,timestamped_stdout);

                // send the system status code back to the parent process
                //std::cout << "    child_result = " << child_result << std::endl;
                exit(child_result);
              }else{
                close(dispatcherpipe[0]);
              }
            }else if(popped == "SUBMITTY_SIGNAL:KILL"){
              std::cout << "Sending SIGKILL to student process" << std::endl;
              int k  = kill(childPID, SIGKILL);
              int k2 = kill(-childPID, SIGKILL);
              close(dispatcherpipe[1]);
            }
            else if(popped == "SUBMITTY_SIGNAL:FINALMESSAGE"){
              std::cout << "The dispatcher actions are complete." << std::endl;
              dispatcher_actions_ended = true;
            }else{
              write(dispatcherpipe[1], piped_message, strlen(popped_nl.c_str()));
              std::cout << "Writing to student stdin: " << popped << std::endl;
            }
          }

          if(window_mode && windowName == ""){ //if we are expecting a window, but know nothing about it
            initializeWindow(windowName, childPID, invalid_windows, elapsed); //attempt to get information about the window
            if(windowName != ""){ //if we found information about the window
              delay_and_mem_check(100000, childPID, elapsed, next_checkpoint, seconds_to_run,
                                    rss_memory, max_rss_memory, allowed_rss_memory, memory_kill,
                                    time_kill, logfile);
              moveMouseToOrigin(windowName);
              centerMouse(windowName); //center our mouse on its screen
            }
          }
           //If we had a window but it no longer exists (crashed/shut)
          else if(window_mode && windowName != "" && !windowExists(windowName)){
            windowName = "";  //reset it's name to nothing so we can begin searching again.
            std::cout << "The students window shut midrun." << std::endl;
          }
          // sleep 1/10 of a second
          wpid = waitpid(childPID, &status, WNOHANG);

          //if the student process is alive.
          if (wpid == 0){
            // monitor time & memory usage
            if (!time_kill && !memory_kill){
              //if we expect a window, and the window exists, and we still have actions to take
              if(window_mode && windowName != "" && windowExists(windowName) && actions_taken < actions.size()){
                takeAction(actions, actions_taken, windowName,
                  childPID, elapsed, next_checkpoint, seconds_to_run, rss_memory, allowed_rss_memory,
                  memory_kill, time_kill, logfile); //Takes each action on the window. Requires delay parameters to do delays.
              }
              //If we do not expect a window and we still have actions to take
              else if(!window_mode && actions_taken < actions.size()){
                takeAction(actions, actions_taken, windowName,
                  childPID, elapsed, next_checkpoint, seconds_to_run, rss_memory, allowed_rss_memory,
                  memory_kill, time_kill, logfile); //Takes each action on the window. Requires delay parameters to do delays.
              }
              //if we are out of actions or there were none, delay 1/10th second.
              else{
                delay_and_mem_check(100000, childPID, elapsed, next_checkpoint, seconds_to_run,
                                    rss_memory, max_rss_memory, allowed_rss_memory, memory_kill,
                                    time_kill, logfile);
              }
            }
         }else if(!dispatcher_actions_ended){ //keep on performing checks even if we killed the child process (for dispatcher actions).
            delay_and_mem_check(100000, childPID, elapsed, next_checkpoint, seconds_to_run,
                                rss_memory, max_rss_memory, allowed_rss_memory, memory_kill,
                                time_kill, logfile);
            //this wpid is necessary to make sure allowed_to_die is up to date.
         }
         wpid = waitpid(childPID, &status, WNOHANG);
         //If the dispatcher actions have ended and the process is dead, we can terminate.
         allowed_to_die = dispatcher_actions_ended && wpid != 0;
         // If we have received a time or memory kill, we must halt even if the dispatcher actions are ongoing.
         override = time_kill || memory_kill;
      } while (!allowed_to_die && !override);

      if (WIFEXITED(status)) {
        std::cout << "Child exited, status=" << WEXITSTATUS(status) << std::endl;
        if (WEXITSTATUS(status) == 0){
            result=0;
        }
        else{
          logfile << "Child exited with status = " << WEXITSTATUS(status) << std::endl;
          result=1;
          //
          // NOTE: If wrapping /usr/bin/time around a program that exits with signal = 25
          //       time exits with status = 153 (128 + 25)
          //
          if (WEXITSTATUS(status) > 128 && WEXITSTATUS(status) <= 256) {
            OutputSignalErrorMessageToExecuteLogfile(  WEXITSTATUS(status)-128,logfile );
          }
        }
      }
      else if (WIFSIGNALED(status)) {
          int what_signal =  WTERMSIG(status);
          OutputSignalErrorMessageToExecuteLogfile(what_signal,logfile);
          std::cout << "Child " << childPID << " was terminated with a status of: " << what_signal << std::endl;
          if (WTERMSIG(status) == 0){
            result=0;
          }
          else{
            result=2;
          }
      }
      if (time_kill){
       logfile << "ERROR: Maximum run time exceeded" << std::endl;
       logfile << "Program Terminated" << std::endl;
       result=3;
      }
      if (memory_kill){
       logfile << "ERROR: Maximum RSS (RAM) exceeded" << std::endl;
       logfile << "Program Terminated" << std::endl;
       result=3;
      }

      nlohmann::json metrics;
      metrics["elapsed_time"] = elapsed;
      metrics["max_rss_size"] = max_rss_memory;
      std::ofstream json_file("submitty_metrics.json");
      json_file << metrics.dump(4);

      std::cout << "PARENT PROCESS COMPLETE: " << std::endl;
      parent_result = system("date");
      assert (parent_result == 0);

      CHILD_NOT_TERMINATED = false;
      if(num_dispatched_actions > 0){
         std::cout <<"Terminating Thread"<<std::endl;
        cin_thread.join();
      }
    }

    logfile.close();
    std::cout <<"Result: "<<result<<std::endl;
    return result;
}


// =====================================================================================
// =====================================================================================


/**
* Tests to see if the student has used too much memory.
*/
bool memory_ok(int rss_memory, int allowed_rss_memory, std::ostream &logfile){
  if(rss_memory > allowed_rss_memory){
      return false;
  }
  else{
      return true;
  }
}

/**
* Tests to see if the student has used too much time.
*/
bool time_ok(float elapsed, float seconds_to_run, std::ostream &logfile){
  // allow 10 extra seconds for differences in wall clock
  // vs CPU time (imperfect solution)
  if(elapsed > seconds_to_run + CPU_TO_WALLCLOCK_TIME_BUFFER){
      return false;
  }
  else{
      return true;
  }
}

/**
* Delays for a number of microseconds, checking the student's memory and time consumption at intervals.
*/
bool delay_and_mem_check(float sleep_time_in_microseconds, int childPID, float &elapsed, float& next_checkpoint,
                float seconds_to_run, int& rss_memory, int& max_rss_memory, int allowed_rss_memory, int& memory_kill,
                int& time_kill, std::ostream &logfile){
  float time_left = sleep_time_in_microseconds;
  while(time_left > 0){
    if(time_left > 100000){ //while we have more than 1/10th second left.
      time_left -= 100000; //decrease the amount of time left by 1/10th of a second.
      usleep(100000); //sleep for 1/10th of a second
      elapsed += .1; //and increment time elapsed by 1/10th second.
    }
    else{ //otherwise, if there is less than 1/10th second left
      usleep(time_left); //sleep for that amount of time
      elapsed+=time_left/1000000.0f; //Increment elapsed by the amount of time (in seconds)
      time_left = 0.0f; //and set time left to be zero.
    }
    if (elapsed >= next_checkpoint){ //if it is time to update our knowledge of the student's memory usage, do so.
      rss_memory = resident_set_size(childPID);
      max_rss_memory = std::max(max_rss_memory, rss_memory);
      std::cout << "time elapsed = " << elapsed << " seconds,  memory used = " << rss_memory << " kb" << std::endl;
      next_checkpoint = std::min(elapsed+5.0,elapsed*2.0);
    }

    if (!time_ok(elapsed, seconds_to_run,logfile)) { //If the student's program ran too long
      // terminate for excessive time
      std::cout << "Killing child process " << childPID << " after " << elapsed << " seconds elapsed." << std::endl;
      TerminateProcess(elapsed,childPID); //kill it.
      time_kill=1;
      return true;
    }
    if (!memory_ok(rss_memory, allowed_rss_memory,logfile)){ //if the student's program used too much memory
      // terminate for excessive memory usage (RSS = resident set size = RAM)
      memory_kill=1;
      TerminateProcess(elapsed,childPID); //kill it.
      std::cout << "Killing child process " << childPID << " for using " << rss_memory << " kb RAM.  (limit is " << allowed_rss_memory << " kb)" << std::endl;
      return true;
    }
  }
  return false;
}
