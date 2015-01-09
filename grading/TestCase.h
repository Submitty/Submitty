/* FILENAME: TestCase.h
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   Chris Berger
 *   Jesse Freitas
 *   Severin Ibarluzea
 *   Kiana McNellis
 *   Kienan Knight-Boehm
 *   Sam Seng
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 *
*/


#ifndef __TESTCASE_H__
#define __TESTCASE_H__

#include <string>
#include <sstream>
#include <cassert>
#include "modules/modules.h"

extern const int max_clocktime;

// =================================================================================

class TestCasePoints {
public:
  TestCasePoints(int p=0, bool h=false, bool ec=false) : points(p),hidden(h),extra_credit(ec) {}
  int points;
  bool hidden;
  bool extra_credit;
};


// =================================================================================
// =================================================================================

class TestCaseGrader {
public:
  TestCaseGrader(const std::string &f, const std::string &d) : filename(f), description(d) { points_fraction = -1; }
  std::string filename;
  std::string description;
  float points_fraction;

  virtual TestResults* doit(const std::string &prefix) = 0;

  virtual std::string getExpected() { return ""; }
};

class TestCaseComparison : public TestCaseGrader {
public:
  TestCaseComparison(TestResults* (*cmp) ( const std::string&, const std::string& ),
		     const std::string file,
		     const std::string desc,
		     const std::string expect = "",
             float points_frac=-1.0)
    : TestCaseGrader(file,desc), cmp_output(cmp), expected_file(expect)  {points_fraction=points_frac;}
  TestResults* (*cmp_output) ( const std::string&, const std::string& );
  std::string expected_file;
  virtual std::string getExpected() { return expected_file; }
  virtual TestResults* doit(const std::string &prefix);
};

class TestCaseTokens : public TestCaseGrader {
public:
  TestCaseTokens(TestResults* (*cmp) ( const std::string&, const std::vector<std::string> &tokens ),
		 const std::string file,
		 const std::string desc,
		 const std::vector<std::string> &_tokens,
         float points_frac=-1.0)
    : TestCaseGrader(file,desc), token_grader(cmp), tokens(_tokens) {points_fraction=points_frac;}
  TestResults* (*token_grader) ( const std::string&, const std::vector<std::string>& );
  std::vector<std::string> tokens;

  virtual TestResults* doit(const std::string &prefix);
};


class TestCaseCustom : public TestCaseGrader {
public:
  TestCaseCustom(float (*custom_grader_)(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv),
		 const std::string file,
		 const std::string desc,
		 const std::string arg_string,
		 float points_frac=-1.0)
    : TestCaseGrader(file,desc), custom_grader(custom_grader_) {my_arg_string = arg_string; points_fraction=points_frac;}

  float (*custom_grader)(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv);

  //TestResults* (*token_grader) ( const std::string&, const std::vector<std::string>& );
  //std::vector<std::string> tokens;

  virtual TestResults* doit(const std::string &prefix);
private:
  std::string my_arg_string;
};




// =================================================================================
// =================================================================================

/* TestCase is used to define individual test cases for homeworks. These
 will be checked by the validator and graded by the grader. */
class TestCase {


private:
  // This constructor only used by the static Make functions
  TestCase () {
    test_case_id = next_test_case_id;
    next_test_case_id++;
    FILE_EXISTS = false;
    COMPILATION = false;
  }

public:


  static TestCase MakeFileExists ( const std::string &title,
				   const std::string &filename,
				   const TestCasePoints &tcp) {

    TestCase answer;
    answer._title = title;
    answer._filename = filename;
    assert (answer._filename != "");
    answer._test_case_points = tcp;
    answer.FILE_EXISTS = true;
    return answer;
  }

  static TestCase MakeCompilation( const std::string &title,
				   const std::string &compilation_command,
				   const std::string &executable_filename,
				   const TestCasePoints &tcp) {

    TestCase answer;
    answer._title = title;
    answer._filename = executable_filename;
    assert (answer._filename != "");
    answer._command = compilation_command;
    assert (answer._command != "");
    answer._test_case_points = tcp;
    answer.COMPILATION = true;

    return answer;
  }

  static TestCase MakeTestCase   ( const std::string &title, const std::string &details,
				   const std::string &command,
				   const TestCasePoints &tcp,
				   TestCaseGrader *tcc0,
				   TestCaseGrader *tcc1=NULL,
				   TestCaseGrader *tcc2=NULL ) {

    TestCase answer;
    answer._title = title;
    answer._details = details;
    answer._command = command;
    assert (answer._command != "");
    answer._test_case_points = tcp;
    answer.test_case_grader[0] = tcc0;
    answer.test_case_grader[1] = tcc1;
    answer.test_case_grader[2] = tcc2;

    return answer;
  }



  // Accessors
  std::string title() const {
    std::stringstream ss;
    ss << "Test " << test_case_id << " " << _title;
    return ss.str();
    //return _title;
  }

		std::string just_title() const {
		  return _title;
		}

		std::string details () const {
			return _details;
		}
		std::string command () const {
			return _command;
		}

  // FIXME: filename/rawfilename is messy/confusing, sort this out

		std::string filename (int i) const {
		  return prefix()+"_"+raw_filename(i);
		}


  std::string getFilename() const {
    return _filename;
  }

  std::string getFilename2() const {
    return prefix()+"_"+_filename;
  }

  int numFileGraders() const {
    int answer = 0;
    if (test_case_grader[0] == NULL) return answer;
    answer++;
    if (test_case_grader[1] == NULL) return answer;
    answer++;
    if (test_case_grader[2] == NULL) return answer;
    answer++;
    return answer;
  }


  std::string raw_filename (int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader[i]->filename;
  }

  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }

  std::string description (int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader[i]->description;
  }
  /*
  std::string getexpected (int i) const {
    //std::cout << "EXPECTED " << i << numFileGraders() << std::endl;
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader[i]->getExpected();
  }
  */

  int points () const {
    return _test_case_points.points;
  }
  bool hidden () const {
    return _test_case_points.hidden;
  }
  bool extracredit () const {
    return _test_case_points.extra_credit;
  }

  /* Calls the function designated by the function pointer; if the function pointer
     is NULL, defaults to returning the result of diffLine(). */
  TestResults* do_the_grading (int j, std::string &message);

  //int seconds_to_run() { return 5; }
  int seconds_to_run() { return max_clocktime; }

  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }

private:

  std::string _title;
  std::string _details;

  std::string _filename;
  std::string _command;


  TestCasePoints _test_case_points;
public:
  TestCaseGrader* test_case_grader[3];
private:
  bool FILE_EXISTS;
  bool COMPILATION;

  int test_case_id;
  static int next_test_case_id;
};


std::string getAssignmentIdFromCurrentDirectory(std::string);



#endif
