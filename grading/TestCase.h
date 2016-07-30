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
#include "testResults.h"

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


inline std::vector<std::string> stringOrArrayOfStrings(nlohmann::json j, const std::string what) {
  std::vector<std::string> answer;
  nlohmann::json::const_iterator itr = j.find(what);
  if (itr == j.end())
    return answer;
  if (itr->is_string()) {
    answer.push_back(*itr);    
  } else {
    assert (itr->is_array());
    nlohmann::json::const_iterator itr2 = itr->begin();
    while (itr2 != itr->end()) {
      assert (itr2->is_string());
      answer.push_back(*itr2);
      itr2++;
    }
  }
  return answer;
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
				   const std::vector<std::string> &compilation_commands,
				   const std::string &executable_filename, // single executable file converted into vector
				   const TestCasePoints &tcp,
                                   float w_frac, // = 0,
                                   nlohmann::json test_case_limits) { // = nlohmann::json() ) {
    return MakeCompilation(title,
			   compilation_commands,
			   std::vector<std::string>(1,executable_filename),
			   tcp, w_frac, test_case_limits);
  }


  static TestCase MakeCompilation( const std::string &title,
				   const std::vector<std::string> &compilation_commands,
				   const std::vector<std::string> &executable_filenames,
				   const TestCasePoints &tcp,
                                   float w_frac, // = 0,
                                   nlohmann::json test_case_limits) { //=nlohmann::json()) {

    TestCase answer;
    answer._title = title;
    assert (executable_filenames.size() > 0 && 
	    executable_filenames[0] != "");
    answer._filenames = executable_filenames;
    assert (compilation_commands.size() > 0);
    answer._commands = compilation_commands;
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
				   const std::vector<std::string> &commands,
				   const TestCasePoints &tcp,
				   //std::vector<TestCaseGrader*> tcc,
				   std::vector<nlohmann::json> graders,
				   const std::string &filename, //  = "",
                                   nlohmann::json test_case_limits) { // = nlohmann::json() ) {
    TestCase answer;
    answer._title = title;
    answer._details = details;
    assert (commands.size() > 0);
    answer._commands = commands;
    answer._test_case_points = tcp;
    assert (graders.size() >= 1); // && tcc.size() <= 4);
    answer.test_case_grader_vec = graders;
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
                  assert (_commands.size() > 0);
                  return _commands[0];
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
    std::vector<std::string> files = stringOrArrayOfStrings(test_case_grader_vec[i],"filename");
    assert (files.size() > 0);
    return files[0];
  }

  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }

  std::string description (int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i].value("description", raw_filename(i)); //"MISSING DESCRIPTION");
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
  TestResults* do_the_grading (int j);

  const nlohmann::json get_test_case_limits() const { return _test_case_limits; }
  
  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }
  float get_warning_frac() { return warning_frac; }
private:

  std::string _title;
  std::string _details;

  std::vector<std::string> _filenames;
  std::vector<std::string> _commands;

  nlohmann::json _test_case_limits;

  bool view_file_results;
  //std::string view_file;

  TestCasePoints _test_case_points;
public:
  std::vector<nlohmann::json> test_case_grader_vec;
private:
  bool FILE_EXISTS;
  bool COMPILATION;
  float warning_frac;
  int test_case_id;
  static int next_test_case_id;
};


std::string getAssignmentIdFromCurrentDirectory(std::string);




bool getFileContents(const std::string &filename, std::string &file_contents);
bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, std::string &message);
bool openInstructorFile(const TestCase &tc, const nlohmann::json &j, std::string &instructor_file_contents, std::string &message);


// FIXME: file organization should be re-structured
#include "JUnitGrader.h"


#endif
