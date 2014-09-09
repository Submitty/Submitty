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

#define tab "    "
#define OtherType 0
#define ByLineByChar 1
#define ByWordByChar 2
#define ByLineByWord 3

class TestResults {
public:

  TestResults(int g=-1, const std::string &m="") { my_grade = g; message = m; }
        virtual ~TestResults() {}
	int distance;

  virtual void printJSON(std::ostream & file_out); // =0;
  //  virtual float grade() { return my_grade; } //=0;
  float getGrade() { assert (my_grade >= 0); return my_grade; } //=0;

  void setGrade(float g) { assert (g >= 0); my_grade = g; }

  std::string get_message() { return message; }
  void setMessage(const std::string &m) { message=m; }
protected:
  std::string message;
  float my_grade;
};



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

void Change::clear() {
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

/*
TestResults::TestResults() :
		distance(0) {
}
*/

Difference::Difference() :
  TestResults(), output_length_a(0), output_length_b(0), edit_distance(0), 
  type(OtherType), extraStudentOutputOk(false) {
}

Tokens::Tokens() :
  TestResults(1,""), num_tokens(0), tokensfound(0), partial(true), harsh(false) {
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

/*
float Tokens::grade() {
  return my_grade;
  // FIXME
  / *
	for (unsigned int i = 0; i < tokens_found.size(); i++) {
		if (tokens_found[i] != -1)
			tokensfound++;
	}
	if (partial)
		return (float) tokensfound / (float) num_tokens;
	else if (tokensfound == num_tokens || (!harsh && tokensfound != 0)) {
		return 1;
	}
	return 0;
  * /
}
*/

void Difference::printJSON(std::ostream & file_out) {
	std::string diff1_name;
	std::string diff2_name;
	file_out << "{" << std::endl << "\"differences\":[" << std::endl << tab;
	switch (type) {
	// ByLineByChar;
	// ByWordByChar;
	// VectorVectorStringType;
	// ByLineByWord;
	// VectorOtherType;

	case ByLineByChar:
		diff1_name = "line";
		diff2_name = "char";
		break;

	case ByWordByChar:
		diff1_name = "word";
		diff2_name = "char";
		break;

	case ByLineByWord:
		diff1_name = "line";
		diff2_name = "word";
		break;

	default:
		diff1_name = "line";
		diff2_name = "char";
		break;
	}

	for (unsigned int a = 0; a < changes.size(); a++) {
		if (a > 0) {
			file_out << ", ";
		}
		file_out << "{" << std::endl;

		file_out << tab<<tab<<"\"student\":"<<std::endl;

		file_out << tab<<tab<<"{"<<std::endl;

		file_out << tab<<tab<<tab<<"\"start\": "
		<<changes[a].a_start;
		if (changes[a].a_changes.size() > 0) {
			file_out << "," << std::endl;
			file_out << tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
			<<tab<<tab<<tab<<tab;
			for (unsigned int b = 0; b < changes[a].a_changes.size(); b++) {
				if (b > 0) {
					file_out << ", ";
				}
				file_out << "{" << std::endl;
				file_out << tab<<tab<<tab<<tab<<tab
				<<"\""+diff1_name+"_number\": "
				<<changes[a].a_changes[b];
				//insert code to display word changes here
				if (changes[a].a_characters.size() >= b
						&& changes[a].a_characters.size() > 0) {
					if (changes[a].a_characters[b].size() > 0) {
						file_out << ", " << std::endl;
						file_out << tab<<tab<<tab<<tab<<tab
						<<"\""+diff2_name+"_number\":[ ";
					}
					else {
						file_out<<std::endl;
					}

					for (unsigned int c=0; c< changes[a].a_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<changes[a].a_characters[b][c];
                    }
                    if (changes[a].a_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
						else {
							file_out<<std::endl;
						}
				file_out << tab<<tab<<tab<<tab<<"}";
			}
			file_out << std::endl << tab<<tab<<tab<<"]"<<std::endl;
		}
		else {
			file_out<<std::endl;
		}

		file_out << tab<<tab<<"},"<<std::endl;
		file_out << tab<<tab<<"\"instructor\":"<<std::endl   // want to replace instructor with expected
		<<tab<<tab<<"{"<<std::endl;

		file_out << tab<<tab<<tab<<"\"start\":"
		<<changes[a].b_start;
		if (changes[a].b_changes.size() > 0) {
			file_out << "," << std::endl;
			file_out << tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
			<<tab<<tab<<tab<<tab;
			for (unsigned int b = 0; b < changes[a].b_changes.size(); b++) {
				if (b > 0) {
					file_out << ", ";
				}
				file_out << "{" << std::endl;
				file_out << tab<<tab<<tab<<tab<<tab
				<<"\""+diff1_name+"_number\": " <<changes[a].b_changes[b];
				//insert code to display word changes here
				if (changes[a].b_characters.size() >= b
						&& changes[a].b_characters.size() > 0) {
					if (changes[a].b_characters[b].size() > 0) {
						file_out << ", " << std::endl;
						file_out << tab<<tab<<tab<<tab<<tab
						<<"\""+diff2_name+"_number\":[ ";
					}
					else {
						file_out<<std::endl;
					}
					for (unsigned int c=0; c< changes[a].b_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<changes[a].b_characters[b][c];
                    }
                    if (changes[a].b_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
						else {
							file_out<<std::endl;

						}
				file_out << tab<<tab<<tab<<tab<<"}";

			}
			file_out << std::endl << tab<<tab<<tab<<"]"<<std::endl;
		}
		else {
			file_out<<std::endl;
		}
		file_out << tab<<tab<<"}"<<std::endl;
		file_out << tab<<"}";
	}
	file_out << std::endl << "]" << std::endl;
	file_out << "}" << std::endl;

	return;
}

void Tokens::printJSON(std::ostream & file_out) {
	std::string partial_str = (partial) ? "true" : "false";

	file_out << "{\n\t\"tokens\": " << num_tokens << "," << std::endl;
	file_out << "\t\"found\": [";
	for (unsigned int i = 0; i < tokens_found.size(); i++) {
		file_out << tokens_found[i];
		if (i != tokens_found.size() - 1) {
			file_out << ", ";
		} else {
			file_out << " ]," << std::endl;
		}
	}
	file_out << "\t\"num_found\": " << tokensfound << "," << std::endl;
	file_out << "\t\"partial\": " << partial_str << "," << std::endl;
	file_out << "}" << std::endl;
	return;
}


void TestResults::printJSON(std::ostream & file_out) {

	file_out << "{" << std::endl;
	file_out << "}" << std::endl;
	return;
}


#endif /* defined(__differences__difference__) */
