/* FILENAME: diffNaive.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * A naive comparison between student and expected output. Since this does a
 * per character comparision, insertions or deletions will cause a shift in the
 * indices being compared, so errors in the beginning will propagate and cause
 * the entire output to be wrong. While useful for quick small output, not
 * recommended for large bodies of text unless expected desires to be strict
 * on formatting of expected output.
 */

#ifndef __DIFFNAIVE__
#define __DIFFNAIVE__

#include <stdlib.h>
#include <string>
#include <sstream>
#include <algorithm>
#include "STRutil.h"
#include "difference.h"

/* METHOD: diffNaive
 * ARGS: student: the student generated output file contents as a string
 * expected: the instructor expected output file contents as a string
 * RETURN: Change: The difference of the two strings
 * PURPOSE: diffNaive is used as a helper to the diffLine function.
 * It does a per character comparison including white space and new lines. The
 * strings are not changed in this comparison. Runs in linear time with respect
 * to the longer string.
 */
Change diffNaive(const std::string& student, const std::string& expected, const bool ignore_whitespace = false) {
    Change differences;
    differences.a_start = 0;
    differences.b_start = 0;

    // Find corresponding changes in the two strings
    unsigned int i = 0;
    unsigned int j = 0;
    while (i != student.size() && j != expected.size()) {
        // If whitespace in i, increment and continue
        if (ignore_whitespace && student[i] == ' ') {
            i++;
            continue;
        }
        // If whitespace in j, increment and continue
        if (ignore_whitespace && expected[j] == ' ') {
            j++;
            continue;
        }

        // Check for difference
        if (student[i] != expected[j]) {
            differences.a_changes.push_back(i);
            differences.b_changes.push_back(j);
        }

        i++;
        j++;
    }


    // Add the rest of the changes for the longer string
    while (i < student.length()) {
        // Don't add a difference for whitespace if we're ignoring it
        if (!(ignore_whitespace && student[i] == ' '))
            differences.a_changes.push_back(i);
        i++;
    }
    while (j < expected.length()) {
        // Don't add a difference for whitespace if we're ignoring it
        if (!(ignore_whitespace && expected[j] == ' '))
            differences.b_changes.push_back(j);
        j++;
    }

    return differences;
}

/* METHOD: diffNoSpace
 * ARGS: student: the student generated output file contents as a string
 * expected: the instructor expected output file contents as a string
 * RETURN: Change: The difference of the two strings
 * PURPOSE: diffNoSpace is a helper function to the diffLineNoSpace function.
 * diffNoSpace does a per character comparison not including white space but
 * including new lines. The strings are not changed in this comparison. Runs
 * in linear time with respect to the longer string.
 */
Change diffNoSpace(const std::string& student, const std::string& expected) {
    return diffNaive(student, expected, true);
}

/* METHOD: diffLine
 * ARGS: student: the student generated output file contents as a string
 * expected: the instructor expected output file contents as a string
 * RETURN: Difference: The difference of the two files
 * PURPOSE: diffLine does a per character comparison including white space and
 * including new lines. Comparison is done per line and returns a Difference
 * object that indicates the indicies of characters the student string was off
 * by. The strings are not changed in this comparison. Runs in linear time
 * with respect to the longer string.
 */
TestResults* diffLine (const std::string& _student, const std::string& _expected, const bool ignore_whitespace = false) {
    Difference* diffs = new Difference();
    diffs->type = 1;
    diffs->output_length_a = 0;
    diffs->output_length_b = 0;

    Change file;
    file.a_start = file.b_start = 0;

    std::stringstream student (_student);
    std::stringstream expected(_expected);
    std::string s_line;
    std::string i_line;

    unsigned int i = 0;
    bool i_eof = false;
    bool s_eof = false;
    while (!i_eof || !s_eof) {
        std::getline(student,  s_line);
        std::getline(expected, i_line);

        if (!i_eof)
            diffs->output_length_b++;
        if (!s_eof)
            diffs->output_length_a++;

        if (!student) {
            s_eof = true;
            s_line = "";
        }
        if (!expected) {
            i_eof = true;
            i_line = "";
        }
        Change changes = diffNaive(s_line, i_line, ignore_whitespace);
        if (changes.a_changes.size() || changes.b_changes.size()) {
            if (!s_eof)
                file.a_changes.push_back(i);
            if (!i_eof)
                file.b_changes.push_back(i);
            file.a_characters.push_back(changes.a_changes);
            file.b_characters.push_back(changes.b_changes);
        }
        i++;
    }
    diffs->setDistance((int)std::max(file.a_changes.size(), file.b_changes.size()));

    diffs->changes.push_back(file);
    return diffs;
}

/* METHOD: diffLineNoSpace
 * ARGS: student: the student generated output file contents as a string
 * expected: the instructor expected output file contents as a string
 * RETURN: Difference: The difference of the two strings
 * PURPOSE: diffLineNoSpace does a per character comparison for each line in the respective
 * strings. Comparison is done per line and returns a Difference object
 * that indicates the indicies of characters the student string was off by. The
 * strings are not changed in this comparison. Runs in linear time with respect to
 * the longer string.
 */
TestResults* diffLineNoSpace (const std::string& _student, const std::string& _expected) {
    return diffLine(_student, _expected, true);
}

#endif //__DIFFNAIVE__
