#include <unistd.h>
#include <set>
#include <sys/stat.h>
#include "TestCase.h"
#include "JUnitGrader.h"
#include "myersDiff.h"
#include "tokenSearch.h"
#include "execute.h"

// FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff

//#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 50   // in characters  (approx 1000 lines with 50 characters per line)
#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 60   // in characters  (approx 1000 lines with 60 characters per line)
#define OTHER_MAX_FILE_SIZE      1000 * 100  // in characters  (approx 1000 lines with 100 characters per line)


std::string GLOBAL_replace_string_before = "";
std::string GLOBAL_replace_string_after = "";


int TestCase::next_test_case_id = 1;

std::string rlimit_name_decoder(int i);

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
  /*
  //#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  if (GLOBAL_replace_string_before != "") {
    std::cout << "BEFORE " << expected << std::endl;
    while (1) {
      int location = expected.find(GLOBAL_replace_string_before);
      if (location == std::string::npos) 
	break;
      expected.replace(location,GLOBAL_replace_string_before.size(),GLOBAL_replace_string_after);
    }
    std::cout << "AFTER  " << expected << std::endl;
  }
  //#endif
  */

  std::ifstream file(filename);
  if (!file.good()) { return false; }
  file_contents = std::string(std::istreambuf_iterator<char>(file), std::istreambuf_iterator<char>());
  std::cout << "file contents size = " << file_contents.size() << std::endl;
  return true;
}


bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, std::vector<std::string> &messages) {
  std::string filename = j.value("actual_file","");
  if (filename == "") {
    messages.push_back("ERROR!  STUDENT FILENAME MISSING");
    return false;
  }
  std::string p_filename = tc.getPrefix() + "_" + filename;

  // check for wildcard
  if (p_filename.find('*') != std::string::npos) {
    std::cout << "HAS WILDCARD!  MUST EXPAND" << p_filename << std::endl;
    std::vector<std::string> files;
    p_filename = replace_slash_with_double_underscore(p_filename);
    wildcard_expansion(files, p_filename, std::cout);
    if (files.size() == 0) {
      messages.push_back("ERROR!  No matches to wildcard pattern");
      return false;
    } else if (files.size() > 1) {
      messages.push_back("ERROR!  Multiple matches to wildcard pattern");
      return false;
    } else {
      p_filename = files[0];
      std::cout << "FOUND MATCH" << p_filename << std::endl;
    }
  }

  if (!getFileContents(p_filename,student_file_contents)) {
    messages.push_back("ERROR!  Could not open student file: '" + filename);
    return false;
  }
  if (student_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    messages.push_back("ERROR!  Student file '" + p_filename + "' too large for grader");
    return false;
  }
  return true;
}


bool openExpectedFile(const TestCase &tc, const nlohmann::json &j, std::string &expected_file_contents, std::vector<std::string> &messages) {
  std::string filename = j.value("expected_file","");
  if (filename == "") {
    messages.push_back("ERROR!  EXPECTED FILENAME MISSING");
    return false;
  }
  if (!getFileContents(filename,expected_file_contents)) {
    messages.push_back("ERROR!  Could not open expected file: '" + filename);
    return false;
  }
  if (expected_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    messages.push_back("ERROR!  Expected file '" + filename + "' too large for grader");
    return false;
  }
  return true;
}


TestResults* intComparison_doit (const TestCase &tc, const nlohmann::json& j) {
  std::string student_file_contents;
  std::vector<std::string> error_messages;
  if (!openStudentFile(tc,j,student_file_contents,error_messages)) {
    return new TestResults(0.0,error_messages);
  }
  if (student_file_contents.size() == 0) {
    return new TestResults(0.0,{"ERROR!  FILE EMPTY"});
  }
  try {
    int value = std::stoi(student_file_contents);
    std::cout << "DONE STOI " << value << std::endl;
    nlohmann::json::const_iterator itr = j.find("term");
    if (itr == j.end() || !itr->is_number()) {
      return new TestResults(0.0,{"ERROR!  integer \"term\" not specified"});
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
      return new TestResults(0.0, {"ERROR! UNKNOWN COMPARISON "+cmpstr});
    }
    if (success)
      return new TestResults(1.0);
    std::string description = j.value("description","MISSING DESCRIPTION");
    return new TestResults(0.0,{"FAILURE! "+description+" "+std::to_string(value)+" "+cmpstr+" "+std::to_string(term)});
  } catch (...) {
    return new TestResults(0.0,{"int comparison do it error stoi"});
  }
}



// =================================================================================
// =================================================================================

TestResults* TestCase::dispatch(const nlohmann::json& grader) const {
  std::string method = grader.value("method","");
  if      (method == "JUnitTestGrader")            { return JUnitTestGrader_doit(*this,grader);           }
  else if (method == "EmmaInstrumentationGrader")  { return EmmaInstrumentationGrader_doit(*this,grader); }
  else if (method == "MultipleJUnitTestGrader")    { return MultipleJUnitTestGrader_doit(*this,grader);   }
  else if (method == "EmmaCoverageReportGrader")   { return EmmaCoverageReportGrader_doit(*this,grader);  }
  else if (method == "searchToken")                { return searchToken_doit(*this,grader);               }
  else if (method == "intComparison")              { return intComparison_doit(*this,grader);             }
  else if (method == "myersDiffbyLinebyChar")      { return myersDiffbyLinebyChar_doit(*this,grader);     }
  else if (method == "myersDiffbyLinebyWord")      { return myersDiffbyLinebyWord_doit(*this,grader);     }
  else if (method == "myersDiffbyLine")            { return myersDiffbyLine_doit(*this,grader);           }
  else if (method == "myersDiffbyLineNoWhite")     { return myersDiffbyLineNoWhite_doit(*this,grader);    }
  else if (method == "diffLineSwapOk")             { return diffLineSwapOk_doit(*this,grader);            }
  else if (method == "fileExists")                 { return fileExists_doit(*this,grader);                }
  else if (method == "warnIfNotEmpty")             { return warnIfNotEmpty_doit(*this,grader);            }
  else if (method == "warnIfEmpty")                { return warnIfEmpty_doit(*this,grader);               }
  else if (method == "errorIfNotEmpty")            { return errorIfNotEmpty_doit(*this,grader);           }
  else if (method == "errorIfEmpty")               { return errorIfEmpty_doit(*this,grader);              }
  else                                             { return custom_dispatch(grader);                      }
}




// Make sure the sum of deductions across graders adds to at least 1.0.
// If a grader does not have a deduction setting, set it to 1/# of (non default) graders.
void VerifyGraderDeductions(nlohmann::json &json_graders) {
  assert (json_graders.is_array());
  assert (json_graders.size() > 0);
  float default_deduction = 1.0 / float(json_graders.size());
  float sum = 0.0;
  for (int i = 0; i < json_graders.size(); i++) {
    nlohmann::json::const_iterator itr = json_graders[i].find("deduction");
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
                      const std::string &filename) {
  if (files_covered.find(filename) != files_covered.end())
    return;
  //std::cout << "ADD GRADER WarnIfNotEmpty test for " << filename << std::endl;
  nlohmann::json j;
  j["method"] = "warnIfNotEmpty";
  j["actual_file"] = filename;
  if (filename.find("STDOUT") != std::string::npos) {
    j["description"] = "Standard Output (STDOUT)";
  } else if (filename.find("STDERR") != std::string::npos) {
    std::string executable_name = get_executable_name(command);
    if (executable_name == "/usr/bin/python") {
      j["description"] = "syntax error output from running python";
    } else if (executable_name == "/usr/bin/java") {
      j["description"] = "syntax error output from running java";
    } else if (executable_name == "/usr/bin/javac") {
      j["description"] = "syntax error output from running javac";
    } else {
      j["description"] = "Standard Error (STDERR)";
    }
  } else {
    j["description"] = "DEFAULTING TO "+filename;
  }
  j["deduction"] = 0.0;
  json_graders.push_back(j);
}


// Every command sends standard output and standard error to two
// files.  Make sure those files are sent to a grader.
void AddDefaultGraders(const std::vector<std::string> &commands,
                       nlohmann::json &json_graders) {
  std::set<std::string> files_covered;
  assert (json_graders.is_array());
  for (int i = 0; i < json_graders.size(); i++) {
    std::vector<std::string> filenames = stringOrArrayOfStrings(json_graders[i],"actual_file");
    for (int j = 0; j < filenames.size(); j++) {
      files_covered.insert(filenames[j]);
    }
  }
  if (commands.size() == 1) {
    AddDefaultGrader(commands[0],files_covered,json_graders,"STDOUT.txt");
    AddDefaultGrader(commands[0],files_covered,json_graders,"STDERR.txt");
  } else {
    for (int i = 0; i < commands.size(); i++) {
      AddDefaultGrader(commands[i],files_covered,json_graders,"STDOUT_"+std::to_string(i)+".txt");
      AddDefaultGrader(commands[i],files_covered,json_graders,"STDERR_"+std::to_string(i)+".txt");
    }
  }
}

// =================================================================================
// =================================================================================
// CONSTRUCTOR

TestCase::TestCase (const nlohmann::json& input) {
  //std::cout << "BEFORE " << input.dump(2) << std::endl;

  test_case_id = next_test_case_id;
  next_test_case_id++;
  
  _json = input;

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
  std::vector<std::string> commands = stringOrArrayOfStrings(_json,"command");
  VerifyGraderDeductions(*itr);
  AddDefaultGraders(commands,*itr);

  //std::cout << "AFTER " << _json.dump(2) << std::endl;
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
  nlohmann::json::iterator f_itr,v_itr;

  // Check the required fields for all test types
  f_itr = _json.find("actual_file");
  v_itr = _json.find("validation");

  if (f_itr != _json.end()) {
    assert (v_itr == _json.end());
    nlohmann::json v;
    v["method"] = "fileExists";
    v["actual_file"] = (*f_itr);
    v["description"] = (*f_itr);
    _json["validation"].push_back(v);
    _json.erase(f_itr);
  } else {
    assert (v_itr != _json.end());
  }

  f_itr = _json.find("actual_file");
  v_itr = _json.find("validation");

  assert (f_itr == _json.end());
  assert (v_itr != _json.end());

}

void TestCase::Compilation_Helper() {
  nlohmann::json::iterator f_itr,v_itr,w_itr;

  // Check the required fields for all test types
  f_itr = _json.find("executable_name");
  v_itr = _json.find("validation");

  if (f_itr != _json.end()) {
    nlohmann::json v;
    v["method"] = "fileExists";
    v["actual_file"] = (*f_itr);
    v["description"] = "executable created";
    v["deduction"] = 1.0;
    _json["validation"].push_back(v);
    _json.erase(f_itr);

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
    v2["actual_file"] = "STDERR.txt";
    v2["description"] = "compilation warnings and errors";
    v2["deduction"] = warning_fraction;
    _json["validation"].push_back(v2);
  }

  f_itr = _json.find("executable_name");
  v_itr = _json.find("validation");

  assert (f_itr == _json.end());
  assert (v_itr != _json.end());

  //assert (commands.size() > 0);

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
  std::cout << "getfilenames of " << _json << std::endl;
  std::vector<std::vector<std::string>> filenames;

  assert (_json.find("actual_file") == _json.end());
  int num = numFileGraders();
  assert (num > 0);
  std::cout << "num file graders" << num << std::endl;
  for (int v = 0; v < num; v++) {
    filenames.push_back(stringOrArrayOfStrings(getGrader(v),"actual_file"));
    assert (filenames[v].size() > 0);
  }
  std::cout << "filenames.size() " << filenames.size() << std::endl;

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
  }

  return _test_case_limits;
}


// =================================================================================
// =================================================================================



TestResults* TestCase::do_the_grading (int j) const {
  assert (j >= 0 && j < numFileGraders());

  nlohmann::json tcg = getGrader(j);
  return this->dispatch(tcg);
}

/*
  //#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  std::string expected = expected_file;
  if (GLOBAL_replace_string_before != "") {
    std::cout << "BEFORE " << expected << std::endl;
    while (1) {
      int location = expected.find(GLOBAL_replace_string_before);
      if (location == std::string::npos) 
	break;
      expected.replace(location,GLOBAL_replace_string_before.size(),GLOBAL_replace_string_after);
    }
    std::cout << "AFTER  " << expected << std::endl;
  }
  //#endif
*/


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

