#ifndef __CONFIG_H__
#define __CONFIG_H__

#define ASSIGNMENT_MESSAGE "Test of LineHighlight"

#define TOTAL_POINTS 40
#define AUTO_POINTS 40
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0

// ===================================================================================
std::vector<TestCase> testcases
{
  
  // Test 1: FULLY CORRECT SOLUTION
  TestCase::MakeTestCase
    ("words at least 5 chars long",
     "gettysburg_address.txt",
     "/usr/bin/python code_correct.py gettysburg_address.txt output_correct.txt",
     TestCasePoints(10),
     { new TestCaseComparison( &diffLineSwapOk,
                               "output_correct.txt",
                               "",
                               "output_instructor.txt",
                               1.0
                               )
         }
     ),
   
  // Test 2: DUPLICATE LINES
  TestCase::MakeTestCase
    ("words at least 5 chars long",
     "gettysburg_address.txt",
     "/usr/bin/python code_duplicate_lines.py gettysburg_address.txt output_duplicates.txt",
     TestCasePoints(10),
     {new TestCaseComparison( &diffLineSwapOk,
                              "output_duplicates.txt",
                              "",
                              "output_instructor.txt",
                              1.0
                              )
         }
     ),

  // Test 3: EXTRA LINES
  TestCase::MakeTestCase
    ("words at least 5 chars long",
     "gettysburg_address.txt",
     "/usr/bin/python code_extra_lines_no_duplicates.py gettysburg_address.txt output_extra.txt",
     TestCasePoints(10),
     {new TestCaseComparison( &diffLineSwapOk,
                              "output_extra.txt",
                              "",
                              "output_instructor.txt",
                              1.0
                              )
         }
     ),

  // Test 4: MISSING LINES
  TestCase::MakeTestCase
    ("words at least 5 chars long",
     "gettysburg_address.txt",
     "/usr/bin/python code_missing_lines.py gettysburg_address.txt output_missing.txt",
     TestCasePoints(10),
     {new TestCaseComparison( &diffLineSwapOk,
                              "output_missing.txt",
                              "",
                              "output_instructor.txt",
                              1.0
                              )
         }
     )
    };

#endif
