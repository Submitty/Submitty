#include <unistd.h>
#include "TestCase.h"

int TestCase::next_test_case_id = 1;


TestResults* TestCase::do_the_grading (int j, std::string &helper_message) {
  assert (j >= 0 && j < numFileGraders());

  bool ok_to_compare = true;

  // GET THE FILES READY
  std::ifstream student_file(filename(j).c_str());
  if (!student_file) {
    std::stringstream tmp;
    tmp << "ERROR: comparison " << j << ": Student's " << filename(j) << " does not exist";
    std::cerr << tmp.str() << std::endl;
    helper_message += tmp.str();
    ok_to_compare = false;
  } 

  std::string expected = "";
  if (test_case_grader[j] != NULL) {
    expected = test_case_grader[j]->getExpected();
  }

  std::ifstream expected_file(expected.c_str());
  if (!expected_file && expected != "") {
    std::stringstream tmp;
    tmp << "ERROR: comparison #" << j << ": Instructor's " + expected + " does not exist!";
    std::cerr << tmp.str() << std::endl;
    if (helper_message != "") helper_message += "<br>";
    helper_message += tmp.str();
    ok_to_compare = false;
  }
  return test_case_grader[j]->doit(prefix());
}


// FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff
#define MAX_FILE_SIZE 1000 * 50 // in characters  (approx 200 lines with 50 characters per line)



TestResults* TestCaseComparison::doit(const std::string &prefix) {


  std::cout << "IN DOIT FOR COMPARISON '" << prefix+"_"+filename << "' '" << expected_file << "'" << std::endl;

  std::ifstream student_instr((prefix+"_"+filename).c_str());
  std::ifstream expected_instr(expected_file.c_str());
  
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
  
  
  
  
  if (s.size() > MAX_FILE_SIZE) {
    std::cout << "ERROR: student file size too big " << s.size() << " > " << MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR: student file too large for grader");
  }
  if (e.size() > MAX_FILE_SIZE) {
    std::cout << "ERROR: expected file size too big " << e.size() << " > " << MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR: expected file too large for grader");
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
  
  if (s.size() > MAX_FILE_SIZE) {
    std::cout << "ERROR: student file size too big " << s.size() << " > " << MAX_FILE_SIZE << std::endl;
    return new TestResults(0,"ERROR: student file too large for grader");
  }
  
  //return test_case_grader[j]->cmp_output (s,e);




  return token_grader(s,tokens);
}




std::string getAssignmentIdFromCurrentDirectory() {
  char cCurrentPath[1000];
  if (!getcwd(cCurrentPath, 1000)) {
    std::cerr << "ERROR: couldn't get current directory" << std::endl;
    exit(0);
  }
  //printf ("The current working directory is '%s'\n", cCurrentPath);
  std::string tmp = cCurrentPath;

  assert (tmp.size() >= 1);
  assert (tmp[tmp.size()-1] != '/');

  while (1) {

    int loc = tmp.find('/');
    if (loc == std::string::npos) break;
    tmp = tmp.substr(loc+1,tmp.size()-loc-1);

    //std::cout << "tmp is now '" << tmp << "'\n";

  }
  assert (tmp.size() >= 1);
  return tmp;
}
