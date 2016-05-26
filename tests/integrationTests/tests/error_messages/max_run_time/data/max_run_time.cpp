/* OUTPUT: ERROR: KILL SIGNAL
 *         Program Terminated
 *         ERROR: Maximum run time exceeded
 *         Program Terminated
 * Child exited with signal 9
 * Note: Terminated because it hit the maximum time limit set (combination of
 *       CPU time and clock time) - should fix message for both students and
 *       instructors in logfile
 * Message located in execute.cpp
 */

#include <unistd.h>

int main()
{
    sleep(1000);
    return 0;
}
