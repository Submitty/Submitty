#include <unistd.h>
#include "TestCase.h"
#include "JUnitGrader.h"


std::string GLOBAL_replace_string_before = "";
std::string GLOBAL_replace_string_after = "";


int TestCase::next_test_case_id = 1;



TestCase TestCase::MakeTestCase (nlohmann::json j) {
  std::string type = j.value("type","DEFAULT");
  //std::cout << "TYPE = " << type << std::endl;
  if (type == "FileExists") {
    return MakeFileExists(j.value("title","TITLE MISSING"),
                          j.value("filename","FILENAME MISSING"),
                          TestCasePoints(j.value("points",0)));
  } else if (type == "Compilation") {
    return MakeCompilation(j.value("title","TITLE MISSING"),
                           j.value("command","COMMAND MISSING"),
                           j.value("executable_name","EXECUTABLE FILENAME MISSING"),
                           TestCasePoints(j.value("points",0)),
                           j.value("warning_deduction",0),
                           j.value("resource_limits", nlohmann::json()));
  } else {
    assert (type == "DEFAULT");
    std::vector<TestCaseGrader*> graders;
    nlohmann::json::iterator itr = j.find("validation");
    assert (itr != j.end());
    int num_graders = itr->size();
    for (nlohmann::json::iterator itr2 = (itr)->begin(); itr2 != (itr)->end(); itr2++) {
      std::string method = itr2->value("method","MISSING METHOD");
      float deduction = itr2->value("deduction",1.0/float(num_graders));
      std::string filename = itr2->value("filename","MISSING FILENAME");
      std::string description = itr2->value("description",filename);
      std::string instructor_file = itr2->value("instructor_file","");
      std::vector<std::string> data_vec;
      nlohmann::json::iterator data_json = itr2->find("data");
      if (data_json != itr2->end()) {
        for (int i = 0; i < data_json->size(); i++) {
          data_vec.push_back((*data_json)[i]);
        }
      }
      


      if (method == "JUnitTestGrader") {
        int num_tests = itr2->value("num_tests",1);
        graders.push_back(TestCaseJUnit::JUnitTestGrader(filename,num_tests,deduction)); 

      } else if (method == "EmmaInstrumentationGrader") {
        graders.push_back(TestCaseJUnit::EmmaInstrumentationGrader(filename,deduction)); 
        
      } else if (method == "MultipleJUnitTestGrader") {
        graders.push_back(TestCaseJUnit::MultipleJUnitTestGrader(filename,deduction)); 

      } else if (method == "EmmaCoverageReportGrader") {
        float coverage_threshold = itr2->value("coverage_threshold",100);
        graders.push_back(TestCaseJUnit::EmmaCoverageReportGrader(filename,coverage_threshold,deduction)); 

      } else if (method == "searchToken") {
        graders.push_back(new TestCaseTokens(&searchToken,filename,description,data_vec,deduction));
        
      } else {
        TestResults* (*cmp) ( const std::string&, const std::string& ) = NULL;
        if      (method == "myersDiffbyLinebyChar")  cmp = &myersDiffbyLinebyChar;
        else if (method == "myersDiffbyLinebyWord")  cmp = &myersDiffbyLinebyWord;
        else if (method == "myersDiffbyLine")        cmp = &myersDiffbyLine;
        else if (method == "myersDiffbyLineNoWhite") cmp = &myersDiffbyLineNoWhite;
        else if (method == "diffLineSwapOk")         cmp = &diffLineSwapOk;
        else if (method == "warnIfNotEmpty")         { cmp = &warnIfNotEmpty; deduction = 0.0; }
        else if (method == "warnIfEmpty")            { cmp = &warnIfEmpty; deduction = 0.0; }
        else if (method == "errorIfNotEmpty")         { cmp = &errorIfNotEmpty; } //deduction = 1.0; }
        else if (method == "errorIfEmpty")            { cmp = &errorIfEmpty; } //deduction = 1.0; }
        else {
          std::cout << "UNKNOWN METHOD " << method << std::endl;
          assert (0);
        }
        graders.push_back(new TestCaseComparison(cmp,filename,description,instructor_file,deduction));
      }
    }
    bool hidden = j.value("hidden",false);
    bool extra_credit = j.value("extra_credit",false);
    return MakeTestCase(j.value("title","TITLE MISSING"),
                        j.value("details","DETAILS MISSING"),
                        j.value("command","COMMAND MISSING"),
                        TestCasePoints(j.value("points",0),hidden,extra_credit),
                        graders,
                        "",
                        j.value("resource_limits",nlohmann::json()));
                        //{});
  }
}







TestResults* TestCase::do_the_grading (int j, std::string &helper_message) {
  assert (j >= 0 && j < numFileGraders());

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
  expected = test_case_grader_vec[j]->getExpected();
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
  return test_case_grader_vec[j]->doit(prefix());
}


// FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff

//#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 50   // in characters  (approx 1000 lines with 50 characters per line)
#define MYERS_DIFF_MAX_FILE_SIZE 1000 * 60   // in characters  (approx 1000 lines with 60 characters per line)
#define OTHER_MAX_FILE_SIZE      1000 * 100  // in characters  (approx 1000 lines with 100 characters per line)



TestResults* TestCaseComparison::doit(const std::string &prefix) {


  std::cout << "IN DOIT FOR COMPARISON '" << prefix+"_"+filename << "' '" << expected_file << "'" << std::endl;

  std::ifstream student_instr((prefix+"_"+filename).c_str());




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




  //std::ifstream expected_instr(expected_file.c_str());
  std::ifstream expected_instr(expected.c_str());

  std::string s = "";
  std::string e = "";

  if (student_instr) {
    std::cout << "STUDENT FILE EXISTS" << std::endl;
    s = std::string(std::istreambuf_iterator<char>(student_instr),
		    std::istreambuf_iterator<char>());
    std::cout << "student file size = " << s.size() << std::endl;
  }
  if (expected_instr) {
    std::cout << "EXPECTED FILE EXISTS" << std::endl;
    e = std::string(std::istreambuf_iterator<char>(expected_instr),
		    std::istreambuf_iterator<char>());
    std::cout << "expected file size = " << e.size() << std::endl;
  }




  if (s.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    std::cout << "ERROR: student file size too big " << s.size() << " > " << MYERS_DIFF_MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR!  Student file too large for grader<br>\n");
  }
  if (e.size() > MYERS_DIFF_MAX_FILE_SIZE) {
    std::cout << "ERROR: expected file size too big " << e.size() << " > " << MYERS_DIFF_MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR!  Instructor expected file too large for grader<br>\n");
  }


  std::cout << "GOING TO COMPARE studentsize=" << s.size() << "  expectedsize="<< e.size() << std::endl;

  //return test_case_grader[j]->cmp_output (s,e);
  return cmp_output (s,e);
}



TestResults* TestCaseTokens::doit(const std::string &prefix) {


  std::ifstream student_instr((prefix+"_"+filename).c_str());

  std::string s = "";

  if (student_instr) {
    std::cout << "STUDENT FILE EXISTS" << std::endl;
    s = std::string(std::istreambuf_iterator<char>(student_instr),
		    std::istreambuf_iterator<char>());
    std::cout << "student file size = " << s.size() << std::endl;
  }

  if (s.size() > OTHER_MAX_FILE_SIZE) {
    std::cout << "ERROR: student file size too big " << s.size() << " > " << OTHER_MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR! Student file too large for grader<br>\n");
  }

  //return test_case_grader[j]->cmp_output (s,e);
  return token_grader(s,tokens);
}




TestResults* TestCaseCustom::doit(const std::string &prefix) {


  std::ifstream student_instr((prefix+"_"+filename).c_str());

  std::string s = "";

  if (!student_instr) {
    std::cout << "ERROR: STUDENT FILE DOES NOT EXIST" << std::endl;
    return new TestResults(0,"ERROR! Student file does not exist<br>\n");
  }


  std::vector<std::string> argv;

  argv.push_back("MY_EXECUTABLE.out");

  std::stringstream ss(my_arg_string);
  std::string token;
  while (ss >> token) {
    argv.push_back(token);
  }


  std::stringstream output;
  float answer = custom_grader(student_instr,output,argv,*this); //my_display_mode);


  std::cout << "GRADE: " << answer << "\nOUTPUT:\n" << output.str() << std::endl;


  std::string tmp = output.str();
  std::string replaced;
  for (int i = 0; i < tmp.size(); i++) {
    if (tmp[i] != '\n') {
      replaced.push_back(tmp[i]);
    }
    else {
      replaced += "<br>\n";
    }
  }




  return new TestResults(answer,replaced);

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

