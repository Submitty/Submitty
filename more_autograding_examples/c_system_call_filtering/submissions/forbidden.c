#define _GNU_SOURCE
#include <unistd.h>
#include <sys/syscall.h>
#include <sys/types.h>
#include <stdio.h>

int main(int argc, char *argv[])
{
    printf("hello world\n");
    fflush(stdout);
    int answer = syscall(SYS_clock_adjtime,0,0,0,0,0,0);
    printf("system call return value = %d\n",answer);
    printf("goodbye\n");
    return 0;
}


