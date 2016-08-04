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


  // -------------------------------
  // COMMANDS
  std::vector<std::string> getCommands() const {
    std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
    assert (commands.size() > 0);
    return commands;
  }


  // -------------------------------
  // FILENAMES
  std::string getPrefix() const;
  std::string getMyFilename(int v, int i) const {
    const std::vector<std::vector<std::string> > filenames = getFilenames();
    assert (filenames.size() > 0);
    assert (filenames[v].size() > 0);
    return filenames[v][i];
  }
  std::string getMyPrefixFilename (int v, int i) const { return getPrefix()+"_"+getMyFilename(v,i); }
  std::vector<std::vector<std::string>> getFilenames() const;


  // -------------------------------
  // GRADING & GRADERS
  TestResults* do_the_grading (int j);
  int numFileGraders() const { return test_case_grader_vec.size(); }
  const nlohmann::json& getGrader(int i) const {
    assert (i >= 0 && i < numFileGraders());
    return test_case_grader_vec[i];
  }
  const nlohmann::json get_test_case_limits() const;

private:

  // -------------------------------
  // PRIVATE HELPER FUNCTIONS
  TestResults* dispatch(const nlohmann::json& grader) const;
  TestResults* custom_dispatch(const nlohmann::json& grader) const;

  // -------------------------------
  // REPRESENTATION
  int test_case_id;
  static int next_test_case_id;

  nlohmann::json _json;
  std::vector<nlohmann::json> test_case_grader_vec;
};




// =================================================================================
// =================================================================================

// NON MEMBER  HELPER FUNCTIONS

void adjust_test_case_limits(nlohmann::json &modified_test_case_limits, int rlimit_name, rlim_t value);

std::string getAssignmentIdFromCurrentDirectory(std::string);

bool getFileContents(const std::string &filename, std::string &file_contents);
bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, std::vector<std::string> &messages);
bool openInstructorFile(const TestCase &tc, const nlohmann::json &j, std::string &instructor_file_contents, std::vector<std::string> &messages);


#endif
