/* FILENAME: difference.h
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *    Chris Berger
 *    Jesse Freitas
 *    Severin Ibarluzea
 *    Kiana McNellis
 *    Kienan Knight-Boehm
 *    Sam Seng
 *    Robert Berman
 *    Victor Zhu
 *    Melissa Lindquist
 *    Beverly Sihsobhon
 *    Stanley Cheung
 *    Tyler Shepard
 *    Seema Bhaskar
 *    Andrea Wong
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

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



inline void PRINT_CHANGES(std::ostream& ostr, const Change &c) {

  ostr << "MY CHANGE\n";
  ostr << "a_start " << c.a_start << std::endl;
  ostr << "b_start " << c.b_start << std::endl;

  ostr << "a_changes: ";
  for (int i = 0; i < c.a_changes.size(); i++) {
    ostr << c.a_changes[i] << " ";
  }
  ostr << std::endl;
  ostr << "b_changes: ";
  for (int i = 0; i < c.b_changes.size(); i++) {
    ostr << c.b_changes[i] << " ";
  }
  ostr << std::endl;
}



inline void INSPECT_CHANGES(std::ostream& ostr, const Change &c,
			    const std::vector<std::vector<std::string> > &a, 
			    const std::vector<std::vector<std::string> >  &b,
			    bool &only_whitespace,
			    bool extra_student_output_ok) { 
  std::cout << "NOT HANDLING THINGS IN THIS CASE... " << std::endl;
  only_whitespace = false;
}


#define VERBOSE_INSPECT_CHANGES 0
//#define VERBOSE_INSPECT_CHANGES 1

inline void INSPECT_CHANGES(std::ostream& ostr, const Change &c,
			    const std::vector<std::string> &adata, 
			    const std::vector<std::string>  &bdata,
			    bool &only_whitespace,
			    bool extra_student_output_ok) { 

#if VERBOSE_INSPECT_CHANGES
  ostr << "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\nINSPECT CHANGES\n";
  ostr << "a_start " << c.a_start << std::endl;
  ostr << "b_start " << c.b_start << std::endl;
  ostr << "a_changes: ";
  for (int i = 0; i < c.a_changes.size(); i++) {
    ostr << c.a_changes[i] << " ";
  }
  ostr << std::endl;
  ostr << "b_changes: ";
  for (int i = 0; i < c.b_changes.size(); i++) {
    ostr << c.b_changes[i] << " ";
  }
  ostr << std::endl;
#endif

  
  // if there are more lines in b (instructor) 
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
#if VERBOSE_INSPECT_CHANGES
    ostr << "a_characters["<<i<<"]: ";
#endif
    for (int j = 0; j < c.a_characters[i].size(); j++) {
      int row = c.a_changes[i];
      int col = c.a_characters[i][j];
#if VERBOSE_INSPECT_CHANGES
      ostr << "" << c.a_characters[i][j] << "='" << adata[row][col] << "' " ;
#endif
      if (adata[row][col] != ' ') only_whitespace = false;
    }
#if VERBOSE_INSPECT_CHANGES
    std::cout << std::endl;
#endif
  }
#if VERBOSE_INSPECT_CHANGES
  ostr << std::endl;
#endif

  for (int i = 0; i < c.b_characters.size(); i++) {
#if VERBOSE_INSPECT_CHANGES
    ostr << "b_characters["<<i<<"]: ";
#endif
    for (int j = 0; j < c.b_characters[i].size(); j++) {
      int row = c.b_changes[i];
      int col = c.b_characters[i][j];
#if VERBOSE_INSPECT_CHANGES
      ostr << "" << c.b_characters[i][j] << "='" << bdata[row][col] << "' " ;
#endif
      if (bdata[row][col] != ' ') only_whitespace = false;
    }
#if VERBOSE_INSPECT_CHANGES
    std::cout << std::endl;
#endif
  }
#if VERBOSE_INSPECT_CHANGES
  ostr << std::endl;
#endif

  if (only_whitespace) {
    std::cout << "ONLY WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
  } else {
    std::cout << "FILE HAS NON WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
  }
}

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
  //float grade();
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

/*
float Difference::grade() {
  //	int max =
  //			(output_length_a > output_length_b) ?
  //					output_length_a : output_length_b;

  int max = std::max(output_length_a, output_length_b);

	/ * CHANGED FROM distance to (1-distance) * /
	/ * because distance == 0 when the files are perfect, and that should be worth full credit * /

	if (max == 0) return 1;

	return (float) (1 - (distance / (float) max ));
}
*/


#endif /* defined(__differences__difference__) */
