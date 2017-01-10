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
      /* pause for 1/2 of a second */
      usleep(500000);
      printf ("loop %d:  child pid %d going to exit\n", i, my_pid);
      fflush(stdout);
      return 0;
    } else {
      /* parent process */
      /* pause for 1/100th of a second */
      usleep(10000);
    }
  }

  /* wait for all children to finish */
  for (i = 0; i < 10; i++) {
    wait(&status);
  }
  printf ("ALL DONE!\n");
  return 0;
}
