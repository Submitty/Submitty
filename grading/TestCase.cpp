#include <unistd.h>
#include "TestCase.h"
#include "JUnitGrader.h"
#include "myersDiff.h"


#include "tokenSearch.h"

// FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff

//#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 50   // in characters  (approx 1000 lines with 50 characters per line)
#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 60   // in characters  (approx 1000 lines with 60 characters per line)
#define OTHER_MAX_FILE_SIZE      1000 * 100  // in characters  (approx 1000 lines with 100 characters per line)


TestResults* custom_grader(const TestCase &tc, const nlohmann::json &j);

std::string GLOBAL_replace_string_before = "";
std::string GLOBAL_replace_string_after = "";


int TestCase::next_test_case_id = 1;


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



//TestResults* TestCaseTokens_doit(const TestCase& tc, const nlohmann::json& j);


TestResults* json_grader_doit(const TestCase& tc, const nlohmann::json& j) {


  std::string method = j.value("method","MISSING METHOD");
  float deduction = j.value("deduction",1.0); ///float(num_graders));

  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"filename");
  assert (filenames.size() > 0);
  std::string filename = filenames[0]; //j.value("filename","MISSING FILENAME");
  //std::string description = j.value("description",filename);
  //std::string instructor_file = j.value("instructor_file","");

  
  std::string prefix = tc.prefix();

  //TestCaseGrader *g;

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
    //g = new TestCaseComparison(cmp,filename,description,instructor_file,deduction);
  }

}



TestCase TestCase::MakeTestCase (nlohmann::json j) {
  std::string type = j.value("type","DEFAULT");
  //std::cout << "TYPE = " << type << std::endl;
  if (type == "FileExists") {
    return MakeFileExists(j.value("title","TITLE MISSING"),
                          j.value("filename","FILENAME MISSING"),
                          TestCasePoints(j.value("points",0)));
  } else if (type == "Compilation") {
    std::vector<std::string> commands = stringOrArrayOfStrings(j,"command");
    return MakeCompilation(j.value("title","TITLE MISSING"),
                           commands, //j.value("command","COMMAND MISSING"),
                           j.value("executable_name","EXECUTABLE FILENAME MISSING"),
                           TestCasePoints(j.value("points",0)),
                           j.value("warning_deduction",0),
                           j.value("resource_limits", nlohmann::json()));
  } else {
    assert (type == "DEFAULT");
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

    bool hidden = j.value("hidden",false);
    bool extra_credit = j.value("extra_credit",false);
    std::vector<std::string> commands = stringOrArrayOfStrings(j,"command");
    return MakeTestCase(j.value("title","TITLE MISSING"),
                        j.value("details",""),
                        commands,
                        TestCasePoints(j.value("points",0),hidden,extra_credit),
                        json_graders,
                        "",
                        j.value("resource_limits",nlohmann::json()));
  }
}







TestResults* TestCase::do_the_grading (int j) {
  assert (j >= 0 && j < numFileGraders());

  std::string helper_message = "";

  bool ok_to_compare = true;

  // GET THE FILES READY
  std::ifstream student_file(filename(j).c_str());
  if (!student_file) {
    std::stringstream tmp;
    //tmp << "Error: comparison " << j << ": Student's " << filename(j) << " does not exist";
    tmp << "ERROR! Student's " << filename(j) << " does not exist";
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

