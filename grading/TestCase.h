#ifndef __TESTCASE_H__
#define __TESTCASE_H__

#include <string>
#include <sstream>
#include <cassert>
#include <iomanip>
#include <sys/resource.h>
#include "json.hpp"

#include "testResults.h"

// ========================================================================================

#define MYERS_DIFF_MAX_FILE_SIZE_MODERATE 1000 * 50    // in characters  (approx 1000  lines with 50 characters per line)
#define MYERS_DIFF_MAX_FILE_SIZE_HUGE     10000 * 100  // in characters  (approx 10000 lines with 100 characters per line)
#define OTHER_MAX_FILE_SIZE               1000 * 100   // in characters  (approx 1000  lines with 100 characters per line)

#define ASSIGNMENT_MESSAGE ""

#define MAX_NUM_SUBMISSIONS 20
#define SUBMISSION_PENALTY 5
#define MAX_SUBMISSION_SIZE 100000      // 100 KB submitted files size
#define PART_NAMES { }

// ========================================================================================

void CustomizeAutoGrading(const std::string& username, nlohmann::json& j);
std::vector<std::string> stringOrArrayOfStrings(nlohmann::json j, const std::string what);

// =================================================================================
// =================================================================================

class TestCase {

public:

  // -------------------------------
  // CONSTRUCTOR
  TestCase (nlohmann::json& input,const nlohmann::json &whole_config);

  void General_Helper();
  void FileCheck_Helper();
  void Compilation_Helper();
  void Execution_Helper();

  // -------------------------------
  // ACCESSORS

  int getID() const { return test_case_id; }
  std::string getTitle() const;
  std::string getDetails () const { return _json.value("details",""); }

  int getPoints() const { return _json.value("points", 0); }
  bool getHidden() const { return _json.value("hidden", false); }
  bool getExtraCredit() const { return _json.value("extra_credit",false); }
  bool viewTestcaseMessage() const { return _json.value("view_testcase_message",true); }

  bool isFileCheck() const { return _json.value("type","Execution") == "FileCheck"; }
  bool isCompilation() const { return _json.value("type","Execution") == "Compilation"; }
  bool isExecution() const { return _json.value("type","Execution") == "Execution"; }

  bool isSubmissionLimit() const { return (isFileCheck() && _json.find("max_submissions") != _json.end()); }
  int getMaxSubmissions() const { assert (isSubmissionLimit()); return _json.value("max_submissions",20); }
  float getPenalty() const { assert (isSubmissionLimit()); return _json.value("penalty",-0.1); }

  void debugJSON() const { std::cout << _json.dump(2) << std::endl; }

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
  TestResultsFixedSize do_the_grading (int j) const;

  int numFileGraders() const {
    const nlohmann::json::const_iterator itr = _json.find("validation");
    if (itr == _json.end()) return 0;
    assert (itr->is_array());
    return (itr->size());
  }
  const nlohmann::json& getGrader(int i) const {
    const nlohmann::json::const_iterator itr = _json.find("validation");
    assert (itr != _json.end());
    assert (itr->is_array());
    assert (i >= 0 && i < itr->size());
    return (*itr)[i];
  }
  const nlohmann::json get_test_case_limits() const;

  static void reset_next_test_case_id() { next_test_case_id = 1; }

  bool ShowExecuteLogfile(const std::string &execute_logfile) const;

private:

  // -------------------------------
  // PRIVATE HELPER FUNCTIONS
  TestResults* dispatch(const nlohmann::json& grader, int autocheck_number) const;
  TestResults* custom_dispatch(const nlohmann::json& grader) const;

  // -------------------------------
  // REPRESENTATION
  int test_case_id;
  static int next_test_case_id;
  nlohmann::json& _json;
};




// =================================================================================
// =================================================================================

// NON MEMBER  HELPER FUNCTIONS

void adjust_test_case_limits(nlohmann::json &modified_test_case_limits, int rlimit_name, rlim_t value);

void AddSubmissionLimitTestCase(nlohmann::json &config_json);


std::string getAssignmentIdFromCurrentDirectory(std::string);

bool getFileContents(const std::string &filename, std::string &file_contents);
bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents,
                     std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > &messages);
bool openExpectedFile(const TestCase &tc, const nlohmann::json &j, std::string &expected_file_contents,
                      std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > &messages);

void fileStatus(const std::string &filename, bool &fileExists, bool &fileEmpty);

#endif
