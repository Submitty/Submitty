#ifndef __differences__difference__
#define __differences__difference__
#include <string>
#include <vector>
#include "testResults.h"

#include "json.hpp"

#define tab "    "
#define OtherType 0
#define ByLineByChar 1
#define ByWordByChar 2
#define ByLineByWord 3

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


/* METHOD: clear
 * ARGS: none
 * RETURN: void
 * PUROSE: Clear and reset data for next block to check
 */
inline void Change::clear() {
  a_start = b_start = -1;
  a_changes.clear();
  b_changes.clear();
}


class Difference: public TestResults {
public:
  Difference();
  std::vector<Change> changes;
  std::vector<int> diff_a; //student
  std::vector<int> diff_b; //expected
  void printJSON(std::ostream & file_out);
  int output_length_a;
  int output_length_b;
  int edit_distance;
  int type;
  bool extraStudentOutputOk;
  bool only_whitespace_changes;

  int line_added;
  int line_deleted;
  int total_line;
  int char_added;
  int char_deleted;
  int total_char;

  std::string message;

  void PrepareGrade(const nlohmann::json& j);
};


inline Difference::Difference() :
  TestResults(), output_length_a(0), output_length_b(0), edit_distance(0), 
  type(OtherType), extraStudentOutputOk(false), only_whitespace_changes(false) {

  line_added = -1;
  line_deleted = -1; 
  total_line = -1;
  char_added = -1;
  char_deleted = -1; 
  total_char = -1;
}




#endif /* defined(__differences__difference__) */
