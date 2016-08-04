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
  TestCase (const nlohmann::json &input);


  // -------------------------------
  // ACCESSORS

  int getID() const { return test_case_id; }
  std::string getTitle() const;
  std::string getDetails () const { return _json.value("details",""); }

  int getPoints() const { return _json.value("points", 0); }
  bool getHidden() const { return _json.value("hidden", false); }
  bool getExtraCredit() const { return _json.value("extra_credit",false); }

  bool isFileExistsTest() const { return _json.value("type","DEFAULT") == "FileExists"; }
  bool isCompilationTest() const { return _json.value("type","DEFAULT") == "Compilation"; }
  bool isDefaultTest() const { return _json.value("type","DEFAULT") == "DEFAULT"; }

  float get_warning_frac() const { assert (isCompilationTest()); return _json.value("warning_deduction",0.0); }

  std::vector<std::string> getCommands() const {
    std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
    assert (commands.size() > 0);
    return commands;
  }

  std::vector<std::vector<std::string>> getFilenames() const;
  std::string getMyFilename(int v, int i) const {
    const std::vector<std::vector<std::string> > filenames = getFilenames();
    assert (filenames.size() > 0);
    assert (filenames[v].size() > 0);
    return filenames[v][i];
  }
  std::string getMyPrefixFilename (int v, int i) const { return getPrefix()+"_"+getMyFilename(v,i); }

  int numFileGraders() const { return test_case_grader_vec.size(); }
  const nlohmann::json& getGrader(int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i];
  }

  TestResults* do_the_grading (int j);

  const nlohmann::json get_test_case_limits() const;

  // PRIVATE HELPER FUNCTIONS

  std::string getPrefix() const;

private:

  TestResults* dispatch(const nlohmann::json& grader) const;
  TestResults* custom_dispatch(const nlohmann::json& grader) const;

  nlohmann::json _json;
  std::vector<nlohmann::json> test_case_grader_vec;

  // -------------------------------
  // REPRESENTATION
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
//#include "JUnitGrader.h"


#endif
