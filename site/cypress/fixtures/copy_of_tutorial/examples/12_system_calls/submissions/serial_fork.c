#include <stdio.h>
#include <sys/types.h>
#include <unistd.h> 
#include <stdlib.h>
#include <sys/wait.h>
#include <assert.h>

int main(int argc, char* argv[]) {

  int i;
  pid_t pid;
  pid_t my_pid;
  int status;
  int fork_count;
  int fork_success;

  assert (argc == 2);
  fork_count = atoi (argv[1]);
  assert (fork_count >= 0);

  /* note: need to flush the buffer after printing, or the child will
     flush the shared file handle at its exit and result in duplicate
     prints */

  fork_success = 0;
  for (i = 0; i < fork_count; i++) {
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
      if (pid == -1) {
        printf ("loop %d:  FORK FAILED\n", i);
        fflush(stdout);
      } else {
        /* wait for child to complete */
        waitpid(pid,&status,0);
        my_pid = getpid();
        printf ("loop %d:  pid %d, waited for child pid %d to finish\n", i, my_pid, pid);
        fflush(stdout);
        fork_success++;
      }
      usleep(100000);
    }
  }

  printf ("ALL DONE! %d successful forks\n", fork_success);
  return 0;
}
