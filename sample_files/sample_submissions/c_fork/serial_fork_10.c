#include <stdio.h>
#include <sys/types.h>
#include <unistd.h> 
#include <stdlib.h>
#include <sys/wait.h>

int main() {

  int i;
  pid_t pid;
  pid_t my_pid;
  int status;

  /* note: need to flush the buffer after printing, or the child will
     flush the shared file handle at its exit and result in duplicate
     prints */

  for (i = 0; i < 10; i++) {
    pid = fork();
    if (pid == 0) {
      /* child process */
      my_pid = getpid();
      printf ("loop %d:  child pid %d started\n", i, my_pid);
      fflush(stdout);
      /* pause for 1/10th of a second */
      usleep(100000);
      return 0;
    } else {
      /* parent process */
      /* wait for child to complete */
      waitpid(pid,&status,0);
      my_pid = getpid();
      printf ("loop %d:  pid %d, waited for child pid %d to finish\n", i, my_pid, pid);
      fflush(stdout);
      usleep(100000);
    }
  }
  return 0;
}
