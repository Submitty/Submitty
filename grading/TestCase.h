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
#include <map>
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
  TestCaseGrader(const std::string &f, const std::string &d) : filename(f), description(d) { points_fraction = -1; }
  std::string filename;
  std::string description;
  float points_fraction;

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
             float points_frac=-1.0)
    : TestCaseGrader(file,desc), cmp_output(cmp), expected_file(expect)  {points_fraction=points_frac;}
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
         float points_frac=-1.0)
    : TestCaseGrader(file,desc), token_grader(cmp), tokens(_tokens) {points_fraction=points_frac;}


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
		 float points_frac=-1.0)
    : TestCaseGrader(file,desc), custom_grader(custom_grader_) { my_arg_string = arg_string; points_fraction=points_frac; my_display_mode = ""; }

  float (*custom_grader)(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv,  TestCaseCustom& custom_testcase);
  virtual TestResults* doit(const std::string &prefix);
  virtual std::string display_mode() const { return my_display_mode; }

  void set_display_mode(const std::string& dm) { my_display_mode = dm; }

private:
  std::string my_arg_string;
  std::string my_display_mode;
};


// =================================================================================

static void adjust_test_case_limits(std::map<int,rlim_t> &modified_test_case_limits,
				    int rlimit_name, rlim_t value) {
  
  // first, see if this quantity already has a value
  std::map<int,rlim_t>::iterator t_itr = modified_test_case_limits.find(rlimit_name);
  
  if (t_itr == modified_test_case_limits.end()) {
    // if it does not, add it
    modified_test_case_limits.insert(std::make_pair(rlimit_name,value));
  } else {
    // otherwise set it to the max
    t_itr->second = std::max(value,t_itr->second);
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


  static TestCase MakeTestCase (nlohmann::json j) {
    std::string type = j.value("type","DEFAULT");
    //std::cout << "TYPE = " << type << std::endl;
    if (type == "FileExists") {
      return MakeFileExists(j.value("title","TITLE MISSING"),
                            j.value("filename","FILENAME MISSING"),
                            TestCasePoints(j.value("points",0)));
    } else if (type == "Compilation") {
      return MakeCompilation(j.value("title","TITLE MISSING"),
                             j.value("command","COMMAND MISSING"),
                             j.value("executable_name","EXECUTABLE FILENAME MISSING"),
                             TestCasePoints(j.value("points",0)));
    } else {
      assert (type == "DEFAULT");
      std::vector<TestCaseGrader*> graders;
      nlohmann::json::iterator itr = j.find("validation");
      assert (itr != j.end());

      for (nlohmann::json::iterator itr2 = (itr)->begin(); itr2 != (itr)->end(); itr2++) {
        std::string method = itr2->value("method","MISSING METHOD");
        TestResults* (*cmp) ( const std::string&, const std::string& );
        if      (method == "myersDiffbyLinebyChar")  cmp = &myersDiffbyLinebyChar;
        else if (method == "myersDiffbyLinebyWord")  cmp = &myersDiffbyLinebyWord;
        else if (method == "myersDiffbyLine")        cmp = &myersDiffbyLine;
        else if (method == "myersDiffbyLineNoWhite") cmp = &myersDiffbyLineNoWhite;
        else if (method == "warnIfNotEmpty")         cmp = &warnIfNotEmpty;
        else {
          exit(0);
        }
        std::string filename = itr2->value("filename","MISSING FILENAME");
        std::string description = itr2->value("description",filename);
        std::string instructor_file = itr2->value("instructor_file","");
        TestCaseGrader* x = new TestCaseComparison(cmp,filename,description,instructor_file);
        graders.push_back(x);
      }
      return MakeTestCase(j.value("title","TITLE MISSING"),
                          j.value("details","DETAILS MISSING"),
                          j.value("command","COMMAND MISSING"),
                          TestCasePoints(j.value("points",0)),
                          graders,
                          {});
    }
  }


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
           float w_frac = 0,
				   const std::map<int,rlim_t> &test_case_limits = {} ) {
    return MakeCompilation(title,
			   compilation_command,
			   std::vector<std::string>(1,executable_filename),
			   tcp, w_frac, test_case_limits);
  }


  static TestCase MakeCompilation( const std::string &title,
				   const std::string &compilation_command,
				   const std::vector<std::string> &executable_filenames,
				   const TestCasePoints &tcp,
           float w_frac = 0,
				   const std::map<int,rlim_t> &test_case_limits={}) {

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
				   const std::string &filename = "",
				   const std::map<int,rlim_t> &test_case_limits = {} ) {
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
  bool extracredit () const {
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

  const std::map<int,rlim_t> get_test_case_limits() const { return _test_case_limits; }
  
  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }
  float get_warning_frac() { return warning_frac; }
private:

  std::string _title;
  std::string _details;

  std::vector<std::string> _filenames;
  std::string _command;

  std::map<int,rlim_t> _test_case_limits;

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
