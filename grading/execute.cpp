#include <sys/time.h>
#include <sys/resource.h>
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

// for system call filtering
#include <seccomp.h>
#include <elf.h>

#include "TestCase.h"
#include "execute.h"
#include "error_message.h"


#define DIR_PATH_MAX 1000


// defined in seccomp_functions.cpp


#define SUBMITTY_INSTALL_DIRECTORY  std::string("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__")


// =====================================================================================
// =====================================================================================


bool system_program(const std::string &program, std::string &full_path_executable) {

  const std::map<std::string,std::string> allowed_system_programs = {

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
    { "cut",                     "/usr/bin/cut" },
    { "sort",                    "/usr/bin/sort" },
    { "sed",                     "/bin/sed" },

    // Submitty Analysis Tools
    { "submitty_count_token",    SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_token" },
    { "submitty_count_node",     SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_node" },
    { "submitty_count_function", SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_function" },

    // for Computer Science I
    { "python",                  "/usr/bin/python" },
    { "python2",                 "/usr/bin/python2" },
    { "python2.7",               "/usr/bin/python2.7" },
    { "python3",                 "/usr/bin/python3" },
    { "python3.4",               "/usr/bin/python3.4" },
    { "python3.5",               "/usr/bin/python3.5" },

    // for Data Structures
    { "g++",                     "/usr/bin/g++" },
    { "clang++",                 "/usr/bin/clang++" },
    { "drmemory",                SUBMITTY_INSTALL_DIRECTORY+"/drmemory/bin/drmemory" },
    { "valgrind",                "/usr/bin/valgrind" },

    // for Computer Organization
    { "spim",                    "/usr/bin/spim" },
    { "clang",                   "/usr/bin/clang" },

    // for Principles of Software
    { "java",                    "/usr/bin/java" },
    { "javac",                   "/usr/bin/javac" },

    // for Operating Systems
    { "gcc",                     "/usr/bin/gcc" },

    // for Programming Languages
    { "swipl",                   "/usr/bin/swipl" },
    { "plt-r5rs",                "/usr/bin/plt-r5rs" }

  };

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


bool local_executable (const std::string &program) {
  assert (program.size() >= 1);
  if (program.size() > 4 &&
      program.substr(0,2) == "./" &&
      program.substr(program.size()-4,4) == ".out" &&
      program.substr(2,program.size()-2).find("/") == std::string::npos) {
    return true;
  }
  return false;
}


std::string validate_program(const std::string &program) {
  std::string full_path_executable;
  assert (program.size() >= 1);
  if (system_program(program,full_path_executable)) {
    return full_path_executable;
  } else if (local_executable(program)) {
    return program;
  } else {
    std::cout << "ERROR: program looks suspicious '" << program << "'" << std::endl;
    std::cerr << "ERROR: program looks suspicious '" << program << "'" << std::endl;
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
      { { "submitty_emma.jar",      SUBMITTY_INSTALL_DIRECTORY+"/JUnit/emma.jar" },
        { "submitty_junit.jar",     SUBMITTY_INSTALL_DIRECTORY+"/JUnit/junit-4.12.jar" },
        { "submitty_hamcrest.jar",  SUBMITTY_INSTALL_DIRECTORY+"/JUnit/hamcrest-core-1.3.jar" },
        { "submitty_junit/",        SUBMITTY_INSTALL_DIRECTORY+"/JUnit/" }
      }
    },
    { "/usr/bin/java",
      { { "submitty_emma.jar",      SUBMITTY_INSTALL_DIRECTORY+"/JUnit/emma.jar" },
        { "submitty_junit.jar",     SUBMITTY_INSTALL_DIRECTORY+"/JUnit/junit-4.12.jar" },
        { "submitty_hamcrest.jar",  SUBMITTY_INSTALL_DIRECTORY+"/JUnit/hamcrest-core-1.3.jar" },
        { "submitty_junit/",        SUBMITTY_INSTALL_DIRECTORY+"/JUnit/" }
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

bool wildcard_match(const std::string &pattern, const std::string &thing, std::ostream &logfile) {
  //  std::cout << "WILDCARD MATCH? " << pattern << " " << thing << std::endl;

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

  if (before == thing_before && after == thing_after) {
    //std::cout << "RETURN TRUE" << std::endl;
    return true;
  }

  //std::cout << "return false" << std::endl;
  return false;
}


void wildcard_expansion(std::vector<std::string> &my_finished_args, const std::string &full_pattern, std::ostream &logfile) {

  //std::cout << "IN WILDCARD EXPANSION " << full_pattern << std::endl;

  // if the pattern does not contain a wildcard, just return that
  if (full_pattern.find("*") == std::string::npos) {
    my_finished_args.push_back(full_pattern);
    return;
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
      if (wildcard_match(file_pattern,thing,logfile)) {
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
    std::cout << "ERROR: FOUND NO MATCHES" << std::endl;
  }

  // sort the matches, so things are deterministic (and unix wildcard is sorted)
  std::sort(my_args.begin(),my_args.end());
  my_finished_args.insert(my_finished_args.end(), my_args.begin(), my_args.end());
}

// =====================================================================================
// =====================================================================================

std::string get_program_name(const std::string &cmd) {
  std::string my_program;
  std::stringstream ss(cmd);

  ss >> my_program;
  assert (my_program.size() >= 1);

  std::string full_path_executable = validate_program(my_program);
  return full_path_executable;
}


void parse_command_line(const std::string &cmd,
			std::string &my_program,
			std::vector<std::string> &my_args,
			std::string &my_stdin,
			std::string &my_stdout,
			std::string &my_stderr,
			std::ofstream &logfile) {

  std::cout << "PARSE COMMAND LINE " << cmd << std::endl;

  my_args.clear();
  my_program = my_stdin = my_stdout = my_stderr = "";

  std::stringstream ss(cmd);
  std::string token,token2;

  while (ss >> token) {
    assert (token.size() >= 1);

    // handle escaped spaces in the command line
    while (token.back() == '\\') {
      token.pop_back();
      token2 = " ";
      ss >> token2;
      token = token + ' ' + token2;
    }

    // grab the program name
    if (my_program == "") {
      assert (my_args.size() == 0);
      // program name
      my_program = validate_program(token);
      assert (my_program != "");
    }

    // look for stdin/stdout/stderr
    else if (token.substr(0,1) == "<") {
      assert (my_stdin == "");
      if (token.size() == 1) {
        ss >> token; bool success = ss.good();
        assert (success);
        my_stdin = token;
      } else {
        my_stdin = token.substr(1,token.size()-1);
      }
      validate_filename(my_stdin);
    }
    else if (token.size() >= 2 && token.substr(0,2) == "1>") {
      assert (my_stdout == "");
      if (token.size() == 2) {
        ss >> token; bool success = ss.good();
        assert (success);
        my_stdout = token;
      } else {
        my_stdout = token.substr(2,token.size()-2);
      }
      validate_filename(my_stdout);
    }
    else if (token.size() >= 2 && token.substr(0,2) == "2>") {
      assert (my_stderr == "");
      if (token.size() == 2) {
        ss >> token; bool success = ss.good();
        assert (success);
        my_stderr = token;
      } else {
        my_stderr = token.substr(2,token.size()-2);
      }
      validate_filename(my_stderr);
    }

    // remainder of the arguments
    else if (token.find("*") != std::string::npos) {
      wildcard_expansion(my_args,token,logfile);
    }

    // special exclude file option
    // FIXME: this is ugly, don't know how I want it to be done though
    else if (token == "-EXCLUDE_FILE") {
      ss >> token;
      std::cout << "EXCLUDE THIS FILE " << token << std::endl;
      for (std::vector<std::string>::iterator itr = my_args.begin();
           itr != my_args.end(); ) {
        if (*itr == token) {  std::cout << "FOUND IT!" << std::endl; itr = my_args.erase(itr);  }
        else { itr++; }
      }
    }

    else {
      // validate_filename(token);
      // validate_option(my_program,token);
      std::cout << "before TOKEN IS " << token << std::endl;
      token = validate_option(my_program,token);
      std::cout << "after  TOKEN IS " << token << std::endl;
      my_args.push_back(token);
    }
  }



  // FOR DEBUGGING
  std::cout << std::endl << std::endl;
  std::cout << "MY PROGRAM: '" << my_program << "'" << std::endl;
  std::cout << "MY STDIN:  '" << my_stdin  << "'" << std::endl;
  std::cout << "MY STDOUT:  '" << my_stdout  << "'" << std::endl;
  std::cout << "MY STDERR:  '" << my_stderr  << "'" << std::endl;
  std::cout << "MY ARGS (" << my_args.size() << ") :";

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
int exec_this_command(const std::string &cmd, std::ofstream &logfile) {

  // to avoid creating extra layers of processes, use exec and not
  // system or the shell

  // first we need to parse the command line
  std::string my_program;
  std::vector<std::string> my_args;
  std::string my_stdin;
  std::string my_stdout;
  std::string my_stderr;
  parse_command_line(cmd, my_program, my_args, my_stdin, my_stdout, my_stderr, logfile);


  char** temp_args = new char* [my_args.size()+2];   //memory leak here
  temp_args[0] = (char*) my_program.c_str();
  for (int i = 0; i < my_args.size(); i++) {
    std::cout << "'" << my_args[i] << "' ";
    temp_args[i+1] = (char*) my_args[i].c_str();
  }
  temp_args[my_args.size()+1] = (char *)NULL;

  char** const my_char_args = temp_args;

  std::cout << std::endl << std::endl;


  // print out the command line to be executed
  std::cout << "going to exec: ";
  for (int i = 0; i < my_args.size()+1; i++) {
    std::cout << my_char_args[i] << " ";
  }
  std::cout << std::endl;





  // SECCOMP:  Used to restrict allowable system calls.
  // First we determine if the program we will run is a 64 or 32 bit
  // executable (the system calls are different on 64 vs. 32 bit)
  Elf64_Ehdr elf_hdr;
  std::cout << "reading " <<  my_program << std::endl;
  int fd = open(my_program.c_str(), O_RDONLY);
  if (fd == -1) {
    perror("can't open");
    std::cerr << "ERROR: cannot open program" << std::endl;
    exit(1);
  }
  int res = read(fd, &elf_hdr, sizeof(elf_hdr));
  if (res < sizeof(elf_hdr)) {
    perror("can't read ");
    std::cerr << "ERROR: cannot read program" << std::endl;
    exit(1);
  }
  int prog_is_32bit;
  if (elf_hdr.e_machine == EM_386) {
    std::cout << "this is a 32 bit program" << std::endl;
    prog_is_32bit = 1;
  }
  else {
    std::cout << "this is a 64 bit program" << std::endl;
    prog_is_32bit = 0;
  }
  // END SECCOMP





  //if (SECCOMP_ENABLED != 0) {
  std::cout << "seccomp filter enabled" << std::endl;
  //} else {
  //std::cout << "********** SECCOMP FILTER DISABLED *********** " << std::endl;
  // }


  // the default umask is 0027, so we need edit so that we can make
  // these files 'other read', so that we can read them when we switch
  // users
  mode_t everyone_read = S_IRUSR | S_IWUSR | S_IRGRP | S_IROTH;
  mode_t prior_umask = umask(S_IWGRP | S_IWOTH);  // save the prior umask

  // The path is probably empty, we need to add /usr/bin to the path
  // since we get a "collect2 ld not found" error from g++ otherwise
  char* my_path = getenv("PATH");
  if (my_path == NULL) {
    setenv("PATH", "/usr/bin", 1);
  }
  else {
    std::cout << "WARNING: PATH NOT EMPTY, PATH= " << (my_path ? my_path : "<empty>") << std::endl;
  }
  my_path = getenv("PATH");
  //std::cout << "PATH post= " << (my_path ? my_path : "<empty>") << std::endl;

  // set the locale so that special characters (e.g., the copyright
  // symbol) are not interpreted as ascii
  setenv("LC_ALL", "en_US.UTF-8", 1);

  // print this out here (before losing our output)
  //  if (SECCOMP_ENABLED != 0) {
  std::cout << "going to install syscall filter for " << my_program << std::endl;
  //}


  // FIXME: if we want to assert or print stuff afterward, we should save
  // the originals and restore after the exec fails.
  if (my_stdin != "") {
    int new_stdinfd  = open(my_stdin.c_str()  , O_RDONLY );
    int stdinfd = fileno(stdin);
    close(stdinfd);
    dup2(new_stdinfd, stdinfd);
  }
  if (my_stdout != "") {
    int new_stdoutfd = creat(my_stdout.c_str(), everyone_read );
    int stdoutfd = fileno(stdout);
    close(stdoutfd);
    dup2(new_stdoutfd, stdoutfd);
  }
  if (my_stderr != "") {
    int new_stderrfd = creat(my_stderr.c_str(), everyone_read );
    int stderrfd = fileno(stderr);
    close(stderrfd);
    dup2(new_stderrfd, stderrfd);
  }



  // SECCOMP: install the filter (system calls restrictions)
  if (install_syscall_filter(prog_is_32bit, my_program,logfile)) {
    std::cout << "seccomp filter install failed" << std::endl;
    return 1;
  }
  // END SECCOMP



  int child_result =  execv ( my_program.c_str(), my_char_args );
  // if exec does not fail, we'll never get here

  umask(prior_umask);  // reset to the prior umask

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
  std::cout << "system ( '" + command + "' )" << std::endl;
  system (command.c_str());

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


// Executes command (from shell) and returns error code (0 = success)
int execute(const std::string &cmd, const std::string &execute_logfile,
	    nlohmann::json test_case_limits,
	    nlohmann::json assignment_limits) {

  std::cout << "IN EXECUTE:  '" << cmd << "'" << std::endl;

  std::ofstream logfile(execute_logfile.c_str(), std::ofstream::out | std::ofstream::app);

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  int time_kill=0;
  int memory_kill=0;
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);

  std::string program_name = get_program_name(cmd);
  int seconds_to_run = get_the_limit(program_name,RLIMIT_CPU,test_case_limits,assignment_limits);

  int allowed_rss_memory = get_the_limit(program_name,RLIMIT_RSS,test_case_limits,assignment_limits);

  if (childPID == 0) {
    // CHILD PROCESS


    enable_all_setrlimit(program_name,test_case_limits,assignment_limits);


    // Student's shouldn't be forking & making threads/processes...
    // but if they do, let's set them in the same process group
    int pgrp = setpgid(getpid(), 0);
    assert(pgrp == 0);

    int child_result;
    child_result = exec_this_command(cmd,logfile);

    // send the system status code back to the parent process
    //std::cout << "    child_result = " << child_result << std::endl;
    exit(child_result);

    }
    else {
        // PARENT PROCESS
        std::cout << "childPID = " << childPID << std::endl;
        std::cout << "PARENT PROCESS START: " << std::endl;
        int parent_result = system("date");
        assert (parent_result == 0);
        //std::cout << "  parent_result = " << parent_result << std::endl;

        float elapsed = 0;
        int status;
        pid_t wpid = 0;
        float next_checkpoint = 0;
	int rss_memory = 0;

        do {
            wpid = waitpid(childPID, &status, WNOHANG);
            if (wpid == 0) {
	      // allow 10 extra seconds for differences in wall clock
	      // vs CPU time (imperfect solution)
	      
	      if (elapsed > seconds_to_run + 10) {
		// terminate for excessive time
		std::cout << "Killing child process " << childPID << " after " << elapsed << " seconds elapsed." << std::endl;
		TerminateProcess(elapsed,childPID);
		time_kill=1;
	      }
	      
	      if (rss_memory > allowed_rss_memory) {
		// terminate for excessive memory usage (RSS = resident set size = RAM)
		std::cout << "Killing child process " << childPID << " for using " << rss_memory << " kb RAM.  (limit is " << allowed_rss_memory << " kb)" << std::endl;
		TerminateProcess(elapsed,childPID);
		memory_kill=1;
	      } 

	      // monitor time & memory usage
	      if (!time_kill && !memory_kill) {
		// sleep 1/10 of a second
		usleep(100000);
		elapsed+= 0.1;
	      }
	      if (elapsed >= next_checkpoint) {
		rss_memory = resident_set_size(childPID);
		std::cout << "time elapsed = " << elapsed << " seconds,  memory used = " << rss_memory << " kb" << std::endl;
		next_checkpoint = std::min(elapsed+5.0,elapsed*2.0);
	      }

            }


        } while (wpid == 0);

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
        if (time_kill){
	  logfile << "ERROR: Maximum RSS (RAM) exceeded" << std::endl;
	  logfile << "Program Terminated" << std::endl;
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
