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
#include <sys/types.h>
#include <sys/stat.h>
#include <signal.h>
#include <unistd.h>
#include <fcntl.h>

#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>

#include "modules/modules.h"
#include "grading/TestCase.h"


#include <seccomp.h>


// ----------------------------------------------------------------

#define MAX_STRING_LENGTH 100
#define MAX_NUM_STRINGS 20
#define DIR_PATH_MAX 1000

#include <dirent.h>

// for system call white list
#include "execute.h"

// defined in seccomp_functions.cpp
#include <elf.h>
int install_syscall_filter(bool is_32, bool blacklist, const std::string &my_program);

// =====================================================================================
// =====================================================================================


bool system_program(const std::string &program) {
  assert (program.size() >= 1);
  if (program == "/bin/ls" ||
      program == "/usr/bin/time" ||
      program == "/usr/bin/clang++" ||
      program == "/usr/bin/g++" ||
      program == "/usr/bin/valgrind" ||
      program == "/projects/submit3/drmemory/bin/drmemory" ||
      program == "/bin/mv" || 
      program == "/bin/chmod" || 
      program == "/usr/bin/find" || 
      program == "/usr/bin/python") {
    return true;
  }
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


void validate_program(const std::string &program) {
  assert (program.size() >= 1);
  if (!system_program(program) &&
      !local_executable(program)) {
    std::cout << "ERROR: program looks suspicious '" << program << "'" << std::endl;
    std::cerr << "ERROR: program looks suspicious '" << program << "'" << std::endl;
    exit(1);
  }
}


void validate_filename(const std::string &filename) {
  assert (filename.size() >= 1);
  if (filename[0] == '-') {
    std::cout << "ERROR: command line filename looks suspicious '" << filename << "'" << std::endl;
    std::cerr << "ERROR: command line filename looks suspicious '" << filename << "'" << std::endl;
    exit(1);
  }
}


void validate_option(const std::string &program, const std::string &option) {
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
}

// =====================================================================================

bool wildcard_match(const std::string &pattern, const std::string &thing, std::ofstream &logfile) {

  //std::cout << "WILDCARD MATCH? " << pattern << " " << thing << std::endl;

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



void wildcard_expansion(std::vector<std::string> &my_args, const std::string &pattern, std::ofstream &logfile) {
  int count_matches = 0;
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
      if (wildcard_match(pattern,thing,logfile)) {
	std::cout << "   MATCHED! " << thing << std::endl;
	validate_filename(thing);
	my_args.push_back(thing);
	count_matches++;
     }
    }
    closedir(dir);
  } else {
    my_args.push_back(pattern);
  }
  if (count_matches == 0) {
    logfile << "WARNING: No matches to wildcard pattern: " << pattern << std::endl;
  }
}


void parse_command_line(const std::string &cmd,
			std::string &my_program,
			std::vector<std::string> &my_args,
			std::string &my_stdin,
			std::string &my_stdout,
			std::string &my_stderr,
			std::ofstream &logfile) {

    std::stringstream ss(cmd);
    std::string tmp;
    bool bare_double_dash = false;

    while (ss >> tmp) {
        assert (tmp.size() >= 1);

        // grab the program name
        if (my_program == "") {
            assert (my_args.size() == 0);
            // program name
            my_program = tmp;
            validate_program(my_program);
        }

        // grab the arguments
        else {
            assert (my_program != "");

            // look for the bare double dash
            if (tmp == "--") {
                assert (bare_double_dash == false);
                bare_double_dash = true;
                my_args.push_back(tmp);
            }

            // look for stdin/stdout/stderr
            else if (tmp.size() >= 1 && tmp.substr(0,1) == "<") {
                assert (my_stdin == "");
                if (tmp.size() == 1) {
                    ss >> tmp; bool success = ss.good();
                    assert (success);
                    my_stdin = tmp;
                } else {
                    my_stdin = tmp.substr(1,tmp.size()-1);
                }
                validate_filename(my_stdin);
            }
            else if (tmp.size() >= 2 && tmp.substr(0,2) == "1>") {
                assert (my_stdout == "");
                if (tmp.size() == 2) {
                    ss >> tmp; bool success = ss.good();
                    assert (success);
                    my_stdout = tmp;
                } else {
                    my_stdout = tmp.substr(2,tmp.size()-2);
                }
                validate_filename(my_stdout);
            }
            else if (tmp.size() >= 2 && tmp.substr(0,2) == "2>") {
                assert (my_stderr == "");
                if (tmp.size() == 2) {
                    ss >> tmp; bool success = ss.good();
                    assert (success);
                    my_stderr = tmp;
                } else {
                    my_stderr = tmp.substr(2,tmp.size()-2);
                }
                validate_filename(my_stderr);
            }

            // remainder of the arguments
            else if (tmp.find("*") != std::string::npos) {
	      // unfortunately not all programs used the double dash convention 
	      /*
                if (bare_double_dash != true) {
                    std::cout << "ERROR: Not allowed to use the wildcard before the bare double dash" << std::endl;
                    std::cerr << "ERROR: Not allowed to use the wildcard before the bare double dash" << std::endl;
                    exit(1);
                }
	      */
	      wildcard_expansion(my_args,tmp,logfile);
            } else {
                if (bare_double_dash == true) {
                    validate_filename(tmp);
                } else {
                    validate_option(my_program,tmp);
                }
                my_args.push_back(tmp);
            }
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
}

// =====================================================================================
// =====================================================================================
// =====================================================================================




#ifndef SIGPOLL
  #define SIGPOLL SIGIO // SIGPOLL is obsolescent in POSIX, SIGIO is a synonym
#endif

void OutputSignalErrorMessageToExecuteLogfile(int what_signal, std::ofstream &logfile) {

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
    message = "ERROR: DETECTED BAD SYSTEM CALL";
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







// This function only returns on failure to exec
int exec_this_command(const std::string &cmd, int SECCOMP_ENABLED, std::ofstream &logfile) {

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
  my_char_args[my_args.size()+1] = (char *)NULL;

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



  if (SECCOMP_ENABLED != 0) {
    std::cout << "seccomp filter enabled" << std::endl;
  } else {
    std::cout << "********** SECCOMP FILTER DISABLED *********** " << std::endl;
  }


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


  // print this out here (before losing our output)
  if (SECCOMP_ENABLED != 0) {
    std::cout << "going to install syscall filter for " << my_program << std::endl;
  }


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
  if (SECCOMP_ENABLED != 0) {
    if (install_syscall_filter(prog_is_32bit, true /*blacklist*/, my_program)) { 
      std::cout << "seccomp filter install failed" << std::endl;
      return 1;
    }
  } else {
  }
  // END SECCOMP



  int child_result =  execv ( my_program.c_str(), my_char_args );
  // if exec does not fail, we'll never get here

  umask(prior_umask);  // reset to the prior umask

  return child_result;
}


// Executes command (from shell) and returns error code (0 = success)
int execute(const std::string &cmd, const std::string &execute_logfile, int seconds_to_run, int file_size_limit, int SECCOMP_ENABLED) { 

  std::cout << "IN EXECUTE:  '" << cmd << "'" << std::endl;

  std::ofstream logfile(execute_logfile.c_str(), std::ofstream::out | std::ofstream::app);

  // Forking to allow the setting of limits of RLIMITS on the command
  int result = -1;
  int time_kill=0;
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
    child_result = exec_this_command(cmd,SECCOMP_ENABLED,logfile);

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
                    time_kill=1;
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
            }
        }
        else if (WIFSIGNALED(status)) {
            int what_signal =  WTERMSIG(status);


	    OutputSignalErrorMessageToExecuteLogfile(what_signal,logfile);
	    
            //std::cout << "Child " << childPID << " was terminated with a status of: " << what_signal << std::endl;

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
        std::cout << "PARENT PROCESS COMPLETE: " << std::endl;
        parent_result = system("date");
        assert (parent_result == 0);
    }
  std::cout <<"Result: "<<result<<std::endl;
    return result;
}
