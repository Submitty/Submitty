/* OUTPUT: ERROR: FLOATING POINT ERROR
 *         Program Terminated
 * Child exited with status 8
 * Note: Throws during runtime occurrence of a division by zero
 * However, when explicitly dividing by 0 (i.e. 1/x), a warning is thrown
 * and exception not caught (prints out a large number if you were to print out result):
 * Compilation output:
 * floating_point_err.cpp:5:14: warning: division by zero is undefined [-Wdivision-by-zero]
 * int x = 1/0;
 *          ^~
 * 1 warning generated.
 * Message located in execute.cpp
 */

int foo()
{
    return 0;
}

int main()
{
    return 100 / foo();
}
