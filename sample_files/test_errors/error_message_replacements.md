Replacement Error Messages for Current Ones
===========================================

1. Abort signal
   - Current message: OUTPUT: ERROR: ABORT SIGNAL
                      Program Terminated
                      (RED) WARNING: This file should be empty (Refering to STDERR)
                      Student Standard ERROR (STDERR)
                      *** Error in `./a.out': free(): invalid next size (fast): 0x0000000000efa
   - Replacement: ERROR: ABORT SIGNAL
                  Most likely occurred due to a heap overflow; make sure to check that you
                  are properly iterating over arrays.  For further information, please
                  contact your instructor.
   - Note might want to keep the RED warning as well referring to standard error
2. Bad system call
    - Current message: ERROR: DETECTED BAD SYSTEM CALL
                       Program Terminated
      COMPILATION WARNING: bad_syscall.cpp:8:15:
                           warning: unused variable 'pid' [-Wunused-variable]
                           pid_t pid = fork();
                                 ^
                           1 warning generated.
    - Replacement: ERROR: DETECTED BAD SYSTEM CALL
                   Most likely occurred because you used something that your instructor
                   has prohibited.  Look at compiler information in case a warning has
                   been generated that might point out the invalid command used.
3. Floating point error
    - Current message: ERROR: FLOATING POINT ERROR
                       Program Terminated
      COMPILATION WARNING: floating_point_err.cpp:5:14: warning: division by zero is undefined    
                           [-Wdivision-by-zero]
                           int x = 1/0;
                                    ^~
                           1 warning generated.
    - Replacement: ERROR: FLOATING POINT ERROR
                   Make sure to check for warnings in compilation.
                   Most likely has to do with a mathematical error.
4. Kill signal
    - Current message: ERROR: KILL SIGNAL
                       Program Terminated
    - Replacement: ERROR: KILL SIGNAL
                   Most likely stopped because you exceeded the time the instructor
                   set.  You might have an infinite loop or your code might be taking
                   too long.
5. File size too large
    - Current message: ERROR: FILE SIZE LIMIT EXCEEDED
                       Program Terminated
                       (RED) ERROR! Student file too large for grader
    - Replacement: ERROR: FILE SIZE LIMIT EXCEEDED
                   Make sure that your files are no larger than the limit set by the
                   instructor.  If you do not know what that is, ask the instructor.
                   You can also check file sizes at the top by each file uploaded to
                   see their sizes.
    - Note: Might also want to keep the red error message
6. Invalid memory
    - Current message: ERROR: INVALID MEMORY REFERENCE
                       Program Terminated
    - Replacement: ERROR: INVALID MEMORY REFERENCE
                   Try running Dr. Memory and/or gdb to help debug and locate the
                   line or area in your code where the issue is coming up.  Some causes
                   for a segmentation fault include:
                        - Attempting to access non-existent memory address
                        - Dereferencing null pointers, uninitialized pointers, or
                          freed pointers
                        - Buffer overflow: overwriting into nearby memory locations
                        - Stack overflow: running out of memory allocated for your program
    - Note: Should add more reasons for memory errors/correct descriptions as they might
    not be 100% accurate
