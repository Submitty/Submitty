#include <unistd.h>
#include <set>
#include "TestCase.h"
#include "JUnitGrader.h"
#include "myersDiff.h"
#include "tokenSearch.h"
#include "execute.h"

// FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff

//#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 50   // in characters  (approx 1000 lines with 50 characters per line)
#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 60   // in characters  (approx 1000 lines with 60 characters per line)
#define OTHER_MAX_FILE_SIZE      1000 * 100  // in characters  (approx 1000 lines with 100 characters per line)


TestResults* custom_grader(const TestCase &tc, const nlohmann::json &j);

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


bool openStudentFile(const TestCase &tc, const nlohmann::json &j, std::string &student_file_contents, std::string &message) {
  std::string filename = j.value("filename","");
  if (filename == "") {
    message += "ERROR!  STUDENT FILENAME MISSING<br>";
    return false;
  }
  std::string prefix = tc.prefix() + "_";
  if (!getFileContents(prefix+filename,student_file_contents)) {
    message += "ERROR!  Could not open student file: '" + prefix+filename + "'<br>";
    return false;
  }
  if (student_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    message += "ERROR!  Student file '" + prefix+filename + "' too large for grader<br>";
    return false;
  }
  return true;
}


bool openInstructorFile(const TestCase &tc, const nlohmann::json &j, std::string &instructor_file_contents, std::string &message) {
  std::string filename = j.value("instructor_file","");
  if (filename == "") {
    message += "ERROR!  INSTRUCTOR FILENAME MISSING<br>";
    return false;
  }
  if (!getFileContents(filename,instructor_file_contents)) {
    message += "ERROR!  Could not open instructor file: '" + filename + "'<br>";
    return false;
  }
  if (instructor_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    message += "ERROR!  Instructor expected file '" + filename + "' too large for grader<br>";
    return false;
  }
  return true;
}


TestResults* intComparison_doit (const TestCase &tc, const nlohmann::json& j) {
  std::string student_file_contents;
  std::string error_message;
  if (!openStudentFile(tc,j,student_file_contents,error_message)) { 
    return new TestResults(0.0,error_message);
  }
  int value = std::stoi(student_file_contents);
  nlohmann::json::const_iterator itr = j.find("term");
  if (itr == j.end() || !itr->is_number()) {
    return new TestResults(0.0,"ERROR!  integer \"term\" not specified");
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
    return new TestResults(0.0, "ERROR! UNKNOWN COMPARISON "+cmpstr);
  }
  if (success) 
    return new TestResults(1.0);
  std::string description = j.value("description","MISSING DESCRIPTION");
  return new TestResults(0.0,"FAILURE! "+description+" "+std::to_string(value)+" "+cmpstr+" "+std::to_string(term));
}





// =================================================================================
// =================================================================================






TestResults* json_grader_doit(const TestCase& tc, const nlohmann::json& j) {


  std::string method = j.value("method","MISSING METHOD");

  nlohmann::json::const_iterator itr = j.find("deduction");
  assert (itr != j.end() && itr->is_number());
  float deduction = (*itr);
  assert (deduction >= -0.001 && deduction < 1.001);

  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"filename");
  assert (filenames.size() > 0);
  std::string filename = filenames[0];
  
  std::string prefix = tc.prefix();

  if (method == "JUnitTestGrader") {
    int num_tests = j.value("num_tests",1);
    TestCaseJUnit *g = TestCaseJUnit::JUnitTestGrader(filename,num_tests,deduction); 
    return g->doit(prefix);

  } else if (method == "EmmaInstrumentationGrader") {
    TestCaseJUnit *g = TestCaseJUnit::EmmaInstrumentationGrader(filename,deduction); 
    return g->doit(prefix);

  } else if (method == "MultipleJUnitTestGrader") {
    TestCaseJUnit *g = TestCaseJUnit::MultipleJUnitTestGrader(filename,deduction); 
    return g->doit(prefix);
    
  } else if (method == "EmmaCoverageReportGrader") {
    float coverage_threshold = j.value("coverage_threshold",100);
    TestCaseJUnit *g = TestCaseJUnit::EmmaCoverageReportGrader(filename,coverage_threshold,deduction); 
    return g->doit(prefix);
    
  } else if (method == "searchToken") {
    return searchToken_doit(tc,j); 

  } else if (method == "intComparison") {
    return intComparison_doit(tc,j); 

  } else if (method == "custom") {
    return custom_grader(tc,j); 
    
  } else {
    TestResults* (*cmp) ( const std::string&, const std::string& ) = NULL;
    if      (method == "myersDiffbyLinebyChar")  return myersDiffbyLinebyChar_doit(tc,j);
    else if (method == "myersDiffbyLinebyWord")  return myersDiffbyLinebyWord_doit(tc,j);
    else if (method == "myersDiffbyLine")        return myersDiffbyLine_doit(tc,j);
    else if (method == "myersDiffbyLineNoWhite") return myersDiffbyLineNoWhite_doit(tc,j);
    else if (method == "diffLineSwapOk")         return diffLineSwapOk_doit(tc,j);
    else if (method == "warnIfNotEmpty")         return warnIfNotEmpty_doit(tc,j);
    else if (method == "warnIfEmpty")            return warnIfEmpty_doit(tc,j); 
    else if (method == "errorIfNotEmpty")        return errorIfNotEmpty_doit(tc,j); 
    else if (method == "errorIfEmpty")           return errorIfEmpty_doit(tc,j);
    else {
      std::cout << "UNKNOWN METHOD " << method << std::endl;
      assert (0);
    }
  }
}


// Make sure the sum of deductions across graders adds to at least 1.0.
// If a grader does not have a deduction setting, set it to 1/# of (non default) graders.
void VerifyGraderDeductions(std::vector<nlohmann::json> &json_graders) {
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
                      std::vector<nlohmann::json> &json_graders,
                      const std::string &filename) {
  if (files_covered.find(filename) != files_covered.end())
    return;
  std::cout << "ADD GRADER WarnIfNotEmpty test for " << filename << std::endl;
  nlohmann::json j;
  j["method"] = "warnIfNotEmpty";
  j["filename"] = filename;
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
    j["description"] = filename;
  }
  j["deduction"] = 0.0;
  json_graders.push_back(j);
}


// Every command sends standard output and standard error to two
// files.  Make sure those files are sent to a grader.
void AddDefaultGraders(const std::vector<std::string> &commands,
                       std::vector<nlohmann::json> &json_graders) {
  std::set<std::string> files_covered;
  for (int i = 0; i < json_graders.size(); i++) {
    std::vector<std::string> filenames = stringOrArrayOfStrings(json_graders[i],"filename");
    for (int j = 0; j < filenames.size(); j++) {
      files_covered.insert(filenames[j]);
    }
  }
  assert (commands.size() > 0);
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


TestCase::TestCase (nlohmann::json j) {

  test_case_id = next_test_case_id;
  next_test_case_id++;
  FILE_EXISTS = false;
  COMPILATION = false;
  
  std::string type = j.value("type","DEFAULT");
    
  if (type == "FileExists") {
    _filenames.push_back(j.value("filename","FILENAME MISSING"));
    FILE_EXISTS = true;
    // view_file_results = false;
  } else if (type == "Compilation") {
    _filenames = stringOrArrayOfStrings(j,"executable_name");
    assert (_filenames.size() > 0);
    _commands = stringOrArrayOfStrings(j,"command");
    assert (_commands.size() > 0);
    warning_frac = j.value("warning_deduction",0);
    COMPILATION = true;
    _test_case_limits = j.value("resource_limits", nlohmann::json());
  
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
  } else {
    assert (type == "DEFAULT");
    std::vector<std::string> commands = stringOrArrayOfStrings(j,"command");
    std::vector<nlohmann::json> json_graders;
    nlohmann::json::iterator itr = j.find("validation");
    assert (itr != j.end());
    int num_graders = itr->size();
    for (nlohmann::json::iterator itr2 = (itr)->begin(); itr2 != (itr)->end(); itr2++) {
      nlohmann::json j = *itr2;
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
      json_graders.push_back(j);
    }
    assert (commands.size() > 0);
    assert (json_graders.size() > 0);
    VerifyGraderDeductions(json_graders);
    AddDefaultGraders(commands,json_graders);
    assert (commands.size() > 0);
    _commands = commands;
    assert (json_graders.size() >= 1); 
    test_case_grader_vec = json_graders;
    _filenames.push_back("");
    _test_case_limits = j.value("resource_limits",nlohmann::json());
  }
  
  _json = j;
}




TestResults* TestCase::do_the_grading (int j) {
  assert (j >= 0 && j < numFileGraders());

  std::string helper_message = "";

  bool ok_to_compare = true;

  // GET THE FILES READY
  std::string pf = getPrefixFilename(j);
  std::ifstream student_file(pf.c_str());
  if (!student_file) {
    std::stringstream tmp;
    //tmp << "Error: comparison " << j << ": Student's " << filename(j) << " does not exist";
    tmp << "ERROR! Student's " << pf << " does not exist";
    std::cerr << tmp.str() << std::endl;
    helper_message += tmp.str();
    ok_to_compare = false;
  }

  std::string expected = "";
  assert (test_case_grader_vec[j] != NULL);
  //  if (test_case_grader[j] != NULL) {
  //expected = test_case_grader_vec[j]->getExpected();
  expected = test_case_grader_vec[j].value("instructor_file",""); //MISSING INSTRUCTOR FILE");
    //}

  std::cout << "IN TEST CASE " << std::endl;

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


  std::ifstream expected_file(expected.c_str());
  if (!expected_file && expected != "") {
    std::stringstream tmp;
    //tmp << "Error: comparison" << j << ": Instructor's " + expected + " does not exist!";
    tmp << "ERROR! Instructor's " + expected + " does not exist!";
    std::cerr << tmp.str() << std::endl;
    if (helper_message != "") helper_message += "<br>";
    helper_message += tmp.str();
    ok_to_compare = false;
  }
  TestResults *answer = json_grader_doit(*this,test_case_grader_vec[j]); //,prefix());
  if (helper_message != "") {
    answer->addMessage(helper_message);
  }
  return answer;
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

