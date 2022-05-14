#ifndef __CHANGE_H__
#define __CHANGE_H__

#include <string>
#include <vector>
#include "testResults.h"

#include <nlohmann/json.hpp>

/* DESCRIPTION: Contains the differences in a block for a student's ouput
 * and the expected output */
class Change {
public:

  // Starting changeblock line for input (student)
  int a_start;

  // Same for (expected)
  int b_start;

  // Vector of lines in changeblock that contain discrepancies (student)
  std::vector<int> a_changes;

  // Same for (expected)
  std::vector<int> b_changes;

  // Structure for changed character/word indices (student)
  std::vector<std::vector<int> > a_characters;

  // Same for (expected)
  std::vector<std::vector<int> > b_characters;

  void clear();
};

/* METHOD: INSPECT_CHANGES
 * ARGS: ostream, c - contains a block of text with discrepancies,
 * a - strings by line for student output, b - strings by line for expected outpu,
 * only_whitespace - check for if only whitespace, extra_Student_output_ok - check for if
 * student output is good
 * RETURN: void
 * PURPOSE: Inspect the changes for student output and expected output
 */
inline void INSPECT_IMPROVE_CHANGES(std::ostream& ostr,
          Change &c,
          const std::vector<std::vector<std::string> > &a,
          const std::vector<std::vector<std::string> >  &b,
          const nlohmann::json& j,
          bool &only_whitespace,
          bool extra_student_output_ok,
          int &line_added,
          int &line_deleted,
          int &char_added,
          int &char_deleted) {
  std::cout << "NOT HANDLING THINGS IN THIS CASE... " << std::endl;
  only_whitespace = false;
}


/* METHOD: INSPECT_CHANGES
 * ARGS: ostream, c - contains a block of text with discrepancies,
 * adata - strings by line for student output, bdata - strings by line for expected output,
 * only_whitespace - check for if only whitespace, extra_Student_output_ok - check for if
 * student output is good
 * RETURN: void
 * PURPOSE: Used for logging
 */
void INSPECT_IMPROVE_CHANGES(std::ostream& ostr, Change &c,
                     const std::vector<std::string> &adata,
                     const std::vector<std::string>  &bdata,
                     const nlohmann::json& j,
                     bool &only_whitespace,
                     bool extra_student_output_ok,
                     int &line_added,
                     int &line_deleted,
                     int &char_added,
                     int &char_deleted);

#endif
