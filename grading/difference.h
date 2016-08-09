#ifndef __differences__difference__
#define __differences__difference__
#include <string>
#include <vector>
#include "testResults.h"

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
inline void INSPECT_CHANGES(std::ostream& ostr, const Change &c,
			    const std::vector<std::vector<std::string> > &a, 
			    const std::vector<std::vector<std::string> >  &b,
			    bool &only_whitespace,
			    bool extra_student_output_ok) { 
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
inline void INSPECT_CHANGES(std::ostream& ostr, const Change &c,
			    const std::vector<std::string> &adata, 
			    const std::vector<std::string>  &bdata,
			    bool &only_whitespace,
			    bool extra_student_output_ok) { 

  // if there are more lines in b (expected) 
  if (c.a_changes.size() < c.b_changes.size()) only_whitespace = false;

  // if there are more lines in a (student), that might be ok...
  if (c.a_changes.size() != c.b_changes.size()) {
    // but if extra student output is not ok
    if (!extra_student_output_ok
	||
	c.b_changes.size() != 0)
      only_whitespace = false;
  }

  for (int i = 0; i < c.a_characters.size(); i++) {
    for (int j = 0; j < c.a_characters[i].size(); j++) {
      int row = c.a_changes[i];
      int col = c.a_characters[i][j];
      if (adata[row][col] != ' ') only_whitespace = false;
    }
  }

  for (int i = 0; i < c.b_characters.size(); i++) {
    for (int j = 0; j < c.b_characters[i].size(); j++) {
      int row = c.b_changes[i];
      int col = c.b_characters[i][j];
      if (bdata[row][col] != ' ') only_whitespace = false;
    }
  }

  if (only_whitespace) {
    std::cout << "ONLY WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
  } else {
    std::cout << "FILE HAS NON WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
  }
}

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

  void PrepareGrade();
};


inline Difference::Difference() :
  TestResults(), output_length_a(0), output_length_b(0), edit_distance(0), 
  type(OtherType), extraStudentOutputOk(false), only_whitespace_changes(false) {
}




#endif /* defined(__differences__difference__) */
