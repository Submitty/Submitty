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
  int fork_requested;
  int tree_height;
  pid_t parent_pid;

  parent_pid = getpid();

  assert (argc == 2);
  fork_requested = atoi (argv[1]);
  assert (fork_requested >= 0);

  if (fork_requested == 0) tree_height = 0;
  else if (fork_requested == 1) tree_height = 1;
  else if (fork_requested <= 3) tree_height = 2;
  else if (fork_requested <= 7) tree_height = 3;
  else if (fork_requested <= 15) tree_height = 4;
  else if (fork_requested <= 31) tree_height = 5;
  else tree_height = 6;

  printf ("requested %d forks, approximating with tree of height %d\n", fork_requested, tree_height);
  fflush(stdout);

  /* note: need to flush the buffer after printing, or the child will
     flush the shared file handle at its exit and result in duplicate
     prints */

  for (i = 0; i < tree_height; i++) {
    pid = fork();
    if (pid == -1) {
      printf ("loop %d:  FORK FAILED\n", i);
      fflush(stdout);
    } else if (pid != 0) {
      my_pid = getpid();
      printf ("pid %d spawned child pid %d\n", my_pid, pid);
      fflush(stdout);
    }
    /* pause for 1/100th of a second */
    usleep(10000);
  }

  /* wait for all children to finish */
  while (1) {
    int child_pid = wait(&status);
    if (child_pid == -1) break;
    my_pid = getpid();
    printf ("pid %d's child pid %d finished\n", my_pid, child_pid);
    fflush(stdout);
  }

  if (parent_pid == getpid()) {
    printf ("ALL DONE!\n");
    fflush(stdout);
  }
  return 0;
}
