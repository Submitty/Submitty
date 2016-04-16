/* OUTPUT: ERROR: DETECTED BAD SYSTEM CALL
 *         Program Terminated
 * Child exited with status 12, 31
 * COMPILATION WARNING: bad_syscall.cpp:8:15:
 *                      warning: unused variable 'pid' [-Wunused-variable]
 *                      pid_t pid = fork();
 *                            ^
 *                      1 warning generated.
 * Note: Most likely caused by a call to fork (preventing a fork bomb)
 * Message located in execute.cpp
 */
#include <stdio.h>
#include <unistd.h>

int main()
{
    while(true)
    {
        pid_t pid = fork();
    }
}
