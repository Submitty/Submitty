#include <stdio.h>
#include <unistd.h>

/* don't run this on your native computer... */

int main(void)
{
  printf ("intentionally launching a fork bomb\n");
  fflush(stdout);
  
  while(1) {
    fork(); 
  }
}
