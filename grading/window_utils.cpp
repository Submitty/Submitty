#include <sys/time.h>
#include <sys/resource.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <signal.h>
#include <unistd.h>
#include <fcntl.h>
#include <dirent.h>
#include <vector>

#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>
#include <map>
#include <regex>


// for system call filtering
//#include <seccomp.h>
//#include <elf.h>

//#include "TestCase.h"
//#include "execute.h"
//#include "error_message.h"


#define DIR_PATH_MAX 1000


// defined in seccomp_functions.cpp


#define SUBMITTY_INSTALL_DIRECTORY  std::string("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__")


// =====================================================================================
// =====================================================================================


// bool system_program(const std::string &program, std::string &full_path_executable) {

//   const std::map<std::string,std::string> allowed_system_programs = {

//     // Basic System Utilities (for debugging)
//     { "ls",                      "/bin/ls", },
//     { "time",                    "/usr/bin/time" },
//     { "mv",                      "/bin/mv" },
//     { "cp",                      "/bin/cp" },
//     { "chmod",                   "/bin/chmod" },
//     { "find",                    "/usr/bin/find" },
//     { "cat",                     "/bin/cat" },
//     { "compare",                 "/usr/bin/compare" }, //image magick!
//     { "mogrify",                 "/usr/bin/mogrify" }, //image magick!
//     { "cut",                     "/usr/bin/cut" },
//     { "sort",                    "/usr/bin/sort" },
//     { "grep",                    "/bin/grep" },
//     { "sed",                     "/bin/sed" },
//     { "pdftotext",               "/usr/bin/pdftotext" },
//     { "wc",                      "/usr/bin/wc" },
//     { "head",                    "/usr/bin/head" },
//     { "tail",                    "/usr/bin/tail" },

//     // Submitty Analysis Tools
//     { "submitty_count_token",    SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_token" },
//     { "submitty_count_node",     SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_node" },
//     { "submitty_count_function", SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_function" },

//     // for Computer Science I
//     { "python",                  "/usr/bin/python" },
//     { "python2",                 "/usr/bin/python2" },
//     { "python2.7",               "/usr/bin/python2.7" },
//     { "python3",                 "/usr/bin/python3" },
//     { "python3.4",               "/usr/bin/python3.4" },
//     { "python3.5",               "/usr/bin/python3.5" },

//     // for Data Structures
//     { "g++",                     "/usr/bin/g++" },
//     { "clang++",                 "/usr/bin/clang++" },
//     { "drmemory",                SUBMITTY_INSTALL_DIRECTORY+"/drmemory/bin/drmemory" },
//     { "valgrind",                "/usr/bin/valgrind" },

//     // for Computer Organization
//     { "spim",                    "/usr/bin/spim" },
//     { "clang",                   "/usr/bin/clang" },

//     // for Principles of Software
//     { "java",                    "/usr/bin/java" },
//     { "javac",                   "/usr/bin/javac" },

//     // for Operating Systems
//     { "gcc",                     "/usr/bin/gcc" },
//     { "strings",                 "/usr/bin/strings" },

//     // for Programming Languages
//     { "swipl",                   "/usr/bin/swipl" },
//     { "plt-r5rs",                "/usr/bin/plt-r5rs" },

//     // for Cmake & Make
//     { "cmake",                   "/usr/bin/cmake" },
//     { "make",                    "/usr/bin/make" },

//     // for Network Programming
//     { "timeout",                 "/usr/bin/timeout" },
//     { "mpicc.openmpi",           "/usr/bin/mpicc.openmpi" },
//     { "mpirun.openmpi",          "/usr/bin/mpirun.openmpi" }

//   };

//   // find full path name
//   std::map<std::string,std::string>::const_iterator itr = allowed_system_programs.find(program);
//   if (itr != allowed_system_programs.end()) {
//     full_path_executable = itr->second;
//     return true;
//   }

//   // did they already use the full path name?
//   for (itr = allowed_system_programs.begin(); itr != allowed_system_programs.end(); itr++) {
//     if (itr->second == program) {
//       full_path_executable = program;
//       return true;
//     }
//   }

//   // not an allowed system program
//   return false;
// }


// bool local_executable (const std::string &program) {
//   assert (program.size() >= 1);
//   if (program.size() > 4 &&
//       program.substr(0,2) == "./" &&
//       program.substr(program.size()-4,4) == ".out" &&
//       program.substr(2,program.size()-2).find("/") == std::string::npos) {
//     return true;
//   }
//   return false;
// }


// std::string validate_program(const std::string &program) {
//   std::string full_path_executable;
//   assert (program.size() >= 1);
//   if (system_program(program,full_path_executable)) {
//     return full_path_executable;
//   } else if (local_executable(program)) {
//     return program;
//   } else {
//     std::cout << "ERROR: program looks suspicious '" << program << "'" << std::endl;
//     std::cerr << "ERROR: program looks suspicious '" << program << "'" << std::endl;
//     exit(1);
//   }
// }


// void validate_filename(const std::string &filename) {
//   assert (filename.size() >= 1);
//   if (filename[0] == '-') {
//     std::cout << "WARNING: command line filename looks suspicious '" << filename << "'" << std::endl;
//     std::cerr << "WARNING: command line filename looks suspicious '" << filename << "'" << std::endl;
//     // note: cannot enforce this when running drmemory on a program with options :(
//     //exit(1);
//   }
// }


// std::string validate_option(const std::string &program, const std::string &option) {
//   const std::map<std::string,std::map<std::string,std::string> > option_replacements = {
//     { "/usr/bin/javac",
//       { { "submitty_emma.jar",      SUBMITTY_INSTALL_DIRECTORY+"/JUnit/emma.jar" },
//         { "submitty_junit.jar",     SUBMITTY_INSTALL_DIRECTORY+"/JUnit/junit-4.12.jar" },
//         { "submitty_hamcrest.jar",  SUBMITTY_INSTALL_DIRECTORY+"/JUnit/hamcrest-core-1.3.jar" },
//         { "submitty_junit/",        SUBMITTY_INSTALL_DIRECTORY+"/JUnit/" }
//       }
//     },
//     { "/usr/bin/java",
//       { { "submitty_emma.jar",      SUBMITTY_INSTALL_DIRECTORY+"/JUnit/emma.jar" },
//         { "submitty_junit.jar",     SUBMITTY_INSTALL_DIRECTORY+"/JUnit/junit-4.12.jar" },
//         { "submitty_hamcrest.jar",  SUBMITTY_INSTALL_DIRECTORY+"/JUnit/hamcrest-core-1.3.jar" },
//         { "submitty_junit/",        SUBMITTY_INSTALL_DIRECTORY+"/JUnit/" }
//       }
//     }
//     };

//   // see if this program has option replacements
//   std::map<std::string,std::map<std::string,std::string> >::const_iterator itr = option_replacements.find(program);
//   if (itr != option_replacements.end()) {
//     std::string answer = option;
//     // loop over all replacements and see if any match
//     std::map<std::string,std::string>::const_iterator itr2;
//     for (itr2 = itr->second.begin(); itr2 != itr->second.end(); itr2++) {
//       std::string pattern = itr2->first;
//       std::string::size_type pos = answer.find(pattern);
//       if (pos == std::string::npos) continue;
//       std::string before = answer.substr(0,pos);
//       std::string after =  answer.substr(pos+pattern.length(),answer.length()-pos-pattern.length());
//       answer = before + itr2->second + after;
//     }
//     if (option != answer) {
//       std::cout << "REPLACE OPTION '" << option << "' with '" << answer << "'" << std::endl;
//     }
//     return answer;
//   }

//   // otherwise, just use the option
//   return option;

//   /*
//   static std::string last_option = "";
//   assert (option.size() >= 1);
//   if (option[0] == '-') {
//     // probably a normal option
//   } else if (last_option == "-o" &&
//          option.size() > 4 &&
//          option.substr(option.size()-4,4) == ".out") {
//     // ok, it's an executable name
//   } else if (local_executable(program)) {
//     // custom
//   } else {
//     std::cout << "WARNING: command line option looks suspicious '" << option << "'" << std::endl;
//     std::cerr << "WARNING: command line option looks suspicious '" << option << "'" << std::endl;
//     //exit(1);
//   }
//   last_option = option;
//   */
// }

// // =====================================================================================

// std::string replace_slash_with_double_underscore(const std::string& input) {
//   std::string answer;
//   for (int i = 0; i < input.size(); i++) {
//     if (input[i] != '/') answer.push_back(input[i]);
//     else {
//       answer.push_back('_');
//       answer.push_back('_');
//     }
//   }
//   return answer;
// }

// std::string escape_spaces(const std::string& input) {
//   std::string answer;
//   for (int i = 0; i < input.size(); i++) {
//     if (input[i] != ' ') answer.push_back(input[i]);
//     else {
//       answer.push_back('\\');
//       answer.push_back(' ');
//     }
//   }
//   return answer;
// }

// // =====================================================================

// bool wildcard_match(const std::string &pattern, const std::string &thing, std::ostream &logfile) {
//   //  std::cout << "WILDCARD MATCH? " << pattern << " " << thing << std::endl;

//   int wildcard_loc = pattern.find("*");
//   assert (wildcard_loc != std::string::npos);

//   std::string before = pattern.substr(0,wildcard_loc);
//   std::string after = pattern.substr(wildcard_loc+1,pattern.size()-wildcard_loc-1);

//   //  std::cout << "BEFORE " << before << " AFTER" << after << std::endl;

//   // FIXME: we only handle a single wildcard!
//   assert (after.find("*") == std::string::npos);
//   assert (before.find("*") == std::string::npos);

//   if (thing.size() < after.size()+before.size()) return false;

//   std::string thing_before = thing.substr(0,wildcard_loc);
//   std::string thing_after = thing.substr(thing.size()-after.size(),after.size());

//   //  std::cout << "THINGBEFORE " << thing_before << " THINGAFTER" << thing_after << std::endl;

//   if (before == thing_before && after == thing_after) {
//     //std::cout << "RETURN TRUE" << std::endl;
//     return true;
//   }

//   //std::cout << "return false" << std::endl;
//   return false;
// }


// void wildcard_expansion(std::vector<std::string> &my_finished_args, const std::string &full_pattern, std::ostream &logfile) {

//   //std::cout << "IN WILDCARD EXPANSION " << full_pattern << std::endl;

//   // if the pattern does not contain a wildcard, just return that
//   if (full_pattern.find("*") == std::string::npos) {
//     my_finished_args.push_back(full_pattern);
//     return;
//   }

//   std::vector<std::string> my_args;

//   //  std::cout << "WILDCARD DETECTED:" << full_pattern << std::endl;

//   // otherwise, if our pattern contains directory structure, first remove that
//   std::string directory = "";
//   std::string file_pattern = full_pattern;
//   while (1) {
//     size_t location = file_pattern.find("/");
//     if (location == std::string::npos) { break; }
//     directory += file_pattern.substr(0,location+1);
//     file_pattern = file_pattern.substr(location+1,file_pattern.size()-location-1);
//   }
//   std::cout << "  directory: " << directory << std::endl;
//   std::cout << "  file_pattern " << file_pattern << std::endl;

//   // FIXME: could extend this to allow a wildcard in the directory name
//   // confirm that the directory does not contain a wildcard
//   assert (directory.find("*") == std::string::npos);
//   // confirm that the file pattern does contain a wildcard
//   assert (file_pattern.find("*") != std::string::npos);

//   int count_matches = 0;

//   // combine the current directory plus the directory portion of the pattern
//   char buf[DIR_PATH_MAX];
//   getcwd( buf, DIR_PATH_MAX );
//   std::string d = std::string(buf)+"/"+directory;
//   // note: we don't want the trailing "/"
//   if (d.size() > 0 && d[d.size()-1] == '/') {
//     d = d.substr(0,d.size()-1);
//   }
//   DIR* dir = opendir(d.c_str());
//   if (dir != NULL) {

//     // loop over all files in the directory, see which ones match the pattern
//     struct dirent *ent;
//     while (1) {
//       ent = readdir(dir);
//       if (ent == NULL) break;
//       std::string thing = ent->d_name;
//       if (wildcard_match(file_pattern,thing,logfile)) {
//     std::cout << "   MATCHED!  '" << thing << "'" << std::endl;
//     validate_filename(directory+thing);
//     my_args.push_back(directory+thing);
//     count_matches++;
//       } else {
//     //std::cout << "   no match  '" << thing << "'" << std::endl;
//       }
//     }
//     closedir(dir);
//   }

//   if (count_matches == 0) {
//     std::cout << "ERROR: FOUND NO MATCHES" << std::endl;
//   }

//   // sort the matches, so things are deterministic (and unix wildcard is sorted)
//   std::sort(my_args.begin(),my_args.end());
//   my_finished_args.insert(my_finished_args.end(), my_args.begin(), my_args.end());
// }

// // =====================================================================================
// // =====================================================================================

// std::string get_program_name(const std::string &cmd) {
//   std::string my_program;
//   std::stringstream ss(cmd);

//   ss >> my_program;
//   assert (my_program.size() >= 1);

//   std::string full_path_executable = validate_program(my_program);
//   return full_path_executable;
// }


// void parse_command_line(const std::string &cmd,
//             std::string &my_program,
//             std::vector<std::string> &my_args,
//             std::string &my_stdin,
//             std::string &my_stdout,
//             std::string &my_stderr,
//             std::ofstream &logfile) {

//   std::cout << "PARSE COMMAND LINE " << cmd << std::endl;

//   my_args.clear();
//   my_program = my_stdin = my_stdout = my_stderr = "";

//   std::stringstream ss(cmd);
//   std::string token,token2;

//   while (ss >> token) {
//     assert (token.size() >= 1);

//     // handle escaped spaces in the command line
//     while (token.back() == '\\') {
//       token.pop_back();
//       token2 = " ";
//       ss >> token2;
//       token = token + ' ' + token2;
//     }

//     // grab the program name
//     if (my_program == "") {
//       assert (my_args.size() == 0);
//       // program name
//       my_program = validate_program(token);
//       assert (my_program != "");
//     }

//     // look for stdin/stdout/stderr
//     else if (token.substr(0,1) == "<") {
//       assert (my_stdin == "");
//       if (token.size() == 1) {
//         ss >> token; bool success = ss.good();
//         assert (success);
//         my_stdin = token;
//       } else {
//         my_stdin = token.substr(1,token.size()-1);
//       }
//       validate_filename(my_stdin);
//     }
//     else if (token.size() >= 2 && token.substr(0,2) == "1>") {
//       assert (my_stdout == "");
//       if (token.size() == 2) {
//         ss >> token; bool success = ss.good();
//         assert (success);
//         my_stdout = token;
//       } else {
//         my_stdout = token.substr(2,token.size()-2);
//       }
//       validate_filename(my_stdout);
//     }
//     else if (token.size() >= 2 && token.substr(0,2) == "2>") {
//       assert (my_stderr == "");
//       if (token.size() == 2) {
//         ss >> token; bool success = ss.good();
//         assert (success);
//         my_stderr = token;
//       } else {
//         my_stderr = token.substr(2,token.size()-2);
//       }
//       validate_filename(my_stderr);
//     }

//     // remainder of the arguments
//     else if (token.find("*") != std::string::npos) {
//       wildcard_expansion(my_args,token,logfile);
//     }

//     // special exclude file option
//     // FIXME: this is ugly, don't know how I want it to be done though
//     else if (token == "-EXCLUDE_FILE") {
//       ss >> token;
//       std::cout << "EXCLUDE THIS FILE " << token << std::endl;
//       for (std::vector<std::string>::iterator itr = my_args.begin();
//            itr != my_args.end(); ) {
//         if (*itr == token) {  std::cout << "FOUND IT!" << std::endl; itr = my_args.erase(itr);  }
//         else { itr++; }
//       }
//     }

//     else {
//       // validate_filename(token);
//       // validate_option(my_program,token);
//       //std::cout << "before TOKEN IS " << token << std::endl;
//       token = validate_option(my_program,token);
//       //std::cout << "after  TOKEN IS " << token << std::endl;
//       my_args.push_back(token);
//     }
//   }



//   // FOR DEBUGGING
//   std::cout << std::endl << std::endl;
//   std::cout << "MY PROGRAM: '" << my_program << "'" << std::endl;
//   std::cout << "MY STDIN:  '" << my_stdin  << "'" << std::endl;
//   std::cout << "MY STDOUT:  '" << my_stdout  << "'" << std::endl;
//   std::cout << "MY STDERR:  '" << my_stderr  << "'" << std::endl;
//   std::cout << "MY ARGS (" << my_args.size() << ") :";

// }

// // =====================================================================================
// // =====================================================================================
// // =====================================================================================

// void OutputSignalErrorMessageToExecuteLogfile(int what_signal, std::ofstream &logfile) {


// #if 0

//   // default message (may be overwritten with more specific message below)
//   std::stringstream ss;
//   ss << "ERROR: Child terminated with signal " << what_signal; 
//   std::string message = ss.str();
  
//   // reference: http://man7.org/linux/man-pages/man7/signal.7.html
//   if        (what_signal == SIGHUP    /*  1        Term  Hangup detected on controlling terminal or death of controlling process   */) {
//   } else if (what_signal == SIGINT    /*  2        Term  Interrupt from keyboard  */) {
//   } else if (what_signal == SIGQUIT   /*  3        Core  Quit from keyboard  */) {
//   } else if (what_signal == SIGILL    /*  4        Core  Illegal Instruction  */) {
//   } else if (what_signal == SIGABRT   /*  6        Core  Abort signal from abort(3)  */) {
//     message = "ERROR: ABORT SIGNAL";
//   } else if (what_signal == SIGFPE    /*  8        Core  Floating point exception  */) {
//     message = "ERROR: FLOATING POINT ERROR";
//   } else if (what_signal == SIGKILL   /*  9        Term  Kill signal  */) {
//     message = "ERROR: KILL SIGNAL";
//   } else if (what_signal == SIGSEGV   /* 11        Core  Invalid memory reference  */) {
//     message = "ERROR: INVALID MEMORY REFERENCE";
//   } else if (what_signal == SIGPIPE   /* 13        Term  Broken pipe: write to pipe with no readers  */) {
//   } else if (what_signal == SIGALRM   /* 14        Term  Timer signal from alarm(2)  */) {
//   } else if (what_signal == SIGTERM   /* 15        Term  Termination signal  */) {
//   } else if (what_signal == SIGUSR1   /* 30,10,16  Term  User-defined signal 1  */) {
//   } else if (what_signal == SIGUSR2   /* 31,12,17  Term  User-defined signal 2  */) {
//   } else if (what_signal == SIGCHLD   /* 20,17,18  Ign   Child stopped or terminated  */) {
//   } else if (what_signal == SIGCONT   /* 19,18,25  Cont  Continue if stopped  */) {
//   } else if (what_signal == SIGSTOP   /* 17,19,23  Stop  Stop process  */) {
//   } else if (what_signal == SIGTSTP   /* 18,20,24  Stop  Stop typed at terminal  */) {
//   } else if (what_signal == SIGTTIN   /* 21,21,26  Stop  Terminal input for background process  */) {
//   } else if (what_signal == SIGTTOU   /* 22,22,27  Stop  Terminal output for background process  */) {
//   } else if (what_signal == SIGBUS    /* 10,7,10   Core  Bus error (bad memory access)  */) {
//     message = "ERROR: BUS ERROR (BAD MEMORY ACCESS)";
//   } else if (what_signal == SIGPOLL   /*           Term  Pollable event (Sys V). Synonym for SIGIO  */) {
//   } else if (what_signal == SIGPROF   /* 27,27,29  Term  Profiling timer expired  */) {
//   } else if (what_signal == SIGSYS    /* 12,31,12  Core  Bad argument to routine (SVr4)  */) {
//     std::cout << "********************************\nDETECTED BAD SYSTEM CALL\n***********************************" << std::endl;
//     message = "ERROR: DETECTED BAD SYSTEM CALL, please report this error to submitty@cs.rpi.edu";
//   } else if (what_signal == SIGTRAP   /*  5        Core  Trace/breakpoint trap  */) {
//   } else if (what_signal == SIGURG    /* 16,23,21  Ign   Urgent condition on socket (4.2BSD)  */) {
//   } else if (what_signal == SIGVTALRM /* 26,26,28  Term  Virtual alarm clock (4.2BSD)  */) {
//   } else if (what_signal == SIGXCPU   /* 24,24,30  Core  CPU time limit exceeded (4.2BSD)  */) {
//     message = "ERROR: CPU TIME LIMIT EXCEEDED";
//   } else if (what_signal == SIGXFSZ   /* 25,25,31  Core  File size limit exceeded (4.2BSD  */) {
//     message = "ERROR: FILE SIZE LIMIT EXCEEDED";
//   } else {
//   }

//   // output message to behind-the-scenes logfile (stdout), and to execute logfile (available to students)
//   std::cout << message << std::endl;
//   logfile   << message << "\nProgram Terminated " << std::endl;
        
// }
// #endif


//     std::string message = RetrieveSignalErrorMessage(what_signal);


//     // output message to behind-the-scenes logfile (stdout), and to execute
//     // logfile (available to students)
//     std::cout << message << std::endl;
//     logfile   << message << "\nProgram Terminated " << std::endl;

// }

// // =====================================================================================
// // =====================================================================================


// This function only returns on failure to exec
int exec_this_command(const std::string &cmd) {

  // to avoid creating extra layers of processes, use exec and not
  // system or the shell

  // first we need to parse the command line
  // std::string my_program;
  // std::vector<std::string> my_args;
  // std::string my_stdin;
  // std::string my_stdout;
  // std::string my_stderr;
  // //parse_command_line(cmd, my_program, my_args, my_stdin, my_stdout, my_stderr, logfile);

  // std::string temp_args = "-input cube.obj";
  // char** temp_args = new char* [0+2];   //memory leak here
  //temp_args[0] = (char*) my_program.c_str();
  // // for (int i = 0; i < my_args.size(); i++) {
  // //   std::cout << "'" << my_args[i] << "' ";
  // //   temp_args[i+1] = (char*) my_args[i].c_str();
  // // }
  // // temp_args[my_args.size()+1] = (char *)NULL;
  // std::string tmp = " -input ../cube.obj";
  // char** newStuff = new char*[1];
  // char** const my_char_args = newStuff;

  // std::cout << std::endl << std::endl;


  // // print out the command line to be executed
  // std::cout << "going to exec: ";
  // for (int i = 0; i < my_args.size()+1; i++) {
  //   std::cout << my_char_args[i] << " ";
  // }
  // std::cout << std::endl;

  // // SECCOMP:  Used to restrict allowable system calls.
  // // First we determine if the program we will run is a 64 or 32 bit
  // // executable (the system calls are different on 64 vs. 32 bit)
  // Elf64_Ehdr elf_hdr;
  // std::cout << "reading " <<  my_program << std::endl;
  // int fd = open(my_program.c_str(), O_RDONLY);
  // if (fd == -1) {
  //   perror("can't open");
  //   std::cerr << "ERROR: cannot open program" << std::endl;
  //   exit(1);
  // }
  // int res = read(fd, &elf_hdr, sizeof(elf_hdr));
  // if (res < sizeof(elf_hdr)) {
  //   perror("can't read ");
  //   std::cerr << "ERROR: cannot read program" << std::endl;
  //   exit(1);
  // }
  // int prog_is_32bit;
  // if (elf_hdr.e_machine == EM_386) {
  //   std::cout << "this is a 32 bit program" << std::endl;
  //   prog_is_32bit = 1;
  // }
  // else {
  //   std::cout << "this is a 64 bit program" << std::endl;
  //   prog_is_32bit = 0;
  // }
  // // END SECCOMP





  // //if (SECCOMP_ENABLED != 0) {
  // std::cout << "seccomp filter enabled" << std::endl;
  // //} else {
  // //std::cout << "********** SECCOMP FILTER DISABLED *********** " << std::endl;
  // // }


  // // the default umask is 0027, so we need edit so that we can make
  // // these files 'other read', so that we can read them when we switch
  // // users
  // mode_t everyone_read = S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH;
  // mode_t prior_umask = umask(S_IWGRP | S_IWOTH);  // save the prior umask

  // // The path is probably empty, we need to add /usr/bin to the path
  // // since we get a "collect2 ld not found" error from g++ otherwise
  // char* my_path = getenv("PATH");
  // if (my_path == NULL) {
  //   setenv("PATH", "/usr/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/sbin:/bin", 1);
  // }
  // else {
  //   std::cout << "WARNING: PATH NOT EMPTY, PATH= " << (my_path ? my_path : "<empty>") << std::endl;
  // }

  // // set the locale so that special characters (e.g., the copyright
  // // symbol) are not interpreted as ascii
  // my_path = getenv("PATH");
  // // std::cout << "PATH post= " << (my_path ? my_path : "<empty>") << std::endl;
  // setenv("LC_ALL", "en_US.UTF-8", 1);

  // // print this out here (before losing our output)
  // //  if (SECCOMP_ENABLED != 0) {
  // std::cout << "going to install syscall filter for " << my_program << std::endl;
  // //}


  // // FIXME: if we want to assert or print stuff afterward, we should save
  // // the originals and restore after the exec fails.
  // if (my_stdin != "") {
  //   int new_stdinfd  = open(my_stdin.c_str()  , O_RDONLY );
  //   int stdinfd = fileno(stdin);
  //   close(stdinfd);
  //   dup2(new_stdinfd, stdinfd);
  // }
  // if (my_stdout != "") {
  //   int new_stdoutfd = creat(my_stdout.c_str(), everyone_read );
  //   int stdoutfd = fileno(stdout);
  //   close(stdoutfd);
  //   dup2(new_stdoutfd, stdoutfd);
  // }
  // if (my_stderr != "") {
  //   int new_stderrfd = creat(my_stderr.c_str(), everyone_read );
  //   int stderrfd = fileno(stderr);
  //   close(stderrfd);
  //   dup2(new_stderrfd, stderrfd);
  // }



  // // SECCOMP: install the filter (system calls restrictions)
  // if (install_syscall_filter(prog_is_32bit, my_program,logfile)) {
  //   std::cout << "seccomp filter install failed" << std::endl;
  //   return 1;
  // }
  // // END SECCOMP

  // std::cout << "CHILD:Calling " << cmd << " " << my_char_args << std::endl;
  // std::string args = " -input ../cube.obj";
  // // char* my_char_args = new char*[1];
  // char* array = new char[args.length() +1];
  // for (int index = 0; index < args.length(); index++){
  //   array[index] = args[index];
  //   std::cout<< "Copying: " << args[index] << std::endl;
  // }
  // array[args.length()] = '\0';
  // char** my_char_args = new char*[2];
  // my_char_args[1] = array;

  // char* commandStr = new char[cmd.length() + 1];
  // for (int index = 0; index < cmd.length(); index++){
  //   commandStr[index] = cmd[index];
  // }
  // commandStr[cmd.length()] = '\0';
  // my_char_args[0] = commandStr;
  // printf("Calling %s with args %s, %s\n",cmd.c_str(), my_char_args[0], my_char_args[1]);
  // int child_result =  execv (cmd.c_str(), my_char_args);
  // std::cout << "CHILD:Did it?" <<std::endl;
  // if exec does not fail, we'll never get here
  // std::cout << "PARENT: running command " << cmd << std::endl;
  std::string total = cmd + " -input ../cube.obj";
  int child_result =  system (total.c_str());
  //umask(prior_umask);  // reset to the prior umask

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
  usleep(100000); /* wait 1/100th of a second for the process to die */
  elapsed+=0.001;
}

std::vector<int> extract_ints_from_string(std::string input)
{


    std::vector<int> ints;
    std::string myReg = ".*?(-?[0-9]{1,12})(.*)[\\s \\S]*";
    //std::string myReg = ".*?([0-9]+)(.*)[\\s \\S]*"; //anything (not greedy) followed by 1 or more digits (greedy)
                                                    //followed by anything, (greedy) followed by zero or more newlines.
    std::regex regex(myReg);
    std::smatch match;
    while (std::regex_match(input, match, regex))
    {
      ints.push_back(stoi(match[1].str()));
      input = match[2];
    }
    return ints;
}

std::vector<std::string> get_window_names_associated_with_pid(int pid)
{
  std::cout << pid << std::endl;
  std::string pidQuery = "pgrep -P ";
  pidQuery +=  std::to_string(pid);
  std::cout << "querying with: " << pidQuery << std::endl;
  std::string children = output_of_system_command(pidQuery.c_str());
  std::cout << "Associated pids " << children << std::endl;
  std::vector<int> ints = extract_ints_from_string(children);
  for(int i = 0; i < ints.size(); i++)
  {
    std::string pidQuery = "pgrep -P ";
    pidQuery +=  ints[i];
    children = output_of_system_command(pidQuery.c_str());
    std::cout << "pids associated with " << ints[i] << ": " << children << std::endl;
  }
  std::vector<std::string> associatedWindows;
  std::string activeWindows = output_of_system_command("wmctrl -lp"); //returns list of active windows with pid.
  std::istringstream stream(activeWindows);
  std::string window;    
  std::smatch match;
  std::cout << "Ideal pid is " << pid << std::endl;
  while (std::getline(stream, window)) {
    std::cout << "Processing: " << window << std::endl;
    //remove the first two columns 
    std::string myReg = "(.+)[ \\t]+(.*)"; //remove everthing before one or more spaces or tabs.
    std::regex regex(myReg);
    if(std::regex_match(window, match, regex)){ //remove the first two columns.
      window = match[2];
      if(std::regex_match(window, match, regex)){
        window = match[2];
      }
      else{
        continue;
      }
    }
    else{
      continue;
    }
    std::cout << "broken down to " << window << std::endl;

    if(std::regex_match(window, match, regex)){ //get the third collumn
      int windowPid = stoi(window);
      std::cout << "\tWindowpid was " << windowPid << std::endl;
      if(windowPid != pid){
        continue;
      }
      else{
        window = match[2];
      }
    }
    else{
      continue;
    }
    if(std::regex_match(window, match, regex)){
      associatedWindows.push_back(match[2]);
    }
  }
  return associatedWindows;
}


std::vector<int> get_window_data(std::string dataString, std::string windowName)
{
    std::string command = "xwininfo -name \"" + windowName + "\" | grep \"" + dataString +"\"";
    std::string valueString = output_of_system_command(command.c_str());
    return extract_ints_from_string(valueString);  
}
bool memory_ok(int rss_memory, int allowed_rss_memory)
{
  if(rss_memory > allowed_rss_memory)
  {
      return false;
  }
  else
  {
      return true;
  }
}

bool time_ok(float elapsed, float seconds_to_run)
{
  // allow 10 extra seconds for differences in wall clock
  // vs CPU time (imperfect solution)
  if(elapsed > seconds_to_run + 10.0f)
  {
      return false;
  }
  else
  {
      return true;
  }
}

//returns true on kill order. 
bool delay_and_mem_check(float sleep_time_in_microseconds, int childPID, float& elapsed, float& next_checkpoint, 
  float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill)
{
  double time_left = sleep_time_in_microseconds;
  while(time_left > 0)
  {

    if(time_left > 100000)
    {
      time_left -= 100000;
      usleep(100000);
    }
    else
    {
      time_left = 0.0f;
      usleep(time_left);
    }
    if (elapsed >= next_checkpoint) 
    {
      rss_memory = resident_set_size(childPID);
      //std::cout << "time elapsed = " << elapsed << " seconds,  memory used = " << rss_memory << " kb" << std::endl;
      next_checkpoint = std::min(elapsed+5.0,elapsed*2.0);
    }

    if (elapsed >= next_checkpoint) {
        rss_memory = resident_set_size(childPID);
        next_checkpoint = std::min(elapsed+5.0,elapsed*2.0);
    }
    if (!time_ok(elapsed, seconds_to_run)) {
      // terminate for excessive time
      std::cout << "Killing child process " << childPID << " after " << elapsed << " seconds elapsed." << std::endl;
      time_kill=1;
      return true;
    }

    if (!memory_ok(rss_memory, allowed_rss_memory)) 
    {
      // terminate for excessive memory usage (RSS = resident set size = RAM)
      return true;
      memory_kill=1;
      std::cout << "Killing child process " << childPID << " for using " << rss_memory << " kb RAM.  (limit is " << allowed_rss_memory << " kb)" << std::endl;
    } 
  }
  return false;
}



void initialize_window(std::string& windowName, int pid)
{
  std::cout << "initializing window." << std::endl;
  //std::vector<std::string> windows = get_window_names_associated_with_pid(pid);
  // if(windows.size() == 0)
  // {
  //   return;
  // }
  // else{
  //   std::cout<<"Windows associated with " << pid << std::endl;
  //   for(int i = 0; i < windows.size(); i++)
  //   {
  //     std::cout << windows[i] <<std::endl;
  //   }
  // }
  std::string windowQuery = "xdotool getwindowfocus getwindowname"; //+ windows[0];
  windowName = output_of_system_command(windowQuery.c_str()); //get the window name for graphics programs.
  windowName.erase(std::remove(windowName.begin(), windowName.end(), '\n'), windowName.end()); //trim.
  std::cout << "window name was " << windowName << std::endl;
}

//modifies pos to window border if necessary. Returns remainder.
int clamp(int& pos, int min, int max)
{
  int leftOver = 0;
  if(pos < min)
  {
    leftOver = pos - min;
    pos = min;
  }
  if(pos > max)
  {
    leftOver = pos - max; 
    pos = max;
  }
  return leftOver;
}

//returns delay time
float takeAction(const std::vector<std::string>& actions, int& actions_taken, 
    int& number_of_screenshots, std::string windowName)
{
  //We get the window data at every step in case it has changed size.
  float delay = 0;
  int height = get_window_data("Height", windowName)[0];
  int width = get_window_data("Width", windowName)[0];
  int xStart = get_window_data("Absolute upper-left X", windowName)[0]; //These values represent the upper left corner
  int yStart = get_window_data("Absolute upper-left Y", windowName)[0];
  int xEnd = xStart+width; //These values represent the upper right corner
  int yEnd = yStart + height;
  std::cout << "The window " << windowName << " has upper left (" << xStart << ", " << yStart << ") and lower right (" << xEnd << ", " << yEnd << ")"<<std::endl; 
  
  std::cout<<"Taking action " << actions_taken+1 << " of " << actions.size() << ": " << actions[actions_taken]<< std::endl;
  if(actions[actions_taken].find("delay") != std::string::npos)
  {
    std::string myReg = ".*?([0-9]+).*";
    std::regex regex(myReg);
    std::smatch match;
    if (std::regex_match(actions[actions_taken], match, regex))
    {
      std::cout << "Delaying for " << match[1].str() << " seconds." << std::endl;
      int sleep_time_secs = stoi(match[1].str());
      int sleep_time_micro = 1000000 * sleep_time_secs; //TODO turn this into a loop w/ memory checks.  
      delay = sleep_time_micro;
    }
  }
  else if(actions[actions_taken].find("screenshot") != std::string::npos)
  {
    std::ostringstream command_stream;
    command_stream << "wmctrl -R " << windowName << " && scrot "  << "_" << number_of_screenshots << ".png -u";
    system(command_stream.str().c_str());
    number_of_screenshots = number_of_screenshots + 1;
  }
  else if(actions[actions_taken].find("type") != std::string::npos)
  {
    int presses = 1;
    float delay = 100000;
    std::string toType = "";
    std::vector<int> values = extract_ints_from_string(actions[actions_taken]);
    if(values.size() > 0)
    {
      presses = values[0];
    }
    if(values.size() > 1)
    {
      delay = values[1] * 1000000;
    }
    std::string myReg = ".*?(\".*?\").*"; //anything (lazy) followed by anything between quotes (lazy)
                                          //followed by anything (greedy)
    std::regex regex(myReg);
    std::smatch match;
    if(std::regex_match(actions[actions_taken], match, regex))
    { 
      toType = match[1];  
    }
    std::cout << "About to type " << toType << " " << presses << " times with a delay of " 
                    << delay << " microseconds" << std::endl;
    std::ostringstream command_stream;
    command_stream << "wmctrl -R " << windowName << " &&  xdotool type " << toType; 
    for(int i = 0; i < presses; i++)
    {
      std::cout << "executed." << command_stream.str() << std::endl;
      if(toType.length() > 0)
      {
        std::cout << "The string " << toType << " is of size " << toType;
        system(command_stream.str().c_str());
      }
      else
      {
        std::cout << "ERROR: The line " << actions[actions_taken] << " contained no quoted string." <<std::endl;
      }
      if(i != presses-1)
      {
        usleep(delay); //TODO update to delay_and_mem_check
      }
    }
  }
  else if(actions[actions_taken].find("click and drag") != std::string::npos)
  {    
    std::ostringstream command_stream;
    std::vector<int> coords = extract_ints_from_string(actions[actions_taken]);
    if(coords.size() == 0)
    {
      std::cout << "ERROR: The line " <<actions[actions_taken] << " does not specify two coordinates." <<std::endl;
      actions_taken++;
      return delay;
    }
    bool no_clamp = false;
    if(actions[actions_taken].find("no clamp") != std::string::npos)
    {
      no_clamp = true;
    }
    if(actions[actions_taken].find("delta") != std::string::npos)
    {
      //delta version, 2 values movement x and movement y.
      int amt_x_movement_remaining = coords[0];
      int amt_y_movement_remaining = coords[1];


      //For now, we're going to force the mouse to start inside of the window by at least a pixel.
      std::string mouse_location_string = output_of_system_command("xdotool getmouselocation");
      std::vector<int> xy = extract_ints_from_string(mouse_location_string);
      int mouse_x = xy[0];
      int mouse_y = xy[1];
      clamp(mouse_x, xStart+1, xEnd-1); //move in by a pixel.
      clamp(mouse_y, yStart+1, yEnd-1);

      float slope = (float)amt_y_movement_remaining / (float)amt_x_movement_remaining;
      std::cout << "Slope was " << slope << std::endl;

      float total_distance_needed = sqrt(pow(amt_x_movement_remaining, 2) + pow (amt_y_movement_remaining, 2));
      std::cout << "Distance needed was " << total_distance_needed << std::endl;
      float distance_needed = total_distance_needed;
      //while loop with a clamp.
      int cycles = 0; //DEBUG CODE
      int curr_x = 0;
      int curr_y = 0;
      while(distance_needed >= 1 && cycles < 1000)
      {
        std::cout << std::endl;
        command_stream.str(""); //todo clean to move.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                       << mouse_x << " " << mouse_y; 
        system(command_stream.str().c_str());

        std::cout << "distance remaining is " << distance_needed <<std::endl;
        int xStep = xEnd-mouse_x; //This can be xStart if we're moving negatively

        float distance_of_move = sqrt(pow(xStep, 2) + pow (xStep*slope, 2));
        std::cout << "We can move " << distance_of_move << " at maximum" << std::endl;
        
        if(distance_of_move > distance_needed)
        {
          std::cout << "INSIDE because " << distance_of_move << " > " << distance_needed << ": setting distance needed to " << distance_needed <<std::endl;
          distance_of_move = distance_needed;
          std::cout << "CurrX: " << curr_x << " xEnd " << total_distance_needed << std::endl;
          xStep = total_distance_needed - curr_x;
        }

        distance_needed -= distance_of_move;
       // std::cout << "Moving mouse " << distance_of_move << std::endl;

        command_stream.str(""); //TODO clean to click.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousedown 1"  ;
        system(command_stream.str().c_str());

        int moved_mouse_x = mouse_x+xStep;
        int moved_mouse_y = mouse_y + (xStep * slope);
        
        command_stream.str("");
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                       << moved_mouse_x << " " << moved_mouse_y;  
        std::cout << "Using command: " << command_stream.str() << std::endl;
        system(command_stream.str().c_str());

        command_stream.str(""); //TODO clean to click.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mouseup 1";  
        system(command_stream.str().c_str());
        
        curr_x += xStep;
        curr_y += (xStep*slope);
        cycles++;
      }
      if(cycles > 1000)
      {
        std::cout << "POSSIBLE INFINITE LOOP!" << std::endl;
        exit(1);
      }
      command_stream.str("");
    }
    else
    {
      int start_x, start_y, end_x, end_y;
      if(coords.size() >3) //get the mouse into starting position.
      {
        start_x = coords[0] + xStart;
        start_y = coords[1] + yStart;
        end_x   = coords[2] + xStart;
        end_y   = coords[3] + yStart;
        //reset logic 
        
        clamp(start_x, xStart, xEnd);//returns remainder or zero if fine.
        clamp(start_y, yStart, yEnd);
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                     << start_x<< " "<< start_y;  
        system(command_stream.str().c_str());
      }
      else
      {
        end_x = coords[0] + xStart;
        end_y = coords[1] + yStart;
      }
      if(!no_clamp)
      {
        clamp(end_x, xStart, xEnd); 
        clamp(end_y, yStart, yEnd);
      }
      command_stream.str("");
      command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                     << end_x << " " << end_y;  
    }
    system(command_stream.str().c_str()) ;
  }
  else if(actions[actions_taken].find("click") != std::string::npos)
  {
    
  }
  else if(actions[actions_taken].find("xdotool") != std::string::npos)
  {
    system(actions[actions_taken].c_str());
  }
  else
  {
    std::ostringstream command_stream; 
    //This should grab the currently focused (newly created) window and run the action on it.
    command_stream << "wmctrl -R " << windowName << " &&  xdotool key " << actions[actions_taken];
    std::cout << "Running: " << command_stream.str() << std::endl;
    system(command_stream.str().c_str());
  }
  actions_taken++;
  return delay;
}


// Executes command (from shell) and returns error code (0 = success)
int main()
{
  std::string cmd = "./b.out"; 
  std::vector<std::string> actions;
  // actions.push_back("a");
  actions.push_back("click and drag delta 611 337");
  actions.push_back("delay 1");
  // actions.push_back("a");
  actions.push_back("screenshot");
  actions.push_back("q");
  //actions.push_back("KP_Enter");

  std::cout << "IN EXECUTE:  '" << cmd << "'" << std::endl;

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  int time_kill=0;
  int memory_kill=0;
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);

  std::string program_name = "ProgName";
  int seconds_to_run = 10;

  int allowed_rss_memory = 100000000;

  if (childPID == 0) {
    // CHILD PROCESS


    // enable_all_setrlimit(program_name,test_case_limits,assignment_limits);


    // Student's shouldn't be forking & making threads/processes...
    // but if they do, let's set them in the same process group
    int pgrp = setpgid(getpid(), 0);
    assert(pgrp == 0);

    int child_result;
    child_result = exec_this_command(cmd);

    // send the system status code back to the parent process
    //std::cout << "    child_result = " << child_result << std::endl;
    exit(child_result);

    }
    else 
    {
      //TODO add check for window up at each step.

        // PARENT PROCESS
        // std::cout << "childPID = " << childPID << std::endl;
        // std::cout << "PARENT PROCESS START: " << std::endl;
        std::string windowName;
        int parent_result = system("date");
        assert (parent_result == 0);
        //std::cout << "  parent_result = " << parent_result << std::endl;

        float elapsed = 0;
        int status;
        pid_t wpid = 0;
        float next_checkpoint = 0;
        int rss_memory = 0;
        int actions_taken = 0;
        int number_of_screenshots = 0;

        std::ostringstream command_stream;
        system(command_stream.str().c_str());
        do {
            if(windowName == "")
            {
              usleep(100000);
              initialize_window(windowName, childPID);
              if(windowName != "")
              {
                int height = get_window_data("Height", windowName)[0];
                int width = get_window_data("Width", windowName)[0];
                int xStart = get_window_data("Absolute upper-left X", windowName)[0]; //These values represent the upper left corner
                int yStart = get_window_data("Absolute upper-left Y", windowName)[0]; 
                int middle_x = xStart + width/2;
                int middle_y = yStart+height/2;
                command_stream.str(""); //todo clean to move.
                command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                        << middle_x << " " << middle_y; 
                system(command_stream.str().c_str());
              }
            }
            wpid = waitpid(childPID, &status, WNOHANG);
            if (wpid == 0) 
            {
              // monitor time & memory usage
              if (!time_kill && !memory_kill) 
              {
                  // sleep 1/10 of a second
                if(actions_taken < actions.size() && windowName != "") //if we still have actions (keyboard events, etc.) to give the child
                {
                  float delayTime = takeAction(actions, actions_taken, number_of_screenshots, windowName); //returns delaytime
                  if(delayTime == 0)
                  {
                    delay_and_mem_check(100000, childPID, elapsed, next_checkpoint, seconds_to_run, 
                      rss_memory, allowed_rss_memory, memory_kill, time_kill);
                  }
                  else
                  {
                    delay_and_mem_check(delayTime, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);
                  }
                }
                else
                {
                  delay_and_mem_check(100000, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);
                }
              }
           }


        } while (wpid == 0);

        if (WIFEXITED(status)) {

            std::cout << "Child exited, status=" << WEXITSTATUS(status) << std::endl;
            if (WEXITSTATUS(status) == 0){
                result=0;
            }
            else{
          std::cout << "Child exited with status = " << WEXITSTATUS(status) << std::endl;
          result=1;

              //
              // NOTE: If wrapping /usr/bin/time around a program that exits with signal = 25
              //       time exits with status = 153 (128 + 25)
              //
              if (WEXITSTATUS(status) > 128 && WEXITSTATUS(status) <= 256) {
                // OutputSignalErrorMessageToExecuteLogfile(  WEXITSTATUS(status)-128,logfile );
              }

            }
        }
        else if (WIFSIGNALED(status)) {
            int what_signal =  WTERMSIG(status);
        // OutputSignalErrorMessageToExecuteLogfile(what_signal,logfile);
            std::cout << "Child " << childPID << " was terminated with a status of: " << what_signal << std::endl;
            if (WTERMSIG(status) == 0){
          result=0;
            }
            else{
          result=2;
            }
        }
        if (time_kill){
      std::cout << "ERROR: Maximum run time exceeded" << std::endl;
      std::cout << "Program Terminated" << std::endl;
      result=3;
        }
        if (time_kill){
      std::cout << "ERROR: Maximum RSS (RAM) exceeded" << std::endl;
      std::cout << "Program Terminated" << std::endl;
      result=3;
        }
        std::cout << "PARENT PROCESS COMPLETE: " << std::endl;
        parent_result = system("date");
        assert (parent_result == 0);
    }
    std::cout <<"Result: "<<result<<std::endl;
    return result;
}



// =====================================================================================
// =====================================================================================


/*
php
apache
postgres



*/