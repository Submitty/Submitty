#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <errno.h>
#include <grp.h>

/* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/* 

compile instructions (using c static library, for security, to eliminate
shared library that could be manipulated):

    g++ -static untrusted_execute.c -o untrusted_execute

change permissions & set suid: (must be root)

    chown root untrusted_execute
    chgrp hwcron untrusted_execute
    chmod 4550 untrusted_execute
*/
/* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */



int main(int argc, char* argv[]) {
  int res;
  uid_t euid;

  /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
  /* THIS PROGRAM ASSUMES THAT A SPECIAL UNTRUSTED USER & GROUP EXISTS IN THE SYSTEM
     IF THEIR IDS CHANGE THIS PROGRAM MUST BE EDITED
     THE SUID BIT & PERMISSIONS MUST BE SET CORRECTLY ON THE EXECUTABLE
  */
  static const uid_t ROOT_UID = 0;         /* root's user id & group id */
  static const uid_t UNTRUSTED_UID = __INSTALL__FILLIN__UNTRUSTED_UID__; /* untrusted's user id */
  static const uid_t UNTRUSTED_GID = __INSTALL__FILLIN__UNTRUSTED_GID__; /* untrusted's group id */
  static const uid_t HWCRON_UID    = __INSTALL__FILLIN__HWCRON_UID__;    /* hwcron's user id */
  static const uid_t HWCRON_GID    = __INSTALL__FILLIN__HWCRON_GID__;    /* hwcron's group id */
  /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

  /* Sanity check, this program must be run by hwcron, with
     effective uid root (suid root bit must be set) */
  if (geteuid() != ROOT_UID || getuid() != HWCRON_UID) {
    fprintf(stderr,"INTERNAL ERROR: BAD USER\n");
    fprintf(stderr,"uid:%d euid:%d",getuid(),geteuid());
    exit(1);
  }

  /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
  /* Check the file permissions of this program
     owner of this file: root
     group of this file: hwcron
     suid bit should be set
     user(root) rx
     group(hwcron) rx
     other nothing
  */
  /* We assume we're on Linux! */
  struct stat stat_data;
  res = stat("/proc/self/exe", &stat_data);
  if (res != 0) {
    fprintf(stderr,"INTERNAL ERROR: FAILED TO STAT SELF\n");
    perror("stat error: ");
    exit(1);
  }
  static const int CORRECT_PERMS = S_IFREG|S_ISUID|S_IRUSR|S_IXUSR|S_IRGRP|S_IXGRP;
  if (stat_data.st_mode != CORRECT_PERMS) {
    fprintf(stderr,"INTERNAL ERROR: file permissions 0x%x (vs 0x%x) are invalid!\n", stat_data.st_mode,
	    CORRECT_PERMS);
    exit(1);
  }
  if (stat_data.st_uid != ROOT_UID ||
      stat_data.st_gid != HWCRON_GID) {
    fprintf(stderr,"INTERNAL ERROR: file uid %d gid %d are invalid\n", stat_data.st_uid, stat_data.st_gid);
    exit(1);
  }

  /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */  
  /* Drop privileges (groups, gid, uid) */
  
  /* make sure to clear out the secondary groups first */
  gid_t my_gid = UNTRUSTED_GID;
  res = setgroups (1,&my_gid);
  if (res != 0) {
    fprintf(stderr,"INTERNAL ERROR: FAILED TO DROP GROUPS (setgroups)\n");
    perror("setgroups error: ");
    exit(1);
  }

  /* switch the group id to the untrusted group id */
  res = setresgid(UNTRUSTED_GID, UNTRUSTED_GID, UNTRUSTED_GID); 
  if (res != 0) {
    fprintf(stderr,"INTERNAL ERROR: FAILED TO DROP GROUP PRIVS\n");
    perror("setresgid error: ");
    exit(1);
  }

  /* switch the user id to the untrusted user id */
  res = setresuid(UNTRUSTED_UID, UNTRUSTED_UID, UNTRUSTED_UID); 
  if (res != 0) {
    fprintf(stderr,"INTERNAL ERROR: FAILED TO DROP USER PRIVS\n");
    perror("setresuid error: ");
    exit(1);
  }

  /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */  
  /* Verify that we have a at least one argument, the name of the program to run... */
  
  if (argc < 2) {
    fprintf(stderr,"INTERNAL ERROR: ARGC < 2\n");
    exit(1);
  }

  /* chop off this executable, and run the the program specified by the rest of the args */
  char *envp[1] = {NULL};
  /* clears the environment variables, etc. */
  execve(argv[1], argv+1, envp);
  perror("exec");
  fprintf(stderr,"INTERNAL ERROR: exec failed\n");
  fprintf(stderr,"%s\n",argv[1]);
  exit (1);
}

