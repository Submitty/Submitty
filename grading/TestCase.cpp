#include <unistd.h>
#include "TestCase.h"
//#include "config.h"

/*

float custom_grade(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv);


// this isn't needed by all homeworks...  needs a rethink
float custom_grade(std::istream &INPUT, std::ostream &OUTPUT,  std::vector<std::string> &argv) { return 0.0; }

*/

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


TestResults* TestCaseCustom::doit(const std::string &prefix) {


  std::ifstream student_instr((prefix+"_"+filename).c_str());

  std::string s = "";

  if (!student_instr) {
    std::cout << "STUDENT FILE DOES NOT EXIST" << std::endl;
    return new TestResults(0,"ERROR: student file does not exist");
  }


  std::vector<std::string> argv;

  argv.push_back("MY_EXECUTABLE.out");

  std::stringstream ss(my_arg_string);
  std::string token;
  while (ss >> token) {
    argv.push_back(token);
  }


  std::stringstream output;
  float answer = custom_grader(student_instr,output,argv);


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

  std::string tmp2 = dir;

  assert (tmp2.size() >= 1);
  // assert (tmp2[tmp2.size()-1] != '/');


  int loc = tmp2.find('/');
  if (loc != std::string::npos){
      tmp2 = tmp2.substr(0,loc);
  }

  std::cout << "tmp2 is now '" << tmp2 << "'\n";
  loc = tmp2.find('_');
  if (loc != std::string::npos){
      tmp2 = tmp2.substr(loc+1);
  }
  std::cout << "tmp2 is now '" << tmp2 << "'\n";

  loc = tmp2.find('_');
  if (loc != std::string::npos){
      tmp2 = tmp2.substr(0,loc);
  }

  std::cout << "tmp2 is now '" << tmp2 << "'\n";

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
    std::cout << "tmp is now '" << tmp << "'\n";  

  }
  assert (tmp.size() > 1);
  return tmp;
}
/*


std::string getAssignmentIdFromCurrentDirectory(std::string dir) {

std::string tmp2 = dir;

assert (tmp2.size() >= 1);
// assert (tmp2[tmp2.size()-1] != '/');


int loc = tmp2.find('/');
if (loc != std::string::npos){
tmp2 = tmp2.substr(0,loc);
}

std::cout << "tmp2 is now '" << tmp2 << "'\n";
loc = tmp2.find('_');
if (loc != std::string::npos){
tmp2 = tmp2.substr(loc+1);
}
std::cout << "tmp2 is now '" << tmp2 << "'\n";

loc = tmp2.find('_');
if (loc != std::string::npos){
tmp2 = tmp2.substr(0,loc);
}

std::cout << "tmp2 is now '" << tmp2 << "'\n";


// }
assert (tmp2.size() >= 1);
return tmp2;
}
*/
