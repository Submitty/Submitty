/* OUTPUT: ERROR: FILE SIZE LIMIT EXCEEDED
 *         Program Terminated
 *         (RED) ERROR! Student file too large for grader
 * Child exited with signal 25 or 31
 * Note: Terminated because it hit the max file size set by instructor
 * Message located in execute.cpp
 */

#include <iostream>

int main()
{
    while(true)
    {
        std::cout << "Hello" << std::endl;
    }

    return 0;
}
