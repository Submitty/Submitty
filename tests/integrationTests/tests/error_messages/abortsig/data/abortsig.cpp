/* OUTPUT: ERROR: ABORT SIGNAL
 *         Program Terminated
 *         (RED) WARNING: This file should be empty (Referring to STDERR)
 *         Student Standard ERROR (STDERR)
 *         *** Error in `./a.out': free(): invalid next size (fast): 0x0000000000efa
 * Child exited with status 6
 * Note: Probably caused by a heap overflow - in this cases
 * trying to access memory out of bounds of dynamically
 * allocated array
 * Message located in execute.cpp
 */

int main(void)
{
    int *j = new int[10];
    for (int i = 0; i < 15; ++i) {
        j[i] = i;
    }
    delete[] j;
}
