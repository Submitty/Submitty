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

std::vector<std::string> stringOrArrayOfStrings(nlohmann::json j, const std::string what);


// =================================================================================
// =================================================================================

/* TestCase is used to define individual test cases for homeworks. These
 will be checked by the validator and graded by the grader. */
class TestCase {

public:

  // CONSTRUCTOR
  TestCase (nlohmann::json j);


  // ACCESSOR

  int getID() const { return test_case_id; }

  std::string getTitle() const {
    nlohmann::json::const_iterator itr = _json.find("title");
    if (itr == _json.end()) {
      std::cerr << "ERROR! MISSING TITLE" << std::endl;
    }
    assert (itr->is_string());
    return (*itr);
  }

  std::string getDetails () const {
    return _json.value("details","");
  }

  std::string command () const {
    assert (_commands.size() > 0);
    return _commands[0];
  }

  std::string getPrefixFilename (int i) const {
    return prefix()+"_"+raw_filename(i);
  }

  std::string getFilename() const {
    assert (_filenames.size() > 0);
    return _filenames[0];
  }

  /*
  std::string getView_file() const {
    assert (_filenames.size() > 0);
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
  */

  std::string getFilename2() const {
    assert (_filenames.size() > 0);
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
    return test_case_grader_vec[i].value("description", raw_filename(i)); 
  }
  int getPoints() const {
    return _json.value("points", 0); 
  }
  bool getHidden() const {
    return _json.value("hidden", false); 
  }
  bool getExtraCredit() const {
    return _json.value("extra_credit",false); 
  }

  TestResults* do_the_grading (int j);

  const nlohmann::json get_test_case_limits() const { return _test_case_limits; }
  
  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }
  float get_warning_frac() { return warning_frac; }
private:

  std::vector<std::string> _filenames;
  std::vector<std::string> _commands;

  nlohmann::json _test_case_limits;

  //bool view_file_results;

public:
  std::vector<nlohmann::json> test_case_grader_vec;

  nlohmann::json _json;

private:
  
  bool FILE_EXISTS;
  bool COMPILATION;
  float warning_frac;
  int test_case_id;
  static int next_test_case_id;
};


// helper functions
void adjust_test_case_limits(nlohmann::json &modified_test_case_limits, int rlimit_name, rlim_t value);


// =================================================================================

std::string getAssignmentIdFromCurrentDirectory(std::string);




bool getFileContents(const std::string &filename, std::string &file_contents);
bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, std::string &message);
bool openInstructorFile(const TestCase &tc, const nlohmann::json &j, std::string &instructor_file_contents, std::string &message);


// FIXME: file organization should be re-structured
#include "JUnitGrader.h"


#endif
