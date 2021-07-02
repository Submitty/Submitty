// #include <sys/types.h>
// #include <sys/stat.h>
// #include <signal.h>
// #include <unistd.h>
// #include <cstdlib>
// #include <string>
// #include <sstream>

// // for system call filtering
// #include <seccomp.h>
// #include <elf.h>

// #include "error_message.h"

// #ifndef SIGPOLL
//   #define SIGPOLL SIGIO // SIGPOLL is obsolescent in POSIX, SIGIO is a synonym
// #endif

// std::string RetrieveSignalErrorMessage(int what_signal) {

//     // default message (may be overwritten with more specific message below)
//     std::stringstream ss;
//     ss << "ERROR: Child terminated with signal " << what_signal;
//     std::string message = ss.str();

//     // reference: http://man7.org/linux/man-pages/man7/signal.7.html
//     if        (what_signal == SIGHUP    /*  1        Term  Hangup detected on controlling terminal or death of controlling process   */) {
//     } else if (what_signal == SIGINT    /*  2        Term  Interrupt from keyboard  */) {
//     } else if (what_signal == SIGQUIT   /*  3        Core  Quit from keyboard  */) {
//     } else if (what_signal == SIGILL    /*  4        Core  Illegal Instruction  */) {
//     } else if (what_signal == SIGABRT   /*  6        Core  Abort signal from abort(3)  */) {
//         message = "ERROR: ABORT SIGNAL";
//     } else if (what_signal == SIGFPE    /*  8        Core  Floating point exception  */) {
//         message = "ERROR: FLOATING POINT ERROR";
//     } else if (what_signal == SIGKILL   /*  9        Term  Kill signal  */) {
//         message = "ERROR: KILL SIGNAL";
//     } else if (what_signal == SIGSEGV   /* 11        Core  Invalid memory reference  */) {
//         message = "ERROR: INVALID MEMORY REFERENCE";
//     } else if (what_signal == SIGPIPE   /* 13        Term  Broken pipe: write to pipe with no readers  */) {
//     } else if (what_signal == SIGALRM   /* 14        Term  Timer signal from alarm(2)  */) {
//     } else if (what_signal == SIGTERM   /* 15        Term  Termination signal  */) {
//     } else if (what_signal == SIGUSR1   /* 30,10,16  Term  User-defined signal 1  */) {
//     } else if (what_signal == SIGUSR2   /* 31,12,17  Term  User-defined signal 2  */) {
//     } else if (what_signal == SIGCHLD   /* 20,17,18  Ign   Child stopped or terminated  */) {
//     } else if (what_signal == SIGCONT   /* 19,18,25  Cont  Continue if stopped  */) {
//     } else if (what_signal == SIGSTOP   /* 17,19,23  Stop  Stop process  */) {
//     } else if (what_signal == SIGTSTP   /* 18,20,24  Stop  Stop typed at terminal  */) {
//     } else if (what_signal == SIGTTIN   /* 21,21,26  Stop  Terminal input for background process  */) {
//     } else if (what_signal == SIGTTOU   /* 22,22,27  Stop  Terminal output for background process  */) {
//     } else if (what_signal == SIGBUS    /* 10,7,10   Core  Bus error (bad memory access)  */) {
//         message = "ERROR: BUS ERROR (BAD MEMORY ACCESS)";
//     } else if (what_signal == SIGPOLL   /*           Term  Pollable event (Sys V). Synonym for SIGIO  */) {
//     } else if (what_signal == SIGPROF   /* 27,27,29  Term  Profiling timer expired  */) {
//     } else if (what_signal == SIGSYS    /* 12,31,12  Core  Bad argument to routine (SVr4)  */) {
//         message = "********************************\nDETECTED BAD SYSTEM CALL\n***********************************\nERROR: DETECTED BAD SYSTEM CALL";
//     } else if (what_signal == SIGTRAP   /*  5        Core  Trace/breakpoint trap  */) {
//     } else if (what_signal == SIGURG    /* 16,23,21  Ign   Urgent condition on socket (4.2BSD)  */) {
//     } else if (what_signal == SIGVTALRM /* 26,26,28  Term  Virtual alarm clock (4.2BSD)  */) {
//     } else if (what_signal == SIGXCPU   /* 24,24,30  Core  CPU time limit exceeded (4.2BSD)  */) {
//         message = "ERROR: CPU TIME LIMIT EXCEEDED";
//     } else if (what_signal == SIGXFSZ   /* 25,25,31  Core  File size limit exceeded (4.2BSD  */) {
//         message = "ERROR: FILE SIZE LIMIT EXCEEDED";
//     } else {
//     }

//   return message;

// }
