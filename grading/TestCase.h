#ifndef __TESTCASE_H__
#define __TESTCASE_H__

#include <string>
#include <sstream>
#include <cassert>
#include <iomanip>
#include <sys/resource.h>
#include "json.hpp"

#include "testResults.h"


std::vector<std::string> stringOrArrayOfStrings(nlohmann::json j, const std::string what);

// =================================================================================
// =================================================================================

class TestCase {

public:

  // -------------------------------
  // CONSTRUCTOR
  TestCase (const nlohmann::json &j);


  // -------------------------------
  // ACCESSOR
  int getID() const { return test_case_id; }

  std::string getTitle() const;


  std::string getDetails () const {
    return _json.value("details","");
  }

  std::vector<std::string> getCommands() const {
    std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
    assert (commands.size() > 0);
    return commands;
  }

  std::vector<std::vector<std::string>> getFilenames() const {
    std::cout << "getfilenames" << std::endl;
    std::vector<std::vector<std::string>> filenames;
    if (isCompilationTest()) {
      std::cout << "compilation" << std::endl;
      filenames.push_back(stringOrArrayOfStrings(_json,"executable_name"));
      assert (filenames.size() > 0);
    } else if (isFileExistsTest()) {
      std::cout << "file exists" << std::endl;
      filenames.push_back(stringOrArrayOfStrings(_json,"filename"));
      assert (filenames.size() > 0);
    } else {
      std::cout << "regular" << std::endl;
      assert (_json.find("filename") == _json.end());
      for (int v = 0; v < test_case_grader_vec.size(); v++) {
        filenames.push_back(stringOrArrayOfStrings(test_case_grader_vec[v],"filename"));
        assert (filenames[v].size() > 0);
      }
    }
    return filenames;
  }

  std::string getMyFilename(int v, int i) const {
    const std::vector<std::vector<std::string> > filenames = getFilenames();
    assert (filenames.size() > 0);
    assert (filenames[v].size() > 0);
    return filenames[v][i];
  }

  std::string getMyPrefixFilename (int v, int i) const {
    return prefix()+"_"+getMyFilename(v,i);
  }



  int numFileGraders() const {
    return test_case_grader_vec.size();
  }


  const nlohmann::json& getGrader(int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i];
  }



  std::string getFilenameDescription (int v) const {
    assert (v >= 0 && v < numFileGraders());
    return test_case_grader_vec[v].value("description", getMyFilename(v,0));
  }

  int getPoints() const { return _json.value("points", 0); }
  bool getHidden() const { return _json.value("hidden", false); }
  bool getExtraCredit() const { return _json.value("extra_credit",false); }
  bool isFileExistsTest() const { return _json.value("type","DEFAULT") == "FileExists"; }
  bool isCompilationTest() const { return _json.value("type","DEFAULT") == "Compilation"; }

  float get_warning_frac() { assert (isCompilationTest()); return _json.value("warning_deduction",0.0); }

  TestResults* do_the_grading (int j);

  const nlohmann::json get_test_case_limits() const { return _test_case_limits; }
  



  // PRIVATE HELPER FUNCTIONS

  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }

public:




private:

  nlohmann::json _json;

  nlohmann::json _test_case_limits;
  std::vector<nlohmann::json> test_case_grader_vec;

  // REPRESENTATION
  //bool FILE_EXISTS;
  //bool COMPILATION;
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
