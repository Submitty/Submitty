#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

// Grading parameters
#define TOTAL_POINTS 21
#define AUTO_POINTS 21
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0

// ===================================================================================

// Test cases
std::vector<TestCase> testcases
{
    /************* README AND COMPILATION *****************/

    TestCase::MakeFileExists(
    "README",
    "part1/README.txt",
    TestCasePoints(2)
    ),

    TestCase::MakeCompilation(
    "Compilation",
    "/usr/bin/clang++ -Wall -o a.out -- part1/*.cpp",
    "a.out",        // name of .exe created by student
    TestCasePoints(3)
    ),

    /******************** TEST CASES **********************/
    TestCase::MakeTestCase(
         "Regular char by char comparision",                    // title
         "./byChar.exe input.txt output.txt",                   // details
         "./a.out CatBreeds.txt output.txt",                         // command
        TestCasePoints(4),                                      // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false
        {new TestCaseComparison(&myersDiffbyLinebyChar,         // compare function [V]
            "output.txt",                                       // output file name [V]
            "output.txt",                                       // output file description
            "inst_output.txt",                             // expected output file [V]
            1
            ),
        new TestCaseComparison(&warnIfNotEmpty,"STDOUT.txt","STDOUT","", 0),
        new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","STDERR","", 0)
        }),
    TestCase::MakeTestCase(
         "Test of by word comparison",                                  // title
         "./byWord.exe input.txt output.txt",                           // details
         "./a.out CatBreeds.txt output.txt",                                 // command
        TestCasePoints(4),                                              // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false 
        {new TestCaseComparison(&myersDiffbyLinebyWord,                 // compare function [V]
            "output.txt",                                               // output file name [V]
            "output.txt",                                               // output file description
            "inst_output.txt",                                     // expected output file [V]
            1
            ),
        new TestCaseComparison(&warnIfNotEmpty,"STDOUT.txt","STDOUT","", 0),
        new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","STDERR","", 0)
        }),
    TestCase::MakeTestCase(
         "Test of by line comparison",                                  // title
         "./byLine.exe input.txt output.txt",                            // details
         "./a.out CatBreeds.txt output.txt",                                 // command
        TestCasePoints(4),                                              // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false 
        {new TestCaseComparison(&myersDiffbyLine,                       // compare function [V]
            "output.txt",                                               // output file name [V]
            "output.txt",                                               // output file description
            "inst_output.txt",                                     // expected output file [V]
            1
            ),
        new TestCaseComparison(&warnIfNotEmpty,"STDOUT.txt","STDOUT","", 0),
        new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","STDERR","", 0)
        }),
    TestCase::MakeTestCase(
         "Test of by line without whitespace comparison",               // title
         "./byLineNoWhite.exe input.txt output.txt",                     // details
         "./a.out CatBreeds.txt output.txt",                                 // command
        TestCasePoints(4),                                              // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false 
        {new TestCaseComparison(&myersDiffbyLineNoWhite,                // compare function [V]
            "output.txt",                                               // output file name [V]
            "output.txt",                                               // output file description
            "inst_output.txt",                              // expected output file [V]
            1
            ),
        new TestCaseComparison(&warnIfNotEmpty,"STDOUT.txt","STDOUT","", 0),
        new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","STDERR","", 0)
        })
};


#endif
