// ================================================================================
//
// NOTE: This file has strict formatting requirements because it is
//   parsed by the system_call_check.cpp helper utility.
//
//
// TO GENERATE LIST OF ALL SYSTEM CALLS:
//    grep _NR /usr/include/x86_64-linux-gnu/asm/unistd_64.h /usr/include/x86_64-linux-gnu/asm/unistd_32.h | awk '{print $2}' | sort | uniq > /tmp/x
//
// ================================================================================

void allow_system_calls(scmp_filter_ctx sc, const std::set<std::string> &categories) {

  // ================================================================================

  // WHITELIST : PROCESS_CONTROL
  ALLOW_SYSCALL(arch_prctl);
  ALLOW_SYSCALL(modify_ldt);
  ALLOW_SYSCALL(exit);
  ALLOW_SYSCALL(exit_group);
  ALLOW_SYSCALL(getpid);
  ALLOW_SYSCALL(getppid);
  ALLOW_SYSCALL(nanosleep);
  ALLOW_SYSCALL(restart_syscall);
  ALLOW_SYSCALL(sigaction);
  ALLOW_SYSCALL(sigprocmask);
  ALLOW_SYSCALL(rt_sigaction);
  ALLOW_SYSCALL(rt_sigprocmask);
  ALLOW_SYSCALL(getrlimit);

  // WHITELIST : PROCESS_CONTROL_MEMORY
  ALLOW_SYSCALL(brk);
  ALLOW_SYSCALL(get_thread_area);
  ALLOW_SYSCALL(gettid);
  ALLOW_SYSCALL(mmap);
  ALLOW_SYSCALL(mmap2);
  ALLOW_SYSCALL(mprotect);
  ALLOW_SYSCALL(mremap);
  ALLOW_SYSCALL(munmap);
  ALLOW_SYSCALL(set_thread_area);
#ifdef __NR_memfd_create
  ALLOW_SYSCALL(memfd_create);
#endif
  ALLOW_SYSCALL(futex);

  // RESTRICTED : PROCESS_CONTROL_MEMORY_ADVANCED
  if (categories.find("PROCESS_CONTROL_MEMORY_ADVANCED") != categories.end()) {
    ALLOW_SYSCALL(madvise);
    ALLOW_SYSCALL(mbind);
    ALLOW_SYSCALL(migrate_pages);
    ALLOW_SYSCALL(set_mempolicy);
    ALLOW_SYSCALL(uselib);
#if __NR_membarrier
    ALLOW_SYSCALL(membarrier);
#endif
  }

  // RESTRICTED : PROCESS_CONTROL_NEW_PROCESS_THREAD
  if (categories.find("PROCESS_CONTROL_NEW_PROCESS_THREAD") != categories.end()) {
    ALLOW_SYSCALL(clone);
    ALLOW_SYSCALL(execve);
#if __NR_execveat
    ALLOW_SYSCALL(execveat);
#endif
    ALLOW_SYSCALL(fork);
    ALLOW_SYSCALL(set_tid_address);
    ALLOW_SYSCALL(vfork);
  }

  // WHITELIST : PROCESS_CONTROL_WAITING
  ALLOW_SYSCALL(epoll_create);
  ALLOW_SYSCALL(epoll_create1);
  ALLOW_SYSCALL(epoll_ctl);
  ALLOW_SYSCALL(epoll_ctl_old);
  ALLOW_SYSCALL(epoll_pwait);
  ALLOW_SYSCALL(epoll_wait);
  ALLOW_SYSCALL(epoll_wait_old);
  ALLOW_SYSCALL(eventfd);
  ALLOW_SYSCALL(eventfd2);
  ALLOW_SYSCALL(idle);
  ALLOW_SYSCALL(_newselect);
  ALLOW_SYSCALL(poll);
  ALLOW_SYSCALL(ppoll);
  ALLOW_SYSCALL(pselect6);
  ALLOW_SYSCALL(select);
  ALLOW_SYSCALL(wait4);
  ALLOW_SYSCALL(waitid);
  ALLOW_SYSCALL(waitpid);
  ALLOW_SYSCALL(getegid);
  ALLOW_SYSCALL(getegid32);
  ALLOW_SYSCALL(geteuid);
  ALLOW_SYSCALL(geteuid32);
  ALLOW_SYSCALL(getgid);
  ALLOW_SYSCALL(getgid32);
  ALLOW_SYSCALL(getgroups);
  ALLOW_SYSCALL(getgroups32);
  ALLOW_SYSCALL(getpgid);
  ALLOW_SYSCALL(getpgrp);
  ALLOW_SYSCALL(getresgid);
  ALLOW_SYSCALL(getresgid32);
  ALLOW_SYSCALL(getresuid);
  ALLOW_SYSCALL(getresuid32);
  ALLOW_SYSCALL(getuid);
  ALLOW_SYSCALL(getuid32);

  // RESTRICTED : PROCESS_CONTROL_SYNCHRONIZATION
  if (categories.find("PROCESS_CONTROL_SYNCHRONIZATION") != categories.end()) {
    ALLOW_SYSCALL(prctl);
    ALLOW_SYSCALL(ptrace);
    ALLOW_SYSCALL(quotactl);
  }

  // RESTRICTED : PROCESS_CONTROL_SCHEDULING
  if (categories.find("PROCESS_CONTROL_SCHEDULING") != categories.end()) {
    ALLOW_SYSCALL(getcpu);
    ALLOW_SYSCALL(getpriority);
    ALLOW_SYSCALL(nice);
    ALLOW_SYSCALL(pause);
    ALLOW_SYSCALL(sched_get_priority_max);
    ALLOW_SYSCALL(sched_get_priority_min);
    ALLOW_SYSCALL(sched_getaffinity);
    ALLOW_SYSCALL(sched_getattr);
    ALLOW_SYSCALL(sched_getparam);
    ALLOW_SYSCALL(sched_getscheduler);
    ALLOW_SYSCALL(sched_rr_get_interval);
    ALLOW_SYSCALL(sched_setaffinity);
    ALLOW_SYSCALL(sched_setattr);
    ALLOW_SYSCALL(sched_setparam);
    ALLOW_SYSCALL(sched_setscheduler);
    ALLOW_SYSCALL(sched_yield);
    ALLOW_SYSCALL(setpriority);
  }

  // RESTRICTED : PROCESS_CONTROL_ADVANCED
  if (categories.find("PROCESS_CONTROL_ADVANCED") != categories.end()) {
    ALLOW_SYSCALL(getrusage);
    ALLOW_SYSCALL(move_pages);
    ALLOW_SYSCALL(prlimit64);
#ifdef __NR_seccomp
    ALLOW_SYSCALL(seccomp);
#endif
    ALLOW_SYSCALL(setrlimit);
    ALLOW_SYSCALL(ugetrlimit);
    ALLOW_SYSCALL(ulimit);
#ifdef __NR_userfaultfd
    ALLOW_SYSCALL(userfaultfd);
#endif
  }

  // RESTRICTED : PROCESS_CONTROL_GET_SET_USER_GROUP_ID
  if (categories.find("PROCESS_CONTROL_GET_SET_USER_GROUP_ID") != categories.end()) {
    ALLOW_SYSCALL(setfsgid);
    ALLOW_SYSCALL(setfsgid32);
    ALLOW_SYSCALL(setfsuid);
    ALLOW_SYSCALL(setfsuid32);
    ALLOW_SYSCALL(setgid);
    ALLOW_SYSCALL(setgid32);
    ALLOW_SYSCALL(setgroups);
    ALLOW_SYSCALL(setgroups32);
    ALLOW_SYSCALL(setpgid);
    ALLOW_SYSCALL(setregid);
    ALLOW_SYSCALL(setregid32);
    ALLOW_SYSCALL(setresgid);
    ALLOW_SYSCALL(setresgid32);
    ALLOW_SYSCALL(setresuid);
    ALLOW_SYSCALL(setresuid32);
    ALLOW_SYSCALL(setreuid);
    ALLOW_SYSCALL(setreuid32);
    ALLOW_SYSCALL(setuid);
    ALLOW_SYSCALL(setuid32);
  }

  // ================================================================================

  // WHITELIST : FILE_MANAGEMENT
  ALLOW_SYSCALL(access);
  ALLOW_SYSCALL(_llseek);
  ALLOW_SYSCALL(close);
  ALLOW_SYSCALL(creat);
  ALLOW_SYSCALL(dup);
  ALLOW_SYSCALL(dup2);
  ALLOW_SYSCALL(dup3);
  ALLOW_SYSCALL(fstat);
  ALLOW_SYSCALL(fstat64);
  ALLOW_SYSCALL(fstatat64);
  ALLOW_SYSCALL(fstatfs);
  ALLOW_SYSCALL(fstatfs64);
  ALLOW_SYSCALL(fsync);
  ALLOW_SYSCALL(ftime);
  ALLOW_SYSCALL(ftruncate);
  ALLOW_SYSCALL(ftruncate64);
  ALLOW_SYSCALL(futimesat);
  ALLOW_SYSCALL(getcwd);
  ALLOW_SYSCALL(getdents);
  ALLOW_SYSCALL(getdents64);
  ALLOW_SYSCALL(link);
  ALLOW_SYSCALL(linkat);
  ALLOW_SYSCALL(lseek);
  ALLOW_SYSCALL(lstat);
  ALLOW_SYSCALL(lstat64);
  ALLOW_SYSCALL(mkdir);
  ALLOW_SYSCALL(mkdirat);
  ALLOW_SYSCALL(newfstatat);
  ALLOW_SYSCALL(oldfstat);
  ALLOW_SYSCALL(oldlstat);
  ALLOW_SYSCALL(oldstat);
  ALLOW_SYSCALL(open);
  ALLOW_SYSCALL(open_by_handle_at);
  ALLOW_SYSCALL(openat);
  ALLOW_SYSCALL(pread64);
  ALLOW_SYSCALL(preadv);
  ALLOW_SYSCALL(pwrite64);
  ALLOW_SYSCALL(pwritev);
  ALLOW_SYSCALL(read);
  ALLOW_SYSCALL(readahead);
  ALLOW_SYSCALL(readdir);
  ALLOW_SYSCALL(readlink);
  ALLOW_SYSCALL(readlinkat);
  ALLOW_SYSCALL(readv);
  ALLOW_SYSCALL(stat);
  ALLOW_SYSCALL(stat64);
  ALLOW_SYSCALL(statfs);
  ALLOW_SYSCALL(statfs64);
  ALLOW_SYSCALL(sync);
  ALLOW_SYSCALL(sync_file_range);
  ALLOW_SYSCALL(syncfs);
  ALLOW_SYSCALL(tee);
  ALLOW_SYSCALL(truncate);
  ALLOW_SYSCALL(truncate64);
  ALLOW_SYSCALL(ustat);
  ALLOW_SYSCALL(write);
  ALLOW_SYSCALL(writev);
  ALLOW_SYSCALL(chdir);
  ALLOW_SYSCALL(fchdir);
  ALLOW_SYSCALL(fadvise64);
  ALLOW_SYSCALL(fadvise64_64);
  ALLOW_SYSCALL(get_robust_list);
  ALLOW_SYSCALL(set_robust_list);

  // RESTRICTED : FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY
  if (categories.find("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY") != categories.end()) {
    ALLOW_SYSCALL(rename);
    ALLOW_SYSCALL(renameat);
    ALLOW_SYSCALL(renameat2);
    ALLOW_SYSCALL(rmdir);
    ALLOW_SYSCALL(unlink);
    ALLOW_SYSCALL(unlinkat);
  }

  // RESTRICTED : FILE_MANAGEMENT_PERMISSIONS
  if (categories.find("FILE_MANAGEMENT_PERMISSIONS") != categories.end()) {
    ALLOW_SYSCALL(chmod);
    ALLOW_SYSCALL(chown);
    ALLOW_SYSCALL(chown32);
    ALLOW_SYSCALL(chroot);
    ALLOW_SYSCALL(fchmod);
    ALLOW_SYSCALL(fchmodat);
    ALLOW_SYSCALL(fchown);
    ALLOW_SYSCALL(fchown32);
    ALLOW_SYSCALL(fchownat);
    ALLOW_SYSCALL(flock);
    ALLOW_SYSCALL(lchown);
    ALLOW_SYSCALL(lchown32);
    ALLOW_SYSCALL(lock);
    ALLOW_SYSCALL(mount);
    ALLOW_SYSCALL(symlink);
    ALLOW_SYSCALL(symlinkat);
    ALLOW_SYSCALL(umask);
    ALLOW_SYSCALL(umount);
    ALLOW_SYSCALL(umount2);
  }

  // RESTRICTED : FILE_MANAGEMENT_CAPABILITIES
  if (categories.find("FILE_MANAGEMENT_CAPABILITIES") != categories.end()) {
    ALLOW_SYSCALL(capget);
    ALLOW_SYSCALL(capset);
  }

  // RESTRICTED : FILE_MANAGEMENT_EXTENDED_ATTRIBUTES
  if (categories.find("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES") != categories.end()) {
    ALLOW_SYSCALL(fgetxattr);
    ALLOW_SYSCALL(flistxattr);
    ALLOW_SYSCALL(fremovexattr);
    ALLOW_SYSCALL(fsetxattr);
    ALLOW_SYSCALL(getxattr);
    ALLOW_SYSCALL(lgetxattr);
    ALLOW_SYSCALL(listxattr);
    ALLOW_SYSCALL(llistxattr);
    ALLOW_SYSCALL(lremovexattr);
    ALLOW_SYSCALL(lsetxattr);
    ALLOW_SYSCALL(removexattr);
    ALLOW_SYSCALL(setxattr);
  }

  // RESTRICTED : FILE_MANAGEMENT_RARE
  if (categories.find("FILE_MANAGEMENT_RARE") != categories.end()) {
    ALLOW_SYSCALL(fcntl);
    ALLOW_SYSCALL(fcntl64);
  }

  // ================================================================================

  // WHITELIST : DEVICE_MANAGEMENT
  ALLOW_SYSCALL(ioctl);

  // RESTRICTED : DEVICE_MANAGEMENT_ADVANCED
  if (categories.find("DEVICE_MANAGEMENT_ADVANCED") != categories.end()) {
    ALLOW_SYSCALL(gtty);
    ALLOW_SYSCALL(io_cancel);
    ALLOW_SYSCALL(io_destroy);
    ALLOW_SYSCALL(io_getevents);
    ALLOW_SYSCALL(io_setup);
    ALLOW_SYSCALL(io_submit);
    ALLOW_SYSCALL(ioperm);
    ALLOW_SYSCALL(iopl);
    ALLOW_SYSCALL(ioprio_get);
    ALLOW_SYSCALL(ioprio_set);
  }

  // RESTRICTED : DEVICE_MANAGEMENT_NEW_DEVICE
  if (categories.find("DEVICE_MANAGEMENT_NEW_DEVICE") != categories.end()) {
    ALLOW_SYSCALL(mknod);
    ALLOW_SYSCALL(mknodat);
  }

  // ================================================================================

  // WHITELIST : INFORMATION_MAINTENANCE
  ALLOW_SYSCALL(clock_getres);
  ALLOW_SYSCALL(clock_gettime);
  ALLOW_SYSCALL(clock_nanosleep);
  ALLOW_SYSCALL(gettimeofday);
  ALLOW_SYSCALL(oldolduname);
  ALLOW_SYSCALL(olduname);
  ALLOW_SYSCALL(stime);
  ALLOW_SYSCALL(time);
  ALLOW_SYSCALL(times);
  ALLOW_SYSCALL(uname);
  ALLOW_SYSCALL(utime);
  ALLOW_SYSCALL(utimensat);
  ALLOW_SYSCALL(utimes);
#ifdef __NR_getrandom
  ALLOW_SYSCALL(getrandom);
#endif
  ALLOW_SYSCALL(sysinfo);

  // RESTRICTED : INFORMATION_MAINTENANCE_ADVANCED
  if (categories.find("INFORMATION_MAINTENANCE_ADVANCED") != categories.end()) {
    ALLOW_SYSCALL(personality);
    ALLOW_SYSCALL(reboot);
    ALLOW_SYSCALL(setdomainname);
    ALLOW_SYSCALL(sethostname);
    ALLOW_SYSCALL(settimeofday);
    ALLOW_SYSCALL(shutdown);
    ALLOW_SYSCALL(swapoff);
    ALLOW_SYSCALL(swapon);
    ALLOW_SYSCALL(_sysctl);
    ALLOW_SYSCALL(syslog);
  }

  // FORBIDDEN : INFORMATION_MAINTENANCE_SET_TIME
  if (0) {
    ALLOW_SYSCALL(clock_adjtime);
    ALLOW_SYSCALL(clock_settime);
  }

  // ================================================================================

  // RESTRICTED : COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL
  if (categories.find("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL") != categories.end()) {
    ALLOW_SYSCALL(connect);
    ALLOW_SYSCALL(socket);
  }

  // RESTRICTED : COMMUNICATIONS_AND_NETWORKING_SOCKETS
  if (categories.find("COMMUNICATIONS_AND_NETWORKING_SOCKETS") != categories.end()) {
    ALLOW_SYSCALL(accept);
    ALLOW_SYSCALL(accept4);
    ALLOW_SYSCALL(bind);
    ALLOW_SYSCALL(getpeername);
    ALLOW_SYSCALL(getsockname);
    ALLOW_SYSCALL(getsockopt);
    ALLOW_SYSCALL(listen);
    ALLOW_SYSCALL(recvfrom);
    ALLOW_SYSCALL(recvmmsg);
    ALLOW_SYSCALL(recvmsg);
    ALLOW_SYSCALL(sendmmsg);
    ALLOW_SYSCALL(sendmsg);
    ALLOW_SYSCALL(sendto);
    ALLOW_SYSCALL(setsockopt);
    ALLOW_SYSCALL(socketcall);
    ALLOW_SYSCALL(socketpair);
  }

  // RESTRICTED : COMMUNICATIONS_AND_NETWORKING_SIGNALS
  if (categories.find("COMMUNICATIONS_AND_NETWORKING_SIGNALS") != categories.end()) {
    ALLOW_SYSCALL(alarm);
    ALLOW_SYSCALL(timer_create);
    ALLOW_SYSCALL(timer_delete);
    ALLOW_SYSCALL(timer_getoverrun);
    ALLOW_SYSCALL(timer_gettime);
    ALLOW_SYSCALL(timer_settime);
    ALLOW_SYSCALL(timerfd_create);
    ALLOW_SYSCALL(timerfd_gettime);
    ALLOW_SYSCALL(timerfd_settime);
    ALLOW_SYSCALL(getitimer);
    ALLOW_SYSCALL(rt_sigpending);
    ALLOW_SYSCALL(rt_sigqueueinfo);
    ALLOW_SYSCALL(rt_sigreturn);
    ALLOW_SYSCALL(rt_sigsuspend);
    ALLOW_SYSCALL(rt_sigtimedwait);
    ALLOW_SYSCALL(rt_tgsigqueueinfo);
    ALLOW_SYSCALL(setitimer);
    ALLOW_SYSCALL(sigaltstack);
    ALLOW_SYSCALL(signal);
    ALLOW_SYSCALL(signalfd);
    ALLOW_SYSCALL(signalfd4);
    ALLOW_SYSCALL(sigpending);
    ALLOW_SYSCALL(sigreturn);
    ALLOW_SYSCALL(sigsuspend);
  }

  // RESTRICTED : COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION
  if (categories.find("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION") != categories.end()) {
    ALLOW_SYSCALL(getpmsg);
    ALLOW_SYSCALL(ipc);
    ALLOW_SYSCALL(mq_getsetattr);
    ALLOW_SYSCALL(mq_notify);
    ALLOW_SYSCALL(mq_open);
    ALLOW_SYSCALL(mq_timedreceive);
    ALLOW_SYSCALL(mq_timedsend);
    ALLOW_SYSCALL(mq_unlink);
    ALLOW_SYSCALL(msgctl);
    ALLOW_SYSCALL(msgget);
    ALLOW_SYSCALL(msgrcv);
    ALLOW_SYSCALL(msgsnd);
    ALLOW_SYSCALL(pipe);
    ALLOW_SYSCALL(pipe2);
    ALLOW_SYSCALL(semctl);
    ALLOW_SYSCALL(semget);
    ALLOW_SYSCALL(semop);
    ALLOW_SYSCALL(semtimedop);
    ALLOW_SYSCALL(shmat);
    ALLOW_SYSCALL(shmctl);
    ALLOW_SYSCALL(shmdt);
    ALLOW_SYSCALL(shmget);
#ifdef __NR_bpf
    ALLOW_SYSCALL(bpf);
#endif
  }

  // RESTRICTED : TGKILL
  if (categories.find("TGKILL") != categories.end()) {
    ALLOW_SYSCALL(tgkill);
  }

  // RESTRICTED : COMMUNICATIONS_AND_NETWORKING_KILL
  if (categories.find("COMMUNICATIONS_AND_NETWORKING_KILL") != categories.end()) {
    ALLOW_SYSCALL(kill);
    ALLOW_SYSCALL(tkill);
  }

  // RESTRICTED : UNKNOWN
  // ================================================================================
  if (categories.find("UNKNOWN") != categories.end()) {
    ALLOW_SYSCALL(acct);
    ALLOW_SYSCALL(add_key);
    ALLOW_SYSCALL(adjtimex);
    ALLOW_SYSCALL(afs_syscall);
    ALLOW_SYSCALL(bdflush);
    ALLOW_SYSCALL(break);
    ALLOW_SYSCALL(faccessat);
    ALLOW_SYSCALL(fallocate);
    ALLOW_SYSCALL(fanotify_init);
    ALLOW_SYSCALL(fanotify_mark);
    ALLOW_SYSCALL(fdatasync);
    ALLOW_SYSCALL(get_kernel_syms);
    ALLOW_SYSCALL(get_mempolicy);
    ALLOW_SYSCALL(getsid);
    ALLOW_SYSCALL(inotify_add_watch);
    ALLOW_SYSCALL(inotify_init);
    ALLOW_SYSCALL(inotify_init1);
    ALLOW_SYSCALL(inotify_rm_watch);
    ALLOW_SYSCALL(kcmp);
    ALLOW_SYSCALL(kexec_load);
#ifdef __NR_kexec_file_load
    ALLOW_SYSCALL(kexec_file_load);
#endif
    ALLOW_SYSCALL(keyctl);
    ALLOW_SYSCALL(lookup_dcookie);
    ALLOW_SYSCALL(mincore);
    ALLOW_SYSCALL(mlock);
#ifdef __NR_mlock2
    ALLOW_SYSCALL(mlock2);
#endif
    ALLOW_SYSCALL(mlockall);
    ALLOW_SYSCALL(mpx);
    ALLOW_SYSCALL(msync);
    ALLOW_SYSCALL(munlock);
    ALLOW_SYSCALL(munlockall);
    ALLOW_SYSCALL(name_to_handle_at);
    ALLOW_SYSCALL(nfsservctl);
    ALLOW_SYSCALL(perf_event_open);
    ALLOW_SYSCALL(pivot_root);
    ALLOW_SYSCALL(process_vm_readv);
    ALLOW_SYSCALL(process_vm_writev);
    ALLOW_SYSCALL(prof);
    ALLOW_SYSCALL(profil);
    ALLOW_SYSCALL(putpmsg);
    ALLOW_SYSCALL(request_key);
    ALLOW_SYSCALL(security);
    ALLOW_SYSCALL(sendfile);
    ALLOW_SYSCALL(sendfile64);
    ALLOW_SYSCALL(setns);
    ALLOW_SYSCALL(setsid);
    ALLOW_SYSCALL(sgetmask);
    ALLOW_SYSCALL(splice);
    ALLOW_SYSCALL(ssetmask);
    ALLOW_SYSCALL(stty);
    ALLOW_SYSCALL(sysfs);
    ALLOW_SYSCALL(tuxcall);
    ALLOW_SYSCALL(unshare);
    ALLOW_SYSCALL(vhangup);
    ALLOW_SYSCALL(vm86);
    ALLOW_SYSCALL(vm86old);
    ALLOW_SYSCALL(vmsplice);
    ALLOW_SYSCALL(vserver);
  }

  // RESTRICTED : UNKNOWN_MODULE
  if (categories.find("UNKNOWN_MODULE") != categories.end()) {
    ALLOW_SYSCALL(create_module);
    ALLOW_SYSCALL(delete_module);
    ALLOW_SYSCALL(finit_module);
    ALLOW_SYSCALL(init_module);
    ALLOW_SYSCALL(query_module);
  }

  // RESTRICTED : UNKNOWN_REMAP_PAGES
  if (categories.find("UNKNOWN_REMAP_PAGES") != categories.end()) {
    ALLOW_SYSCALL(remap_file_pages);
  }

  // ================================================================================
}
