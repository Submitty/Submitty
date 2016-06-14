#ifndef __CONFIG_H__
#define __CONFIG_H__

#define ASSIGNMENT_MESSAGE "Test of diffLineSwapOk compared to myersDiffbyLinebyChar"

#define TOTAL_POINTS 90
#define AUTO_POINTS 90
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0

// ===================================================================================
std::vector<TestCase> testcases
{
  
  TestCase::MakeTestCase
    ("CORRECT SOLUTION",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_correct.py gettysburg_address.txt output_correct.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&diffLineSwapOk,        "output_correct.txt","","output_instructor.txt",0.5),
       new TestCaseComparison(&myersDiffbyLinebyChar, "output_correct.txt","","output_instructor.txt",0.5),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
     }
    ),
   

  TestCase::MakeTestCase
    ("DUPLICATE LINES - Required Order",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_duplicate_lines.py gettysburg_address.txt output_duplicates.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&myersDiffbyLinebyChar, "output_duplicates.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
     }
    ),
  TestCase::MakeTestCase
    ("DUPLICATE LINES - Re-Ordering OK",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_duplicate_lines.py gettysburg_address.txt output_duplicates.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&diffLineSwapOk,        "output_duplicates.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
     }
    ),


  TestCase::MakeTestCase
    ("EXTRA LINES - Required Order",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_extra_lines_no_duplicates.py gettysburg_address.txt output_extra.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&myersDiffbyLinebyChar, "output_extra.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
     }
    ),
  TestCase::MakeTestCase
    ("EXTRA LINES - Re-Ordering OK",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_extra_lines_no_duplicates.py gettysburg_address.txt output_extra.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&diffLineSwapOk,        "output_extra.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
     }
    ),


  TestCase::MakeTestCase
    ("MISSING LINES - Required Order",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_missing_lines.py gettysburg_address.txt output_missing.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&myersDiffbyLinebyChar, "output_missing.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
         }
     ),  TestCase::MakeTestCase
    ("MISSING LINES - Re-Ordering OK",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_missing_lines.py gettysburg_address.txt output_missing.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&diffLineSwapOk,        "output_missing.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
         }
     ),


  TestCase::MakeTestCase
    ("OUT OF ORDER - Required Order",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_out_of_order.py gettysburg_address.txt output_reordered.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&myersDiffbyLinebyChar, "output_reordered.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
         }
     ),
  TestCase::MakeTestCase
    ("OUT OF ORDER Re-Ordering OK",
     "gettysburg_address.txt",
     "/usr/bin/python part1/code_out_of_order.py gettysburg_address.txt output_reordered.txt",
     TestCasePoints(10),
     { new TestCaseComparison(&diffLineSwapOk,        "output_reordered.txt","","output_instructor.txt",1.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDOUT.txt","Standard OUTPUT (STDOUT)","",0.0),
       new TestCaseComparison(&warnIfNotEmpty,        "STDERR.txt","Standard ERROR (STDERR)","",0.0)
         }
     )

};

#endif
