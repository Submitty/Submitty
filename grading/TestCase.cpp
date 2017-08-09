#include <unistd.h>
#include <set>
#include <sys/stat.h>
#include "TestCase.h"
#include "JUnitGrader.h"
#include "DrMemoryGrader.h"
#include "PacmanGrader.h"
#include "myersDiff.h"
#include "tokenSearch.h"
#include "execute.h"

#include <sys/time.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <errno.h>
#include <sys/ipc.h>
#include <sys/shm.h>
#include <unistd.h>
#include <iostream>


// Set mode bits on shared memory
#define SHM_MODE (SHM_W | SHM_R | IPC_CREAT)


int TestCase::next_test_case_id = 1;

std::string rlimit_name_decoder(int i);

void TerminateProcess(float &elapsed, int childPID);
int resident_set_size(int childPID);

void adjust_test_case_limits(nlohmann::json &modified_test_case_limits,
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


std::vector<std::string> stringOrArrayOfStrings(nlohmann::json j, const std::string what) {
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


void fileStatus(const std::string &filename, bool &fileExists, bool &fileEmpty) {
  struct stat st;
  if (stat(filename.c_str(), &st) < 0) {
    // failure 
    fileExists = false;
  }
  else {
    fileExists = true;
    if (st.st_size == 0) {
      fileEmpty = true;
    } else {
      fileEmpty = false;
    }
  }
}


bool getFileContents(const std::string &filename, std::string &file_contents) {
  std::ifstream file(filename);
  if (!file.good()) { return false; }
  file_contents = std::string(std::istreambuf_iterator<char>(file), std::istreambuf_iterator<char>());
  //std::cout << "file contents size = " << file_contents.size() << std::endl;
  return true;
}


bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, 
                     std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > &messages) {

  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"actual_file");
  if (filenames.size() != 1) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  STUDENT FILENAME MISSING"));
    return false;
  }

  std::string filename = filenames[0];
  std::string p_filename = tc.getPrefix() + "_" + filename;

  // check for wildcard
  if (p_filename.find('*') != std::string::npos) {
    std::cout << "HAS WILDCARD!  MUST EXPAND '" << p_filename << "'" << std::endl;
    std::vector<std::string> files;
    wildcard_expansion(files, p_filename, std::cout);
    if (files.size() == 0) {
      wildcard_expansion(files, filename, std::cout);
    }
    if (files.size() == 0) {
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  No matches to wildcard pattern"));
      return false;
    } else if (files.size() > 1) {
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Multiple matches to wildcard pattern"));
      return false;
    } else {
      p_filename = files[0];
      std::cout << "FOUND MATCH" << p_filename << std::endl;
    }
  }

  if (!getFileContents(p_filename,student_file_contents)) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Could not open student file: '" + filename + "'"));
    return false;
  }
  if (student_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE_HUGE) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Student file '" + p_filename + "' too large for grader (" +
                                      std::to_string(student_file_contents.size()) + " vs. " +
                                      std::to_string(MYERS_DIFF_MAX_FILE_SIZE_HUGE) + ")"));
    return false;
  }
  return true;
}


bool openExpectedFile(const TestCase &tc, const nlohmann::json &j, std::string &expected_file_contents, 
                      std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > &messages) {

  std::string filename = j.value("expected_file","");
  if (filename == "") {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  EXPECTED FILENAME MISSING"));
    return false;
  }
  if (!getFileContents(filename,expected_file_contents)) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Could not open expected file: '" + filename));
    return false;
  }
  if (expected_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE_HUGE) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Expected file '" + filename + "' too large for grader (" +
                                      std::to_string(expected_file_contents.size()) + " vs. " +
                                      std::to_string(MYERS_DIFF_MAX_FILE_SIZE_HUGE) + ")"));
    return false;
  }
  return true;
}


TestResults* intComparison_doit (const TestCase &tc, const nlohmann::json& j) {
  std::string student_file_contents;
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > error_messages;
  if (!openStudentFile(tc,j,student_file_contents,error_messages)) {
    return new TestResults(0.0,error_messages);
  }
  if (student_file_contents.size() == 0) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR!  FILE EMPTY")});
  }
  try {
    int value = std::stoi(student_file_contents);
    std::cout << "DONE STOI " << value << std::endl;
    nlohmann::json::const_iterator itr = j.find("term");
    if (itr == j.end() || !itr->is_number()) {
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR!  integer \"term\" not specified")});
    }
    int term = (*itr);
    std::string cmpstr = j.value("comparison","MISSING COMPARISON");
    bool success;
    if (cmpstr == "eq")      success = (value == term);
    else if (cmpstr == "ne") success = (value != term);
    else if (cmpstr == "gt") success = (value > term);
    else if (cmpstr == "lt") success = (value < term);
    else if (cmpstr == "ge") success = (value >= term);
    else if (cmpstr == "le") success = (value <= term);
    else {
      return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE,"ERROR! UNKNOWN COMPARISON "+cmpstr)});
    }
    if (success)
      return new TestResults(1.0);
    std::string description = j.value("description","MISSING DESCRIPTION");
    std::string failure_message = j.value("failure_message",
                                          "ERROR! "+description+" "+std::to_string(value)+" "+cmpstr+" "+std::to_string(term));
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,failure_message)});
  } catch (...) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"int comparison do it error stoi")});
  }
}



// =================================================================================
// =================================================================================

TestResults* TestCase::dispatch(const nlohmann::json& grader, int autocheck_number) const {
  std::string method = grader.value("method","");
  if      (method == "")                           { return NULL;                                           }
  else if (method == "JUnitTestGrader")            { return JUnitTestGrader_doit(*this,grader);             }
  else if (method == "EmmaInstrumentationGrader")  { return EmmaInstrumentationGrader_doit(*this,grader);   }
  else if (method == "MultipleJUnitTestGrader")    { return MultipleJUnitTestGrader_doit(*this,grader);     }
  else if (method == "EmmaCoverageReportGrader")   { return EmmaCoverageReportGrader_doit(*this,grader);    }
  else if (method == "DrMemoryGrader")             { return DrMemoryGrader_doit(*this,grader);              }
  else if (method == "PacmanGrader")               { return PacmanGrader_doit(*this,grader);                }
  else if (method == "searchToken")                { return searchToken_doit(*this,grader);                 }
  else if (method == "intComparison")              { return intComparison_doit(*this,grader);               }
  else if (method == "myersDiffbyLinebyChar")      { return myersDiffbyLinebyChar_doit(*this,grader);       }
  else if (method == "myersDiffbyLinebyWord")      { return myersDiffbyLinebyWord_doit(*this,grader);       }
  else if (method == "myersDiffbyLine")            { return myersDiffbyLine_doit(*this,grader);             }
  else if (method == "myersDiffbyLineNoWhite")     { return myersDiffbyLineNoWhite_doit(*this,grader);      }
  else if (method == "diffLineSwapOk")             { return diffLineSwapOk_doit(*this,grader);              }
  else if (method == "fileExists")                 { return fileExists_doit(*this,grader);                  }
  else if (method == "warnIfNotEmpty")             { return warnIfNotEmpty_doit(*this,grader);              }
  else if (method == "warnIfEmpty")                { return warnIfEmpty_doit(*this,grader);                 }
  else if (method == "errorIfNotEmpty")            { return errorIfNotEmpty_doit(*this,grader);             }
  else if (method == "errorIfEmpty")               { return errorIfEmpty_doit(*this,grader);                }
  else if (method == "ImageDiff")                  { return ImageDiff_doit(*this,grader, autocheck_number); }
  else                                             { return custom_dispatch(grader);                        }
}




// Make sure the sum of deductions across graders adds to at least 1.0.
// If a grader does not have a deduction setting, set it to 1/# of (non default) graders.
void VerifyGraderDeductions(nlohmann::json &json_graders) {
  assert (json_graders.is_array());
  assert (json_graders.size() > 0);

  int json_grader_count = 0;
  for (int i = 0; i < json_graders.size(); i++) {
    nlohmann::json::const_iterator itr = json_graders[i].find("method");
    if (itr != json_graders[i].end()) {
      json_grader_count++;
    }
  }

  assert (json_grader_count > 0);

  float default_deduction = 1.0 / float(json_grader_count);
  float sum = 0.0;
  for (int i = 0; i < json_graders.size(); i++) {
    nlohmann::json::const_iterator itr = json_graders[i].find("method");
    if (itr == json_graders[i].end()) {
      json_graders[i]["deduction"] = 0;
      continue;
    }
    itr = json_graders[i].find("deduction");
    float deduction;
    if (itr == json_graders[i].end()) {
      json_graders[i]["deduction"] = default_deduction;
      deduction = default_deduction;
    } else {
      assert (itr->is_number());
      deduction = (*itr);
    }
    sum += deduction;
  }

  if (sum < 0.99) {
    std::cout << "ERROR! DEDUCTION SUM < 1.0: " << sum << std::endl;
  }
}



// If we don't already have a grader for the indicated file, add a
// simple "WarnIfNotEmpty" check, that will print the contents of the
// file to help the student debug if their output has gone to the
// wrong place or if there was an execution error
void AddDefaultGrader(const std::string &command,
                      const std::set<std::string> &files_covered,
                      nlohmann::json& json_graders,
                      const std::string &filename,
                      const nlohmann::json &whole_config) {
  if (files_covered.find(filename) != files_covered.end())
    return;
  //std::cout << "ADD GRADER WarnIfNotEmpty test for " << filename << std::endl;
  nlohmann::json j;
  j["method"] = "warnIfNotEmpty";
  j["actual_file"] = filename;
  if (filename.find("STDOUT") != std::string::npos) {
    j["description"] = "Standard Output (STDOUT)";
  } else if (filename.find("STDERR") != std::string::npos) {
    std::string program_name = get_program_name(command,whole_config);
    if (program_name == "/usr/bin/python") {
      j["description"] = "syntax error output from running python";
    } else if (program_name == "/usr/bin/java") {
      j["description"] = "syntax error output from running java";
    } else if (program_name == "/usr/bin/javac") {
      j["description"] = "syntax error output from running javac";
    } else {
      j["description"] = "Standard Error (STDERR)";
    }
  } else {
    j["description"] = "DEFAULTING TO "+filename;
  }
  j["deduction"] = 0.0;
  j["show_message"] = "on_failure";
  j["show_actual"] = "on_failure";
  json_graders.push_back(j);
}


// Every command sends standard output and standard error to two
// files.  Make sure those files are sent to a grader.
void AddDefaultGraders(const std::vector<std::string> &commands,
                       nlohmann::json &json_graders,
                       const nlohmann::json &whole_config) {
  std::set<std::string> files_covered;
  assert (json_graders.is_array());
  for (int i = 0; i < json_graders.size(); i++) {
    std::vector<std::string> filenames = stringOrArrayOfStrings(json_graders[i],"actual_file");
    for (int j = 0; j < filenames.size(); j++) {
      files_covered.insert(filenames[j]);
    }
  }
  if (commands.size() == 1) {
    AddDefaultGrader(commands[0],files_covered,json_graders,"STDOUT.txt",whole_config);
    AddDefaultGrader(commands[0],files_covered,json_graders,"STDERR.txt",whole_config);
  } else {
    for (int i = 0; i < commands.size(); i++) {
      AddDefaultGrader(commands[i],files_covered,json_graders,"STDOUT_"+std::to_string(i)+".txt",whole_config);
      AddDefaultGrader(commands[i],files_covered,json_graders,"STDERR_"+std::to_string(i)+".txt",whole_config);
    }
  }
}

// =================================================================================
// =================================================================================
// CONSTRUCTOR

bool validShowValue(const nlohmann::json& v) {
  return (v.is_string() &&
          (v == "always" ||
           v == "never" ||
           v == "on_failure" ||
           v == "on_success"));
}

TestCase::TestCase (nlohmann::json& input,const nlohmann::json &whole_config) : _json(input) {
  test_case_id = next_test_case_id;
  next_test_case_id++;
  General_Helper();

  if (isFileCheck()) {
    FileCheck_Helper();
  } else if (isCompilation()) {
    Compilation_Helper();
  } else {
    assert (isExecution());
    Execution_Helper();
  }

  nlohmann::json::iterator itr = _json.find("validation");
  if (itr != _json.end()) {
    assert (itr->is_array());
    VerifyGraderDeductions(*itr);
    std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
    AddDefaultGraders(commands,*itr,whole_config);

     for (int i = 0; i < (*itr).size(); i++) {
      nlohmann::json& grader = (*itr)[i];
      nlohmann::json::iterator itr2;
      std::string method = grader.value("method","MISSING METHOD");
      itr2 = grader.find("show_message");
      if (itr2 == grader.end()) {
        if (method == "warnIfNotEmpty" || method == "warnIfEmpty") {
          grader["show_message"] = "on_failure";
        } else {
          if (grader.find("actual_file") != grader.end() &&
              *(grader.find("actual_file")) == "execute_logfile.txt" &&
              grader.find("show_actual") != grader.end() &&
              *(grader.find("show_actual")) == "never") {
            grader["show_message"] = "never";
          } else {
            grader["show_message"] = "always";
          }
        }
      } else {
        assert (validShowValue(*itr2));
      }
      if (grader.find("actual_file") != grader.end()) {
        itr2 = grader.find("show_actual");
        if (itr2 == grader.end()) {
          if (method == "warnIfNotEmpty" || method == "warnIfEmpty") {
            grader["show_actual"] = "on_failure";
          } else {
            grader["show_actual"] = "always";
          }
        } else {
          assert (validShowValue(*itr2));
        }
      }
      if (grader.find("expected_file") != grader.end()) {
        itr2 = grader.find("show_expected");
        if (itr2 == grader.end()) {
          grader["show_expected"] = "always";
        } else {
          assert (validShowValue(*itr2));
        }
      }
    }
  }
}


void TestCase::General_Helper() {
  nlohmann::json::iterator itr;

  // Check the required fields for all test types
  itr = _json.find("title");
  assert (itr != _json.end() && itr->is_string());

  // Check the type of the optional fields
  itr = _json.find("description");
  if (itr != _json.end()) { assert (itr->is_string()); }
  itr = _json.find("points");
  if (itr != _json.end()) { assert (itr->is_number()); }
}

void TestCase::FileCheck_Helper() {
  nlohmann::json::iterator f_itr,v_itr,m_itr,itr;

  // Check the required fields for all test types
  f_itr = _json.find("actual_file");
  v_itr = _json.find("validation");
  m_itr = _json.find("max_submissions");
  
  if (f_itr != _json.end()) {
    // need to rewrite to use a validation
    assert (m_itr == _json.end());
    assert (v_itr == _json.end());
    nlohmann::json v;
    v["method"] = "fileExists";
    v["actual_file"] = (*f_itr);
    std::vector<std::string> filenames = stringOrArrayOfStrings(_json,"actual_file");
    std::string desc;
    for (int i = 0; i < filenames.size(); i++) {
      if (i != 0) desc += " ";
      desc += filenames[i];
    }
    v["description"] = desc;
    if (filenames.size() != 1) {
      v["show_actual"] = "never";
    }
    if (_json.value("one_of",false)) {
      v["one_of"] = true;
    }
    _json["validation"].push_back(v);
    _json.erase(f_itr);
  } else if (v_itr != _json.end()) {
    // already has a validation
  } else {
    assert (m_itr != _json.end());
    assert (m_itr->is_number_integer());
    assert ((int)(*m_itr) >= 1);

    itr = _json.find("points");
    if (itr == _json.end()) {
      _json["points"] = -5;
    } else {
      assert (itr->is_number_integer());
      assert ((int)(*itr) <= 0);
    }
    itr = _json.find("penalty");
    if (itr == _json.end()) {
      _json["penalty"] = -0.1;
    } else {
      assert (itr->is_number());
      assert ((*itr) <= 0);
    }
    itr = _json.find("title");
    if (itr == _json.end()) {
      _json["title"] = "Submission Limit";
    } else {
      assert (itr->is_string());
    }
  }
}

bool HasActualFileCheck(const nlohmann::json &v_itr, const std::string &actual_file) {
  assert (actual_file != "");
  const std::vector<nlohmann::json> tmp = v_itr.get<std::vector<nlohmann::json> >();
  for (int i = 0; i < tmp.size(); i++) {
    if (tmp[i].value("actual_file","") == actual_file) {
      return true;
    }
  }
  return false;
}

void TestCase::Compilation_Helper() {
  nlohmann::json::iterator f_itr,v_itr,w_itr;

  // Check the required fields for all test types
  f_itr = _json.find("executable_name");
  v_itr = _json.find("validation");

  if (v_itr != _json.end()) {
    assert (v_itr->is_array());
    std::vector<nlohmann::json> tmp = v_itr->get<std::vector<nlohmann::json> >();
  }

  if (f_itr != _json.end()) {

    std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
    assert (commands.size() > 0);
    for (int i = 0; i < commands.size(); i++) {
      w_itr = _json.find("warning_deduction");
      float warning_fraction = 0.0;
      if (w_itr != _json.end()) {
        assert (w_itr->is_number());
        warning_fraction = (*w_itr);
        _json.erase(w_itr);
      }
      assert (warning_fraction >= 0.0 && warning_fraction <= 1.0);
      nlohmann::json v2;
      v2["method"] = "errorIfNotEmpty";
      if (commands.size() == 1) {
        v2["actual_file"] = "STDERR.txt";
      } else {
        v2["actual_file"] = "STDERR_" + std::to_string(i) + ".txt";
      }
      v2["description"] = "Compilation Errors and/or Warnings";
      v2["show_actual"] = "on_failure";
      v2["show_message"] = "on_failure";
      v2["deduction"] = warning_fraction;

      v_itr = _json.find("validation");
      if (v_itr == _json.end() ||
          !HasActualFileCheck(*v_itr,v2["actual_file"])) {
        _json["validation"].push_back(v2);
      }
    }


    std::vector<std::string> executable_names = stringOrArrayOfStrings(_json,"executable_name");
    assert (executable_names.size() > 0);
    for (int i = 0; i < executable_names.size(); i++) {
      nlohmann::json v;
      v["method"] = "fileExists";
      v["actual_file"] = executable_names[i];
      v["description"] = "Create Executable";
      v["show_actual"] = "on_failure";
      v["show_message"] = "on_failure";
      v["deduction"] = 1.0/executable_names.size();

      v_itr = _json.find("validation");
      if (v_itr == _json.end() ||
          !HasActualFileCheck(*v_itr,v["actual_file"])) {
        _json["validation"].push_back(v);
      }
    }
  }

  v_itr = _json.find("validation");

  if (v_itr != _json.end()) {
    assert (v_itr->is_array());
    std::vector<nlohmann::json> tmp = v_itr->get<std::vector<nlohmann::json> >();
  }

  assert (v_itr != _json.end());
}

void TestCase::Execution_Helper() {
  nlohmann::json::iterator itr = _json.find("validation");
  assert (itr != _json.end());
  for (nlohmann::json::iterator itr2 = (itr)->begin(); itr2 != (itr)->end(); itr2++) {
    nlohmann::json& j = *itr2;
    std::string method = j.value("method","");
    std::string description = j.value("description","");
    if (description=="") {
      if (method == "EmmaInstrumentationGrader") {
        j["description"] = "JUnit EMMA instrumentation output";
      } else if (method =="JUnitTestGrader") {
        j["description"] = "JUnit output";
      } else if (method =="EmmaCoverageReportGrader") {
        j["description"] = "JUnit EMMA coverage report";
      } else if (method =="MultipleJUnitTestGrader") {
        j["description"] = "TestRunner output";
      }
    }
  }

  //assert (commands.size() > 0);

}


// =================================================================================
// =================================================================================
// ACCESSORS


std::string TestCase::getTitle() const {
  const nlohmann::json::const_iterator& itr = _json.find("title");
  if (itr == _json.end()) {
    std::cerr << "ERROR! MISSING TITLE" << std::endl;
  }
  assert (itr->is_string());
  return (*itr);
}


std::string TestCase::getPrefix() const {
  std::stringstream ss;
  ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
  return ss.str();
}


std::vector<std::vector<std::string>> TestCase::getFilenames() const {
  //std::cout << "getfilenames of " << _json << std::endl;
  std::vector<std::vector<std::string>> filenames;

  assert (_json.find("actual_file") == _json.end());
  int num = numFileGraders();
  assert (num > 0);
  for (int v = 0; v < num; v++) {
    filenames.push_back(stringOrArrayOfStrings(getGrader(v),"actual_file"));


    assert (filenames[v].size() > 0);
  }

  return filenames;
}



const nlohmann::json TestCase::get_test_case_limits() const {
  nlohmann::json _test_case_limits = _json.value("resource_limits", nlohmann::json());

  if (isCompilation()) {
    // compilation (g++, clang++, javac) usually requires multiple
    // threads && produces a large executable

    // Over multiple semesters of Data Structures C++ assignments, the
    // maximum number of vfork (or fork or clone) system calls needed
    // to compile a student submissions was 28.
    //
    // It seems that g++     uses approximately 2 * (# of .cpp files + 1) processes
    // It seems that clang++ uses approximately 2 +  # of .cpp files      processes

    adjust_test_case_limits(_test_case_limits,RLIMIT_NPROC,100);

    // 10 seconds was sufficient time to compile most Data Structures
    // homeworks, but some submissions required slightly more time
    adjust_test_case_limits(_test_case_limits,RLIMIT_CPU,60);              // 60 seconds
    adjust_test_case_limits(_test_case_limits,RLIMIT_FSIZE,10*1000*1000);  // 10 MB executable

    adjust_test_case_limits(_test_case_limits,RLIMIT_RSS,1000*1000*1000);  // 1 GB
  }

  if (isSubmittyCount()) {
    // necessary for the analysis tools count program
    adjust_test_case_limits(_test_case_limits,RLIMIT_NPROC,100);
    adjust_test_case_limits(_test_case_limits,RLIMIT_NOFILE,1000);
    adjust_test_case_limits(_test_case_limits,RLIMIT_CPU,60);
    adjust_test_case_limits(_test_case_limits,RLIMIT_AS,RLIM_INFINITY);
    adjust_test_case_limits(_test_case_limits,RLIMIT_SIGPENDING,100);
  }
  
  return _test_case_limits;
}

bool TestCase::ShowExecuteLogfile(const std::string &execute_logfile) const {
  for (int i = 0; i < numFileGraders(); i++) {
    const nlohmann::json& grader = getGrader(i);
    nlohmann::json::const_iterator a = grader.find("actual_file");
    if (a != grader.end()) {
      if (*a == execute_logfile) {
        nlohmann::json::const_iterator s = grader.find("show_actual");
        if (s != grader.end()) {
          if (*s == "never") return false;
        }
      }
    }
  }
  return true;
}

// =================================================================================
// =================================================================================


TestResultsFixedSize TestCase::do_the_grading (int j) const {

  // ALLOCATE SHARED MEMORY
  int memid;
  TestResultsFixedSize *tr_ptr;
  if ((memid = shmget(IPC_PRIVATE,sizeof(TestResultsFixedSize),SHM_MODE)) == -1) {
    std::cout << "Unsuccessful memory get" << std::endl;
    std::cout << "Errno was " << errno << std::endl;
    exit(-1);
  }


  // FORK A CHILD THREAD TO DO THE VALIDATION
  pid_t childPID = fork();
  // ensure fork was successful
  assert (childPID >= 0);


  if (childPID == 0) {
    // CHILD

    // attach to shared memory
    tr_ptr = (TestResultsFixedSize*) shmat(memid,0 ,0);
    tr_ptr->initialize();

    // perform the validation (this might hang or crash)
    assert (j >= 0 && j < numFileGraders());
    nlohmann::json tcg = getGrader(j);
    TestResults* answer_ptr = this->dispatch(tcg, j);
    assert (answer_ptr != NULL);

    // write answer to shared memory and terminate this process
    answer_ptr->PACK(tr_ptr);
    //std::cout << "do_the_grading, child completed successfully " << std::endl;
    exit(0);

  } else {
    // PARENT

    // attach to shared memory
    tr_ptr = (TestResultsFixedSize *)  shmat(memid,0 ,0);

    bool time_kill=false;
    bool memory_kill=false;
    pid_t wpid = 0;
    int status;
    float elapsed = 0;
    float next_checkpoint = 0;
    int rss_memory = 0;
    int seconds_to_run = 20;
    int allowed_rss_memory = 1000000;

    // loop while waiting for child to finish
    do {
      wpid = waitpid(childPID, &status, WNOHANG);
      if (wpid == 0) {
        // terminate for excessive time
        if (elapsed > seconds_to_run) {
          std::cout << "do_the_grading error:  Killing child process " << childPID
                    << " after " << elapsed << " seconds elapsed." << std::endl;
          TerminateProcess(elapsed,childPID);
          time_kill=true;
        }
        // terminate for excessive memory usage (RSS = resident set size = RAM)
        if (rss_memory > allowed_rss_memory) {
          std::cout << "do_the_grading error:  Killing child process " << childPID
                    << " for using " << rss_memory << " kb RAM.  (limit is " << allowed_rss_memory << " kb)" << std::endl;
          TerminateProcess(elapsed,childPID);
          memory_kill=true;
        }
        // monitor time & memory usage
        if (!time_kill && !memory_kill) {
          // sleep 1/10 of a second
          usleep(100000);
          elapsed+= 0.1;
        }
        if (elapsed >= next_checkpoint) {
          rss_memory = resident_set_size(childPID);
          //std::cout << "do_the_grading running, time elapsed = " << elapsed
          //          << " seconds,  memory used = " << rss_memory << " kb" << std::endl;
          next_checkpoint = std::min(elapsed+5.0,elapsed*2.0);
        }
      }
    } while (wpid == 0);
  }

  // COPY result from shared memory
  TestResultsFixedSize answer = *tr_ptr;

  // detach shared memory and destroy the memory queue
  shmdt((void *)tr_ptr);
  if (shmctl(memid,IPC_RMID,0) < 0) {
    std::cout << "Problems destroying shared memory ID" << std::endl;
    std::cout << "Errno was " <<  errno << std::endl;
    exit(-1);
  }

  std::cout << "do the grading complete: " << answer << std::endl;
  return answer;
}



std::string getAssignmentIdFromCurrentDirectory(std::string dir) {
  //std::cout << "getassignmentidfromcurrentdirectory '" << dir << "'\n";
  assert (dir.size() >= 1);
  assert (dir[dir.size()-1] != '/');

  int last_slash = -1;
  int second_to_last_slash = -1;
  std::string tmp;
  while (1) {
    int loc = dir.find('/',last_slash+1);
    if (loc == std::string::npos) break;
    second_to_last_slash = last_slash;
    last_slash = loc;
    if (second_to_last_slash != -1) {
      tmp = dir.substr(second_to_last_slash+1,last_slash-second_to_last_slash-1);
    }
    //std::cout << "tmp is now '" << tmp << "'\n";  
  }
  assert (tmp.size() >= 1);
  return tmp;
}


// Go through the instructor-written test cases.
//   If the autograding points are non zero, and
//   if the instructor didn't add a penalty for excessive submissions, then
//   add a standard small penalty for > 20 submissions.
//
void AddSubmissionLimitTestCase(nlohmann::json &config_json) {
  int total_points = 0;
  bool has_limit_test = false;

  // count total points and search for submission limit testcase
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {
    TestCase my_testcase((*tc)[i],config_json);
    int points = (*tc)[i].value("points",0);
    if (points > 0) {
      total_points += points;
    }
    if (my_testcase.isSubmissionLimit()) {
      has_limit_test = true;
    }
  }

  // add submission limit test case
  if (!has_limit_test) {
    nlohmann::json limit_test;
    limit_test["type"] = "FileCheck";
    limit_test["title"] = "Submission Limit";
    limit_test["max_submissions"] = MAX_NUM_SUBMISSIONS;
    if (total_points > 0) {
      limit_test["points"] = -5;
      limit_test["penalty"] = -0.1;
    } else {
      limit_test["points"] = 0;
      limit_test["penalty"] = 0;
    }
    config_json["testcases"].push_back(limit_test);
  }


  // FIXME:  ugly...  need to reset the id...
  TestCase::reset_next_test_case_id();
}




void RecursiveReplace(nlohmann::json& j, const std::string& placeholder, const std::string& replacement) {
  if (j.is_string()) {
    std::string str = j.get<std::string>();
    int pos = str.find(placeholder);
    if (pos != std::string::npos) {
      std::cout << "REPLACING '" << str << "' with '";
      str.replace(pos,placeholder.length(),replacement);
      std::cout << str << "'" << std::endl;
      j = str;
    }
  } else if (j.is_array() || j.is_object()) {
    for (nlohmann::json::iterator itr = j.begin(); itr != j.end(); itr++) {
      RecursiveReplace(*itr,placeholder,replacement);
    }
  }
}


void CustomizeAutoGrading(const std::string& username, nlohmann::json& j) {
  if (j.find("string_replacement") != j.end()) {
    // Read and check string replacement variables
    nlohmann::json j2 = j["string_replacement"];
    std::string placeholder = j2.value("placeholder","");
    assert (placeholder != "");
    std::string replacement = j2.value("replacement","");
    assert (replacement != "");
    assert (replacement == "hashed_username");
    int mod_value = j2.value("mod",-1);
    assert (mod_value > 0);
    
    int A = 54059; /* a prime */
    int B = 76963; /* another prime */
    int FIRSTH = 37; /* also prime */
    unsigned int sum = FIRSTH;
    for (int i = 0; i < username.size(); i++) {
      sum = (sum * A) ^ (username[i] * B);
    }
    int assigned = (sum % mod_value)+1; 
  
    std::string repl = std::to_string(assigned);

    nlohmann::json::iterator association = j2.find("association");
    if (association != j2.end()) {
      repl = (*association)[repl];
    }

    nlohmann::json::iterator itr = j.find("testcases");
    if (itr != j.end()) {
      RecursiveReplace(*itr,placeholder,repl);
    }
  }
}
