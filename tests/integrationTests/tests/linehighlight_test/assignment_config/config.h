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
    TestCase::MakeTestCase(
        "words at least 5 chars long",
        "gettysburg_address.txt",
        "/usr/bin/python good_submission.py gettysburg_address.txt student_output.txt",
        TestCasePoints(10),
        {
            new TestCaseComparison( &diffLineSwapOk,
                "student_output.txt",
                "student_output.txt",
                "good_output.txt",
                1.0
            )
        }
    ),
        
    
    TestCase::MakeTestCase(
        "words at least 5 chars long",
        "gettysburg_address.txt",
        "/usr/bin/python duplicate_lines.py gettysburg_address.txt duplicate_lines.txt",
        TestCasePoints(15),
        {
            new TestCaseComparison( &diffLineSwapOk,
                "duplicate_lines.txt",
                "duplicate_lines.txt",
                "good_output.txt",
                1.0
            )
        }
    ),
        
    TestCase::MakeTestCase(
        "words at least 5 chars long",
        "gettysburg_address.txt",
        "/usr/bin/python extra_lines_not_duplicate.py gettysburg_address.txt extra_lines.txt",
        TestCasePoints(10),
        {
            new TestCaseComparison( &diffLineSwapOk,
                "extra_lines.txt",
                "extra_lines.txt",
                "good_output.txt",
                1.0
            )
        }
    ),
    TestCase::MakeTestCase(
        "words at least 5 chars long",
        "gettysburg_address.txt",
        "/usr/bin/python missing_lines.py gettysburg_address.txt missing_lines.txt",
        TestCasePoints(5),
        {
            new TestCaseComparison( &diffLineSwapOk,
                "missing_lines.txt",
                "missing_lines.txt",
                "good_output.txt",
                1.0
            )
        }
    ),
};

#endif
