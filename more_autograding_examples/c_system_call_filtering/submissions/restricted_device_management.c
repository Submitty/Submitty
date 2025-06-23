#define _GNU_SOURCE
#include <unistd.h>
#include <sys/syscall.h>
#include <sys/types.h>
#include <stdio.h>

int main(int argc, char *argv[])
{
    printf("hello world\n");
    fflush(stdout);
    int answer = syscall(SYS_io_cancel,0,0,0,0,0,0);
    printf("goodbye\n");
    return 0;
}
