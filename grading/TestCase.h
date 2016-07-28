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


#include "json.hpp"

#ifndef __TESTCASE_H__
#define __TESTCASE_H__

#include <string>
#include <sstream>
#include <cassert>
#include <iomanip>
#include <sys/resource.h>
#include "tokenSearch.h"
#include "myersDiff.h"
#include "testResults.h"
#include "tokens.h"

const std::string drmemory_path = "/usr/local/submitty/drmemory/bin/drmemory";

// =================================================================================

class TestCasePoints {
public:
  TestCasePoints(int p=0, bool h=false, bool ec=false, bool view_test_case=true, bool view_points=true ) : points(p),hidden(h),extra_credit(ec),visible(view_test_case),view_test_points(view_points) {}
  int points;
  bool hidden;
  bool extra_credit;
  bool visible;
  bool view_test_points;
};


// =================================================================================
// =================================================================================

class TestCaseGrader {
public:
  TestCaseGrader(const std::string &f, const std::string &d) : filename(f), description(d) { deduction = -1; }
  std::string filename;
  std::string description;
  float deduction;

  virtual TestResults* doit(const std::string &prefix) = 0;

  virtual std::string getExpected() const { return ""; }
  virtual std::string display_mode() const { return ""; }
};

class TestCaseComparison : public TestCaseGrader {
public:
  TestCaseComparison(TestResults* (*cmp) ( const std::string&, const std::string& ),
		     const std::string file,
		     const std::string desc,
		     const std::string expect = "",
                     float deduct=-1.0)
    : TestCaseGrader(file,desc), cmp_output(cmp), expected_file(expect)  {deduction=deduct;}
  TestResults* (*cmp_output) ( const std::string&, const std::string& );
  std::string expected_file;
  virtual std::string getExpected() const { return expected_file; }
  virtual TestResults* doit(const std::string &prefix);
};

class TestCaseTokens : public TestCaseGrader {
public:
  TestCaseTokens(TestResults* (*cmp) ( const std::string&, const std::vector<std::string> &tokens ),
		 const std::string file,
		 const std::string desc,
		 const std::vector<std::string> &_tokens,
                 float deduct=-1.0)
    : TestCaseGrader(file,desc), token_grader(cmp), tokens(_tokens) {deduction=deduct;}


  TestResults* (*token_grader) ( const std::string&, const std::vector<std::string>& );
  std::vector<std::string> tokens;

  virtual TestResults* doit(const std::string &prefix);
};




class TestCaseCustom : public TestCaseGrader {
public:
  TestCaseCustom(float (*custom_grader_)(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv, TestCaseCustom& custom_testcase),
		 const std::string file,
		 const std::string desc,
		 const std::string arg_string,
		 float deduct=-1.0)
    : TestCaseGrader(file,desc), custom_grader(custom_grader_) { my_arg_string = arg_string; deduction=deduct; my_display_mode = ""; }

  float (*custom_grader)(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv,  TestCaseCustom& custom_testcase);
  virtual TestResults* doit(const std::string &prefix);
  virtual std::string display_mode() const { return my_display_mode; }

  void set_display_mode(const std::string& dm) { my_display_mode = dm; }

private:
  std::string my_arg_string;
  std::string my_display_mode;
};

#define _TERM_COMPARISON_DEF(T)\
if (method == #T "Comparison") {\
        bool (*cmp)(const int& a, const int& b) = NULL;\
        std::string cmpstr(itr2->value("comparison",""));\
        if (cmpstr == "eq") cmp = [](const T& a, const T& b){return a == b;};\
        else if (cmpstr == "ne") cmp = [](const T& a, const T& b){return a != b;};\
        else if (cmpstr == "gt") cmp = [](const T& a, const T& b){return a > b;};\
        else if (cmpstr == "lt") cmp = [](const T& a, const T& b){return a < b;};\
        else if (cmpstr == "ge") cmp = [](const T& a, const T& b){return a >= b;};\
        else if (cmpstr == "le") cmp = [](const T& a, const T& b){return a <= b;};\
	    T object;\
	    std::stringstream ss(itr2->value("term", "0"));\
	    ss >> object;\
		if (cmp != NULL) graders.push_back(new TestCaseTermComparison<T>(filename, description, cmp, object));\
}

template <typename T>
class TestCaseTermComparison : public TestCaseGrader {
public:
  TestCaseTermComparison(const std::string file, const std::string desc,
                         bool (*m)(const T& a, const T& b), const T& o)
    : TestCaseGrader(file, desc), method(m), object(o) {}

  virtual TestResults* doit(const std::string &prefix) {
    T contents;
    std::ifstream student_instr((prefix + "_" + filename).c_str());
    student_instr >> contents;
    student_instr.close();
    if (method(contents, object)) {
      return new TestResults(1);
    } else {
      return new TestResults(0);
    }
  }

private:
  bool (*method)(const T& a, const T& b);
  T object;
};


// =================================================================================


std::string rlimit_name_decoder(int i);

static void adjust_test_case_limits(nlohmann::json &modified_test_case_limits,
				    int rlimit_name, rlim_t value) {
  
  std::string rlimit_name_string = rlimit_name_decoder(rlimit_name);

  // first, see if this quantity already has a value
  nlohmann::json::iterator t_itr = modified_test_case_limits.find(rlimit_name_string);
  
  if (t_itr == modified_test_case_limits.end()) {
    // if it does not, add it
    modified_test_case_limits[rlimit_name_string] = value;
  } else {
    // otherwise set it to the max
    //t_itr->second = std::max(value,t_itr->second);
    if (int(value) > int(modified_test_case_limits[rlimit_name_string]))
      modified_test_case_limits[rlimit_name_string] = value;
  }
}

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


  static TestCase MakeTestCase (nlohmann::json j);


  static TestCase MakeFileExists ( const std::string &title,
				   const std::string &filename,
				   const TestCasePoints &tcp) {
    TestCase answer;
    answer._title = title;
    assert (filename != "");
    answer._filenames.push_back(filename); // = std::vector<std::string>(0,filename);
    answer._test_case_points = tcp;
    answer.FILE_EXISTS = true;
    //answer.view_file = filename;
    answer.view_file_results = false;
    return answer;
  }


  static TestCase MakeCompilation( const std::string &title,
				   const std::string &compilation_command,
				   const std::string &executable_filename, // single executable file converted into vector
				   const TestCasePoints &tcp,
                                   float w_frac, // = 0,
                                   nlohmann::json test_case_limits) { // = nlohmann::json() ) {
    return MakeCompilation(title,
			   compilation_command,
			   std::vector<std::string>(1,executable_filename),
			   tcp, w_frac, test_case_limits);
  }


  static TestCase MakeCompilation( const std::string &title,
				   const std::string &compilation_command,
				   const std::vector<std::string> &executable_filenames,
				   const TestCasePoints &tcp,
                                   float w_frac, // = 0,
                                   nlohmann::json test_case_limits) { //=nlohmann::json()) {

    TestCase answer;
    answer._title = title;
    assert (executable_filenames.size() > 0 && 
	    executable_filenames[0] != "");
    answer._filenames = executable_filenames;
    answer._command = compilation_command;
    assert (answer._command != "");
    answer.warning_frac = w_frac;

    answer.COMPILATION = true;
    answer._test_case_limits = test_case_limits;


    // compilation (g++, clang++, javac) usually requires multiple
    // threads && produces a large executable

    // Over multiple semesters of Data Structures C++ assignments, the
    // maximum number of vfork (or fork or clone) system calls needed
    // to compile a student submissions was 28.
    //
    // It seems that g++     uses approximately 2 * (# of .cpp files + 1) processes
    // It seems that clang++ uses approximately 2 +  # of .cpp files      processes

    adjust_test_case_limits(answer._test_case_limits,RLIMIT_NPROC,100*40);           // 100 threads * 40 parallel grading


    // 10 seconds was sufficient time to compile most Data Structures
    // homeworks, but some submissions required slightly more time
    adjust_test_case_limits(answer._test_case_limits,RLIMIT_CPU,60);              // 60 seconds 


    adjust_test_case_limits(answer._test_case_limits,RLIMIT_FSIZE,10*1000*1000);  // 10 MB executable

    answer._test_case_points = tcp;
    //std::cout << "COMPILATION TEST CASE POINTS " << tcp.points << std::endl;
    //std::cout << "ANSWER POINTS " << answer.points() << std::endl;

    //if (answer.points() == 0) { std::cout << "NO POINTS????" << std::endl; }
    //std::cout << "ANSWER POINTS " << answer.points() << std::endl;
    return answer;
  }


  static TestCase MakeTestCase   ( const std::string &title, const std::string &details,
				   const std::string &command,
				   const TestCasePoints &tcp,
				   std::vector<TestCaseGrader*> tcc,
				   const std::string &filename, //  = "",
                                   nlohmann::json test_case_limits) { // = nlohmann::json() ) {
    TestCase answer;
    answer._title = title;
    answer._details = details;
    answer._command = command;
    assert (answer._command != "");
    answer._test_case_points = tcp;
    assert (tcc.size() >= 1); // && tcc.size() <= 4);
    answer.test_case_grader_vec = tcc; 
    answer._filenames.push_back(filename);
    answer.view_file_results = true;
    answer._test_case_limits = test_case_limits;
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
    return _filenames[0];
  }

  std::string getView_file() const {
    //      if(view_file_results && view_file !=""){
    //return prefix()+"_"+view_file;
    if(view_file_results && _filenames[0] !=""){
      return prefix()+"_"+_filenames[0];
    }
    else{
      return _filenames[0];
    }
  }

  bool getView_file_results() const {
      return view_file_results;
  }


  std::string getFilename2() const {
    return prefix()+"_"+_filenames[0];
  }

  int numFileGraders() const {
    return test_case_grader_vec.size();
  }


  std::string raw_filename (int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i]->filename;
  }

  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }

  std::string description (int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i]->description;
  }
  int points () const {
    return _test_case_points.points;
  }
  bool hidden () const {
    return _test_case_points.hidden;
  }
  bool extra_credit () const {
    return _test_case_points.extra_credit;
  }
  bool view_test_points () const {
      return _test_case_points.view_test_points;
  }
  bool hidden_points() const { 
    return !_test_case_points.view_test_points;
  }
  bool visible () const {
    assert (_test_case_points.visible == !_test_case_points.hidden);
    return _test_case_points.visible;
  }

  /* Calls the function designated by the function pointer; if the function pointer
     is NULL, defaults to returning the result of diffLine(). */
  TestResults* do_the_grading (int j, std::string &message);

  const nlohmann::json get_test_case_limits() const { return _test_case_limits; }
  
  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }
  float get_warning_frac() { return warning_frac; }
private:

  std::string _title;
  std::string _details;

  std::vector<std::string> _filenames;
  std::string _command;

  nlohmann::json _test_case_limits;

  bool view_file_results;
  //std::string view_file;

  TestCasePoints _test_case_points;
public:
  std::vector<TestCaseGrader*> test_case_grader_vec;
private:
  bool FILE_EXISTS;
  bool COMPILATION;
  float warning_frac;
  int test_case_id;
  static int next_test_case_id;
};


std::string getAssignmentIdFromCurrentDirectory(std::string);


// FIXME: file organization should be re-structured
#include "JUnitGrader.h"


#endif
