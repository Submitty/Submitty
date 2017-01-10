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
  int finish_count;

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
      /* pause for 1/2 of a second */
      usleep(500000);
      printf ("loop %d:  child pid %d going to exit\n", i, my_pid);
      fflush(stdout);
      return 0;
    } else {
      /* parent process */
      if (pid == -1) {
        printf ("loop %d:  FORK FAILED\n", i);
        fflush(stdout);
      } else {
        fork_success++;
      }
      /* pause for 1/100th of a second */
      usleep(10000);
    }
  }

  /* wait for all children to finish */
  finish_count = 0;
  for (i = 0; i < fork_count; i++) {
    int child_pid = wait(&status);
    printf ("child %d finished\n", child_pid);
    if (child_pid != -1) finish_count++;
    fflush(stdout);
  }
  assert(finish_count == fork_success);

  printf ("ALL DONE! %d successful forks\n", fork_success);
  fflush(stdout);
  return 0;
}
