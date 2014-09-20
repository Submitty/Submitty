/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
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

inline std::ostream& operator<< (std::ostream& ostr, const Change &c) {
  ostr << "CHANGE\n";
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
  return ostr;
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
};

/*
class Tokens: public TestResults {
public:
	Tokens();
	std::vector<int> tokens_found;
	int num_tokens;
	bool partial;
	int tokensfound;
	bool harsh;
  // FIXME: redesign necessary
  //  float tokens_grade;
  //void setGrade(float g) { my_grade = g; }
	void printJSON(std::ostream & file_out);
  //float grade();
};
*/

inline Difference::Difference() :
  TestResults(), output_length_a(0), output_length_b(0), edit_distance(0), 
  type(OtherType), extraStudentOutputOk(false) {
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
