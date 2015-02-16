// Pass -lseccomp to build

#include <stdio.h>
#include <stddef.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <elf.h>
#include <seccomp.h>

#include <string>

#include "syscall_numbers.h"


// XXX: we have a memory leak: not calling seccomp_release() on error
#define DISALLOW_SYSCALL_32(name) do {\
    int __res__ = seccomp_rule_add(sc, SCMP_ACT_KILL, SYSNUM_32_##name, 0);\
    if (__res__ < 0) {\
        fprintf(stderr, "Error %d installing seccomp rule for %s\n", __res__, #name); \
        return 1;\
    }\
} while (0)


// XXX: we have a memory leak: not calling seccomp_release() on error
#define DISALLOW_SYSCALL_64(name) do {\
    int __res__ = seccomp_rule_add(sc, SCMP_ACT_KILL, SYSNUM_64_##name, 0);\
    if (__res__ < 0) {\
        fprintf(stderr, "Error %d installing seccomp rule for %s\n", __res__, #name); \
        return 1;\
    }\
} while (0)


int install_syscall_filter(bool is_32, bool blacklist, const std::string &my_program)
{
    int res;
    scmp_filter_ctx sc = seccomp_init(blacklist ? SCMP_ACT_ALLOW : SCMP_ACT_KILL); 


    // TODO: Can adjust the system call restrictions per course or per
    // per assignment


    // RESTRICT SOCKETS
    if (is_32) {
        DISALLOW_SYSCALL_32(socketcall);
    } else {


      //std::cout << "SYSTEM CALL NUMBER FOR SOCKET  " << seccomp_syscall_resolve_name("socket") << std::endl;
      //std::cout << "SYSTEM CALL NUMBER FOR CONNECT " << seccomp_syscall_resolve_name("connect") << std::endl;

      
      if (my_program != "/usr/bin/python") {
        // these 2 system calls are used by even very basic python programs (???)
        DISALLOW_SYSCALL_64(socket);
        DISALLOW_SYSCALL_64(connect);
      }
      
      DISALLOW_SYSCALL_64(accept);
      DISALLOW_SYSCALL_64(sendto);
      DISALLOW_SYSCALL_64(recvfrom);
      DISALLOW_SYSCALL_64(sendmsg);
      DISALLOW_SYSCALL_64(recvmsg);
      DISALLOW_SYSCALL_64(shutdown);
      DISALLOW_SYSCALL_64(bind);
      DISALLOW_SYSCALL_64(listen);
      DISALLOW_SYSCALL_64(getsockname);
      DISALLOW_SYSCALL_64(getpeername);
      DISALLOW_SYSCALL_64(socketpair);
      DISALLOW_SYSCALL_64(setsockopt);
      DISALLOW_SYSCALL_64(getsockopt);
    }
    
    
    // We will allow clone & fork & vfork for g++ compilation
    // a successful g++ compilation apparently uses only vfork
    // but a compiler error uses clone (but after reporting the error?)
    if (my_program != "/usr/bin/g++") {
      // RESTRICT CLONE & FORK
      if (is_32) {
        DISALLOW_SYSCALL_32(clone);
        DISALLOW_SYSCALL_32(fork);
        DISALLOW_SYSCALL_32(vfork);
      } else {
        DISALLOW_SYSCALL_64(clone);
        DISALLOW_SYSCALL_64(fork);
        DISALLOW_SYSCALL_64(vfork);
      }
    }


    if (seccomp_load(sc) < 0) {
      return 1; // failure
    }
	
    seccomp_release(sc);

    return 0;
}

