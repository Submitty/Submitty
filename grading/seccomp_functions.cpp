#include <sys/types.h>
#include <sys/stat.h>
#include <cstdio>
#include <cstddef>
#include <cstdlib>
#include <unistd.h>
#include <fcntl.h>
#include <elf.h>

// COMPILATION NOTE: Must pass -lseccomp to build
#include <seccomp.h>

#include <string>

// ===========================================================================
// ===========================================================================
// Helper macro that disallows certain system calls using the seccomp library

#define DISALLOW_SYSCALL(name) do {\
  int __res__ = seccomp_rule_add(sc, SCMP_ACT_KILL, SCMP_SYS(name), 0); \
  if (__res__ < 0) {\
    fprintf(stderr, "Error %d installing seccomp rule for %s\n", __res__, #name); \
    return 1;\
  }\
} while (0)

// ===========================================================================
// ===========================================================================

int install_syscall_filter(bool is_32, bool blacklist, const std::string &my_program)
{
  int res;
  scmp_filter_ctx sc = seccomp_init(blacklist ? SCMP_ACT_ALLOW : SCMP_ACT_KILL);
  int target_arch = is_32 ? SCMP_ARCH_X86 : SCMP_ARCH_X86_64;
  if (seccomp_arch_native() != target_arch) {
    res = seccomp_arch_add(sc, target_arch);
    if (res != 0) {
      //fprintf(stderr, "seccomp_arch_add failed: %d\n", res);
      return 1;
    }
  }

  // libseccomp uses pseudo-syscalls to let us use the 64-bit split
  // system call names for SYS_socketcall on 32-bit.  The translation
  // being on their side means we have no choice in the matter as we
  // cannot pass them the number for the target: only for the source.
  // We could use raw seccomp-bpf instead.
    
  /*
  if (my_program != "/usr/bin/javac" &&
      my_program != "/usr/bin/java") {
    // FIXME:  CURRENTLY DISABLING ALL SYSTEM CALL FILTERING FOR JAVA


  if (my_program != "/usr/bin/compare") {
    // FIXME: SHOULD FIGURE OUT WHAT COMPARE NEEDS...
    */

  
  // -------------------------------------------
  // FORK AND CLONE 
  if (my_program != "/usr/bin/g++" &&
      my_program != "/usr/bin/clang++" &&
      my_program != "/usr/bin/time" &&
      my_program != "/usr/bin/javac" &&
      my_program != "/usr/bin/java" &&
      my_program != "/usr/bin/compare") {
    DISALLOW_SYSCALL(clone);
    DISALLOW_SYSCALL(fork);
    DISALLOW_SYSCALL(vfork);
  }

  // -------------------------------------------
  // SOCKETS
  if (my_program != "/usr/bin/python" &&
      my_program != "/usr/bin/javac" &&
      my_program != "/usr/bin/java") {
    // these 2 system calls are used by even very basic python programs (???)
    DISALLOW_SYSCALL(socket);
    DISALLOW_SYSCALL(connect);
  }

  // -------------------------------------------
  if (my_program != "/usr/bin/javac" &&
      my_program != "/usr/bin/java") {

    DISALLOW_SYSCALL(accept);
    DISALLOW_SYSCALL(sendto);
    DISALLOW_SYSCALL(recvfrom);
    DISALLOW_SYSCALL(sendmsg);
    DISALLOW_SYSCALL(recvmsg);
    DISALLOW_SYSCALL(shutdown);
    DISALLOW_SYSCALL(bind);
    DISALLOW_SYSCALL(listen);
    DISALLOW_SYSCALL(getsockname);
    DISALLOW_SYSCALL(getpeername);
    DISALLOW_SYSCALL(socketpair);
    DISALLOW_SYSCALL(setsockopt);
    DISALLOW_SYSCALL(getsockopt);
  }

  
  if (seccomp_load(sc) < 0)
    return 1; // failure                                                                                   
  
  /* This does not remove the filter */
  seccomp_release(sc);

  return 0;
}


// ===========================================================================
// ===========================================================================
