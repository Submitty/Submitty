#include <stdio.h>
#include <unistd.h>

/* don't run this on your native computer... */

int main(void)
{
  pid_t pid;
  
  printf ("intentionally launching a fork bomb\n");
  fflush(stdout);
  
  while(1) {
    pid = fork(); 
    if (pid == -1) {
      printf ("FORK FAILED\n");
      fflush(stdout);
    } else {
      printf ("fork success\n");
      fflush(stdout);
    }
  }
}
