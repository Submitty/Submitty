#Error Message Documentation

>Documentation for available test cases written for error messages thrown by the
>compiler in the grading system. Will primarily discuss the adequateness of the
>messages.

1. Abort signal
   - Current message: ERROR: ABORT SIGNAL Program Terminated
   - APPROVAL: REJECT
   - Should reject because they are a variety of causes for abort signals, but
     can also argue that an accompanying error message (like an assert fail or
     file error) will appear
   - Notes: Abort signals usually occur because of failing asserts or from memory
     errors like heap overflow
2. Bad system call
    - Current message: ERROR: DETECTED BAD SYSTEM CALL Program Terminated
    - APPROVAL: REJECT
    - Should reject because a variety of causes for bad system call.  Most likely,
      this error will be caused due to students using black listed system calls like
      fork.  Should add a note to check for warnings.  As instructors cannot give out
      what they have blacklisted, so just put a note saying that the student did
      something wrong.
3. Floating point error
    - Current message: ERROR: FLOATING POINT ERROR Program Terminated
    - APPROVAL: REJECT
    - Should provide some sort of accompanying message suggesting students use a
      debugger like gdb or such (for specific language).  Should also provide some
      note to check for warnings.
    - Notes: This error is only thrown when there isn't an obvious floating point error
      like dividing by 0 as the compiler will throw a warning instead.  Possibly should
      find a way to combine these two possibilities to minimize confusion (although it
      seems unlikely that people will outright divide by 0)
4. Kill signal
    - Current message: ERROR: KILL SIGNAL Program Terminated
    - APPROVAL: REJECT
    - Should reject because 'KILL SIGNAL' is a daunting message to receive for beginners.
      Also error can be thrown for variety of issues (the example test case is for an
      infinte loop). Need to compile a list of most frequent reasons for getting the signal.
    - Note also gets called when the maximum runtime is exceeded - should distinguish between
      cpu time and run time.
5. File size too large
    - Current message: ERROR: FILE SIZE LIMIT EXCEEDED Program Terminated
    - APPROVAL: ACCEPT
    - Message is self explanatory, but might want to add that it's most likely because
      too much output was generated.
6. Invalid memory
    - Current message: ERROR: INVALID MEMORY REFERENCE Program Terminated
    - APPROVAL: REJECT
    - Should reject because there is a large number of ways segmentation fault can be
      generated.  Need to have a suggestion to use gdb or some sort of appropriate
      debugger to solve issue.  Can also give a list of most common seg fault reasons
      like referencing deallocated memory, going outside of bounds, deallocating already
      deallocated memory, etc.
