/* OUTPUT: ERROR: INVALID MEMORY REFERENCE
 *         Program Terminated
 * Child exited with status 11
 * Note: Terminated due to segfault from trying to dereference a null pointer 
 * Message located in execute.cpp
 */

#include <iostream>

void foo(int * i)
{
    std::cout << *i;
}

int main()
{
    int *i = NULL;
    foo(i);
}
