#include <cassert>
#include <unistd.h>

#include "dispatch.h"

#include "json.hpp"
#include "tokens.h"
#include "clean.h"

#include "execute.h"
#include "window_utils.h"

#include "tokenSearch.h"

#include "myersDiff.h"

std::vector<std::string> SplitOnComma(const std::string& in) {
  std::vector<std::string> answer;
  std::string tmp;
  for (int i = 0; i < in.size(); i++) {
    if (in[i]==',') {
      answer.push_back(tmp);
      tmp="";
    } else {
      tmp.push_back(in[i]);
    }
  }
  if (tmp != "") {
    answer.push_back(tmp);
  }
  return answer;
}

// implemented in execute.cpp
bool wildcard_match(const std::string &pattern, const std::string &thing);


void LineHighlight(std::stringstream &swap_difference, bool &first_diff, int student_line,
                   int expected_line, bool only_student, bool only_expected) {
  if (!first_diff) {
    swap_difference << "  ,\n";
  }
  using json = nlohmann::json;

  json j;
  j["actual"]["start"] = student_line;

  if (!only_expected) {
    json i;
    i["line_number"] = student_line;
    j["actual"]["line"] = { i };
  }

  std::cout << "LINE HIGHLIGHT " << expected_line << std::endl;
  j["expected"]["start"] = expected_line;

  if (!only_student) {
    json i;
    i["line_number"] = expected_line;
    j["expected"]["line"] = { i };
  }
  swap_difference << j.dump(4) << std::endl;
  first_diff = false;
}

bool JavaToolOptionsCheck(const std::string &student_file_contents) {
  std::stringstream ss(student_file_contents);
  std::string token;
  // "Picked up JAVA_TOOL_OPTIONS: -Xms128m -Xmx256m\n"
  if (!(ss >> token) || token != "Picked") return false;
  if (!(ss >> token) || token != "up") return false;
  if (!(ss >> token) || token != "JAVA_TOOL_OPTIONS:") return false;

  char c;
  if (!(ss >> c) || c != '-') return false;
  if (!(ss >> c) || c != 'X') return false;
  if (!(ss >> c) || c != 'm') return false;
  if (!(ss >> c) || c != 's') return false;

  int val;
  if (!(ss >> val)) return false;
  if (!(ss >> c) || c != 'm') return false;

  if (!(ss >> c) || c != '-') return false;
  if (!(ss >> c) || c != 'X') return false;
  if (!(ss >> c) || c != 'm') return false;
  if (!(ss >> c) || c != 'x') return false;

  if (!(ss >> val)) return false;
  if (!(ss >> c) || c != 'm') return false;

  // should be nothing else in the file
  if (ss >> token) return false;

  return true;
}





TestResults* dispatch::MultipleJUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!junit_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output does not exist")});
  }

  // look for version number on opening line
  std::string token1, token2, token3, token4, token5, token6;
  junit_output >> token1 >> token2 >> token3;
  if (token1 != "JUnit" || token2 != "version" || token3 != "4.12") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: TestRunner output format and/or version number incompatible with grader")});
  }

  while (junit_output >> token1) {

    // If OK, then all student tests pass, award full credit

    if (token1 == "TEST-RUNNER-OK") {
      char c;
      junit_output >> c;
      if (c != '(') {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      int num;
      junit_output >> num;
      if (num == 0) // No tests ran, awarding 0
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: No tests ran!")});
      junit_output >> token2;
      if (token2 != "tests)") {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      return new TestResults(1.0); // Awarding full credit
    }

    // If Failures, award partial credit

    else if (token1 == "TEST-RUNNER-FAILURES!!!") {
      // Parses the following: Tests run: 13, Failures: 13
      junit_output >> token2 >> token3;
      if (token2 != "Tests" || token3 != "run:") {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      int tests_run;
      junit_output >> tests_run;
      assert (tests_run >= 0);
      char comma;
      junit_output >> comma;
      if (comma != ',') {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      junit_output >> token4;
      if (token4 != "Failures:") {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      int tests_failed;
      junit_output >> tests_failed;
      assert (tests_failed > 0);
      if (tests_run == 0) { // Fixture creation failure (likely), award 0 credit
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: No tests ran. Could not create fixture.")});
      }

      int successful_tests = std::max(0,tests_run-tests_failed);
      std::cout << "SUCCESSFUL_TESTS = " << successful_tests << "  tests_run = " << tests_run << std::endl;
      float partial = float(successful_tests) / float(tests_run);
      std::stringstream ss;
      ss << "ERROR: JUnit testing has revealed an exception or other failure.  Successful tests = " << successful_tests << "/" << tests_run;

      std::cout << "JUNIT Multiple junit tests, partial = " << partial << std::endl;
      assert (partial >= 0.0 && partial <= 1.0);
      return new TestResults(partial,{std::make_pair(MESSAGE_FAILURE,ss.str())});
    }
  }

  std::cout << "ERROR: TestRunner output did not say 'TEST-RUNNER-OK' or 'TEST-RUNNER-FAILURES!!!'.  This should not happen!" << std::endl;
  return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: TestRunner output did not say 'TEST-RUNNER-OK' or 'TEST-RUNNER-FAILURES!!!'.  This should not happen!")});
}

// =============================================================================
// =============================================================================

TestResults* dispatch::JUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!junit_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output does not exist")});
  }

  int num_junit_tests = j.value("num_tests",1);

  // look for version number on opening line
  std::string token1, token2, token3;
  junit_output >> token1 >> token2 >> token3;
  if (token1 != "JUnit" || token2 != "version" || token3 != "4.12") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output format and/or version number incompatible with grader")});
  }

  bool ok = false;
  bool failure = false;
  bool exception = false;

  int tests_run = -1;
  int test_failures = -1;


  while (junit_output >> token1) {

    // if the word "OK" appears in the output, and the number of tests
    // matches the instructor configuration, then it is worth full credit
    if (token1 == "OK") {
      assert (ok == false);
      assert (failure == false && exception == false);
      char c;
      junit_output >> c;
      if (c != '(') {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: FORMATTING!")});
      }
      int num;
      junit_output >> num;
      if (num != num_junit_tests) {
        return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Number of tests specified in configuration does not match!")});
      }
      ok = true;
    }

    // look for problems in the output
    if (token1.find("Failure") != std::string::npos ||
        token1.find("failure") != std::string::npos) {
      assert (ok == false);
      failure = true;
    }
    if (token1.find("Exception") != std::string::npos) {
      assert (ok == false);
      exception = true;
    }

    // count the number of non failed tests run
    if (token1 == "Tests") {
      junit_output >> token1;
      if (token1 == "run:") {
        junit_output >> tests_run;
        assert (tests_run >= 0);
        char c;
        junit_output >> c;
        assert (c == ',');
        junit_output >> token1;
        assert (token1 == "Failures:");
        junit_output >> test_failures;
        assert (test_failures >= 0);
      }
    }
  }


  if (ok) {
    assert (!failure && !exception);
    return new TestResults(1.0); // Awarding full credit, no message
  }

  if (failure || exception) {
    if (tests_run > num_junit_tests) {
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Number of tests specified in configuration does not match!")});
    }
    std::cout << "tests_run " << tests_run << " test_failures " << test_failures << std::endl;
    if (test_failures == -1) {
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Failure to read number of test failures")});
    }
    assert (tests_run >= 0);
    assert (test_failures >= 0);
    // hmm, it appears that a test can fail before even starting to run(??)
    // so we cannot test this:
    //assert (tests_run >= test_failures);
    int successful_tests = std::max(0,tests_run-test_failures);
    float partial = float(successful_tests) / float(num_junit_tests);
    std::stringstream ss;
    ss << "ERROR: JUnit testing has revealed an exception or other failure.  Successful tests = " << successful_tests << "/" << num_junit_tests;
    return new TestResults(partial,{std::make_pair(MESSAGE_FAILURE,ss.str())});
  }

  std::cout << "ERROR: JUnit output did not say 'OK' or 'Failure' or 'Exception'.  This should not happen!" << std::endl;
  return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output did not say 'OK' or 'Failure' or 'Exception'.  This should not happen!")});
}

// =============================================================================

TestResults* dispatch::EmmaInstrumentationGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!junit_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output does not exist")});
  }

  // look for version number on opening line
  std::string token;

  //
  // NOTE: instrumentation will probably not be auto-graded (no points)
  //

  // look for the final line of the file that starts with "EMMA: metadata merged"
  while (junit_output >> token) {
    if (token == "EMMA:") {
      junit_output >> token;
      if (token == "metadata") {
        junit_output >> token;
        if (token == "merged") {
          return new TestResults(1.0); // Awarding full credit, no message
        }
      }
    }
  }

  return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit EMMA instrumentation not verified")});
}

// =============================================================================

TestResults* dispatch::EmmaCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!junit_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit output does not exist")});
  }

  float coverage_threshold = j.value("coverage_threshold",100);

  // look for version number on opening line
  std::string token1, token2;
  junit_output >> token1 >> token2;
  if (token1 != "[EMMA" || token2 != "v2.0.5312") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JUnit EMMA output format and/or version number incompatible with grader")});
  }

  // read the rest of the file, one line at a time.
  std::string line;
  bool breakdown_by_package = false;
  while (getline(junit_output,line)) {
    if (line == "COVERAGE BREAKDOWN BY PACKAGE:") {
      breakdown_by_package = true;
    } else if (breakdown_by_package) {
      if (line.find("[class, %]") != std::string::npos) { continue; }
      if (line.size() == 0) { continue; }
      if (line.find(".test")     != std::string::npos) { continue; }
      if (line.find("hw")        == std::string::npos) { continue; }

      // output looks something line this:

      // [class, %]      [method, %]     [block, %]      [line, %]       [name]
      // 83%  (5/6)!     88%  (23/26)    83%  (223/270)  80%  (53/66)    hw0
      // 100% (4/4)      97%  (32/33)    98%  (1104/1130)        97%  (195/202)  hw0.test

      std::stringstream ss(line);
      int class_p, method_p, block_p, line_p;
      char c;
      std::string tmp, name;
      ss >> class_p  >> c >> tmp; assert (c == '%');
      ss >> method_p >> c >> tmp; assert (c == '%');
      ss >> block_p  >> c >> tmp; assert (c == '%');
      ss >> line_p   >> c >> tmp; assert (c == '%');
      ss >> name;

      std::stringstream ss2;

      assert (coverage_threshold >= 0.0 && coverage_threshold <= 100.0);

      if (block_p >= coverage_threshold) {
        // && line_p >= coverage_threshold) {
        return new TestResults(1.0); // Awarding full credit, no message
      }

      else {
        // simple formula for partial credit based on coverage.
        // float partial = float(std::min(block_p,line_p)) / coverage_threshold;
        float partial = float(block_p) / coverage_threshold;
        ss2 << "ERROR: Insuffficient block coverage below threshold for... " << name
            << " (" << block_p << "/" << coverage_threshold << " = " << partial << ")";
        return new TestResults(partial,{std::make_pair(MESSAGE_FAILURE,ss2.str())});
      }
    }
  }

  return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Did not successfully parse EMMA output.")});
}

// =============================================================================
// =============================================================================

TestResults* dispatch::JaCoCoCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  float instruction_coverage_threshold = j.value("instruction_coverage_threshold",0);
  float branch_coverage_threshold = j.value("branch_coverage_threshold",0);
  float line_coverage_threshold = j.value("line_coverage_threshold",0);
  float complexity_coverage_threshold = j.value("complexity_coverage_threshold",0);
  float method_coverage_threshold = j.value("method_coverage_threshold",0);

  if (instruction_coverage_threshold <= 0.01 &&
      branch_coverage_threshold <= 0.01 &&
      line_coverage_threshold <= 0.01 &&
      complexity_coverage_threshold <= 0.01 &&
      method_coverage_threshold <= 0.01) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Must specify coverage threshold for instruction, branch, line, complexity, or method")});
  }

  std::string include_package = j.value("package", "*");
  std::string include_class = j.value("class", "*");
  std::string exclude_package = j.value("exclude_package", "");
  std::string exclude_class = j.value("exclude_class", "");

  std::string filename = j.value("actual_file","");

  // open the specified runtime Jacoco output/log file
  std::ifstream jacoco_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!jacoco_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: JaCoCo output does not exist")});
  }

  // look for the opening line
  std::string token;
  jacoco_output >> token;
  if (token != "GROUP,PACKAGE,CLASS,INSTRUCTION_MISSED,INSTRUCTION_COVERED,BRANCH_MISSED,BRANCH_COVERED,LINE_MISSED,LINE_COVERED,COMPLEXITY_MISSED,COMPLEXITY_COVERED,METHOD_MISSED,METHOD_COVERED") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Jacoco output format incompatible with grader")});
  }

  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > answer;

  float score = 1.0;
  int check_count = 0;

  // read the rest of the file, one line at a time.
  std::string line;
  while (getline(jacoco_output,line)) {
    std::vector<std::string> tokens = SplitOnComma(line);
    if (tokens.size() == 0) continue;
    if (tokens.size() != 13 || tokens[0] != "JaCoCo Coverage Report") {
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: incorrectly formatted JaCoCo data line: "+line)});
    }

    // skip the package if its not the one to check
    if ( !wildcard_match(include_package,tokens[1]) || wildcard_match(exclude_package,tokens[1]) ) continue;
    if ( !wildcard_match(include_class,  tokens[2]) || wildcard_match(exclude_class,  tokens[2]) ) continue;

    // parse the line
    int i_m = std::stoi(tokens[3]);
    int i_c = std::stoi(tokens[4]);
    int b_m = std::stoi(tokens[5]);
    int b_c = std::stoi(tokens[6]);
    int l_m = std::stoi(tokens[7]);
    int l_c = std::stoi(tokens[8]);
    int c_m = std::stoi(tokens[9]);
    int c_c = std::stoi(tokens[10]);
    int m_m = std::stoi(tokens[11]);
    int m_c = std::stoi(tokens[12]);
    // calculate the coverage
    float instruction_coverage = 100;
    float branch_coverage = 100;
    float line_coverage = 100;
    float complexity_coverage = 100;
    float method_coverage = 100;
    if (i_m+i_c > 0) instruction_coverage = 100 * i_c / float (i_c+i_m);
    if (b_m+b_c > 0) branch_coverage      = 100 * b_c / float (b_c+b_m);
    if (l_m+l_c > 0) line_coverage        = 100 * l_c / float (l_c+l_m);
    if (c_m+c_c > 0) complexity_coverage  = 100 * c_c / float (c_c+c_m);
    if (m_m+m_c > 0) method_coverage      = 100 * m_c / float (m_c+m_m);
    // print the coverage
    std::stringstream ss;
    ss << tokens[1] << " " << tokens[2] << " "
       << std::setw(4) << std::fixed << std::setprecision(1) << instruction_coverage << "% instruction, "
       << std::setw(4) << std::fixed << std::setprecision(1) << branch_coverage << "% branch, "
       << std::setw(4) << std::fixed << std::setprecision(1) << line_coverage << "% line, "
       << std::setw(4) << std::fixed << std::setprecision(1) << complexity_coverage << "% complexity, "
       << std::setw(4) << std::fixed << std::setprecision(1) << method_coverage << "% method";
    answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));

    // partial credit for missing the threshold(s)
    check_count++;
    if (instruction_coverage_threshold > 0.01 && instruction_coverage < instruction_coverage_threshold) {
      score *= instruction_coverage / float (instruction_coverage_threshold);
      std::stringstream ss;
      ss << std::fixed << std::setprecision(1) << instruction_coverage
         << "% < "
         << std::fixed << std::setprecision(1) << instruction_coverage_threshold
         << "% insufficient instruction coverage for " << tokens[1] << " " << tokens[2];
      answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));
    }
    if (branch_coverage_threshold > 0.01 && branch_coverage < branch_coverage_threshold) {
      score *= branch_coverage / float (branch_coverage_threshold);
      std::stringstream ss;
      ss << std::fixed << std::setprecision(1) << branch_coverage
         << "% < "
         << std::fixed << std::setprecision(1) << branch_coverage_threshold
         << "% insufficient branch coverage for " << tokens[1] << " " << tokens[2];
      answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));
    }
    if (line_coverage_threshold > 0.01 && line_coverage < line_coverage_threshold) {
      score *= line_coverage / float (line_coverage_threshold);
      std::stringstream ss;
      ss << std::fixed << std::setprecision(1) << line_coverage
         << "% < "
         << std::fixed << std::setprecision(1) << line_coverage_threshold
         << "% insufficient line coverage for " << tokens[1] << " " << tokens[2];
      answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));
    }
    if (complexity_coverage_threshold > 0.01 && complexity_coverage < complexity_coverage_threshold) {
      score *= complexity_coverage / float (complexity_coverage_threshold);
      std::stringstream ss;
      ss << std::fixed << std::setprecision(1) << complexity_coverage
         << "% < "
         << std::fixed << std::setprecision(1) << complexity_coverage_threshold
         << "% insufficient complexity coverage for " << tokens[1] << " " << tokens[2];
      answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));
    }
    if (method_coverage_threshold > 0.01 && method_coverage < method_coverage_threshold) {
      score *= method_coverage / float (method_coverage_threshold);
      std::stringstream ss;
      ss << std::fixed << std::setprecision(1) << method_coverage
         << "% < "
         << std::fixed << std::setprecision(1) << method_coverage_threshold
         << "% insufficient method coverage for " << tokens[1] << " " << tokens[2];
      answer.push_back(std::make_pair(MESSAGE_FAILURE,ss.str()));
    }
  }

  if (check_count==0) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,
                                               "ERROR: Nothing matched the package="+include_package+
                                               " but not package="+exclude_package+
                                               " and class="+include_class+
                                               " but not class="+exclude_class)});
  }

  return new TestResults(score,answer);
}

// =============================================================================
// =============================================================================





// =============================================================================
// =============================================================================

TestResults* dispatch::DrMemoryGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  // open the specified runtime DrMemory output/log file
  std::string filename = j.value("actual_file","");
  std::ifstream drmemory_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!drmemory_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: DrMemory output does not exist")});
  }

  std::vector<std::vector<std::string> > file_contents;
  std::string line;
  while (getline(drmemory_output,line)) {
    file_contents.push_back(std::vector<std::string>());
    std::stringstream ss(line);
    std::string token;
    while(ss >> token) {
      file_contents.back().push_back(token);
    }
  }

  int num_errors = 0;
  bool errors_message = false;
  bool no_errors_message = false;
  int zero_unique_errors = 0;
  bool non_zero_unique_errors = false;

  for (int i = 0; i < file_contents.size(); i++) {
    if (file_contents[i].size() >= 3 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "Error" &&
        file_contents[i][2][0] == '#') {
      num_errors++;
    }
    if (file_contents[i].size() == 4 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "ERRORS" &&
        file_contents[i][2] == "FOUND") {
      errors_message = true;
    }
    if (file_contents[i].size() == 4 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "NO" &&
        file_contents[i][2] == "ERRORS" &&
        file_contents[i][3] == "FOUND:") {
      no_errors_message = true;
    }

    if (file_contents[i].size() >= 3 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][2] == "unique,") {
      if (file_contents[i][1] == "0") {
        zero_unique_errors++;
      } else {
        non_zero_unique_errors = true;
      }
    }
  }

  float result = 1.0;
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;

  if (num_errors > 0) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,std::to_string(num_errors) + " DrMemory Errors"));
    result = 0;
  }
  if (result > 0.01 &&
      (no_errors_message == false ||
       non_zero_unique_errors == true ||
       zero_unique_errors != 6)) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"Program Contains Memory Errors"));
    result = 0;
  }
  if (no_errors_message == true &&
      result < 0.99) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"Your Program *does* contains memory errors (misleading DrMemory Output \"NO ERRORS FOUND\")"));
  }


  return new TestResults(result,messages);
}

// =============================================================================





// =============================================================================
// =============================================================================

TestResults* dispatch::PacmanGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  // open the specified runtime Pacman output/log file
  std::string filename = j.value("actual_file","");
  std::ifstream pacman_output((tc.getPrefix()+filename).c_str());

  // check to see if the file was opened successfully
  if (!pacman_output.good()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Pacman output does not exist")});
  }

  // instructor must provided correct expected number of tests
  int num_pacman_tests = j.value("num_tests",-1);
  if (num_pacman_tests <= 0) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"CONFIGURATION ERROR: Must specify number of Pacman tests")});
  }

  // store the points information
  std::vector<int> awarded(num_pacman_tests,-1);
  std::vector<int> possible(num_pacman_tests,-1);
  int total_awarded = -1;
  int total_possible = -1;

  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string line;
  while (getline(pacman_output,line)) {
    std::stringstream line_ss(line);
    std::string word;
    while (line_ss >> word) {
      if (word == "###") {
        // parse each question score
        line_ss >> word;
        if (word == "Question") {
          line_ss >> word;
          int which = atoi(word.substr(1,word.size()-1).c_str())-1;
          if (num_pacman_tests < 0 || which >= num_pacman_tests) {
            messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Invalid question number " + word));
            return new TestResults(0.0,messages);
          }
          char c;
          line_ss >> awarded[which] >> c >> possible[which];
          if (awarded[which] < 0 ||
              c != '/' ||
              possible[which] <= 0 ||
              awarded[which] > possible[which]) {
            messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Could not parse question points"));
            return new TestResults(0.0,messages);
          }
        }
      } else if (word == "Total:") {
        // parse the total points
        char c;
        line_ss >> total_awarded >> c >> total_possible;
        if (total_awarded < 0 ||
            c != '/' ||
            total_possible <= 0 ||
            total_awarded > total_possible) {
          messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Could not parse total points"));
          return new TestResults(0.0,messages);
        }
      }
    }
  }

  // error checking
  int check_awarded = 0;
  int check_possible = 0;
  for (int i = 0; i < num_pacman_tests; i++) {
    if (awarded[i] < 0 ||
        possible[i] < 0) {
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Missing question " + std::to_string(i+1)));
    } else {
      check_awarded += awarded[i];
      check_possible += possible[i];
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"Question " + std::to_string(i+1) + ": "
       + std::to_string(awarded[i]) + " / "
       + std::to_string(possible[i])));
    }
  }
  if (total_possible == -1 ||
      total_awarded == -1) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Could not parse total points"));
    return new TestResults(0.0,messages);
  }
  if (total_possible != check_possible ||
      total_awarded != check_awarded) {
    messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: Summation of parsed points does not match"));
    return new TestResults(0.0,messages);
  }

  // final answer
  messages.push_back(std::make_pair(MESSAGE_FAILURE,"Total: " + std::to_string(total_awarded) + " / " + std::to_string(total_possible)));
  return new TestResults(float(total_awarded) / float(total_possible),messages);
}

// =============================================================================



/* METHOD: searchToken
 * ARGS: student: string containing student output, token: vector of strings that
 * is based of off the student output
 * RETURN: TestResults*
 * PURPOSE: Looks for a token specified in the second argument in the
 * student output. The algorithm runs in linear time with respect to the
 * length of the student output and preprocessing for the algorithm is
 * linear with respect to the token. Overall, the algorithm runs in O(N + M)
 * time where N is the length of the student and M is the length of the token.
 */
TestResults* dispatch::searchToken_doit (const TestCase &tc, const nlohmann::json& j) {

  std::vector<std::string> token_vec;
  nlohmann::json::const_iterator data_json = j.find("data");
  if (data_json != j.end()) {
   for (int i = 0; i < data_json->size(); i++) {
     token_vec.push_back((*data_json)[i]);
   }
  }

  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }


  //Build a table to use for the search
  Tokens* diff = new Tokens();
  diff->num_tokens = token_vec.size();
  assert (diff->num_tokens > 0);

  int found = 0;

  for (int which = 0; which < diff->num_tokens; which++) {
    int V[token_vec[which].size()];
    buildTable( V, token_vec[which] );
    std::cout << "searching for " << token_vec[which] << std::endl;
    int m = 0;
    int i = 0;
    while ( m + i < student_file_contents.size() ) {
      if ( student_file_contents[i + m] == token_vec[which][i] ) {
        if ( i == token_vec[which].size() - 1 ) {
          diff->tokens_found.push_back( m );
          std::cout << "found! " << std::endl;
          found++;
          break;
        }
        i++;
      } else {
        m += i - V[i];
        if ( V[i] == -1 )
          i = 0;
        else
          i = V[i];
      }
    }
    diff->tokens_found.push_back( -1 );
  }

  assert (found <= diff->num_tokens);

  diff->setGrade(found / float(diff->num_tokens));
  return diff;
}


TestResults* dispatch::intComparison_doit (const TestCase &tc, const nlohmann::json& j) {
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




// ==============================================================================
// ==============================================================================

TestResults* dispatch::fileExists_doit (const TestCase &tc, const nlohmann::json& j) {

  // grab the required files
  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"actual_file");
  if (filenames.size() == 0) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: no required files specified")});
  }
  for (int f = 0; f < filenames.size(); f++) {
    if (!tc.isCompilation()) {
      //filenames[f] = tc.getPrefix() + filenames[f];
      //filenames[f] = tc.getPrefix() + filenames[f];
      //filenames[f] = replace_slash_with_double_underscore(filenames[f]);
    }
  }

  // is it required to have all of these files or just one of these files?
  bool one_of = j.value("one_of",false);

  // loop over all of the listed files
  int found_count = 0;
  std::string files_not_found;
  for (int f = 0; f < filenames.size(); f++) {
    std::cout << "  file exists check: '" << filenames[f] << "' : ";
    std::vector<std::string> files;
    wildcard_expansion(files, filenames[f], std::cout);
    wildcard_expansion(files, tc.getPrefix() + filenames[f], std::cout);
    bool found = false;
    // loop over the available files
    for (int i = 0; i < files.size(); i++) {
      std::cout << "FILE CANDIDATE: " << files[i] << std::endl;
      if (access( files[i].c_str(), F_OK|R_OK ) != -1) { // file exists
        std::cout << "FOUND '" << files[i] << "'" << std::endl;
        found = true;
      } else {
        std::cout << "OOPS, does not exist: " << files[i] << std::endl;
      }
    }
    if (found) {
      found_count++;
    } else {
      files_not_found += " " + filenames[f];
    }
  }

  // the answer
  if (one_of) {
    if (found_count > 0) {
      return new TestResults(1.0);
    } else {
      std::cout << "FILE NOT FOUND " + files_not_found << std::endl;
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: required file not found: " + files_not_found)});
    }
  } else {
    if (found_count == filenames.size()) {
      return new TestResults(1.0);
    } else {
      std::cout << "FILES NOT FOUND " + files_not_found << std::endl;
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: required files not found: " + files_not_found)});
    }
  }
}

TestResults* dispatch::warnIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::cout << "WARNING IF NOT EMPTY DO IT" << std::endl;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(1.0,messages);
  }
  if (student_file_contents != "") {
    if (j.find("jvm_memory") != j.end() && j["jvm_memory"] == true &&
        JavaToolOptionsCheck(student_file_contents)) {
      return new TestResults(1.0);
    }
    return new TestResults(1.0,{std::make_pair(MESSAGE_WARNING,"WARNING: This file should be empty")});
  }
  return new TestResults(1.0);
}

TestResults* dispatch::errorIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }

  // FIXME: this logic was the right idea, but since we don't
  // automatically add the error version, the jvm_memory flag is not
  // being inserted.  I don't want to make the instructor add this
  // flag manually when they manually insert this validation check.
  // Checking for this flag is not strictly necessary, but we should
  // revisit this in the upcoming refactor.
  
  if (//j.find("jvm_memory") != j.end() && j["jvm_memory"] == true &&
      JavaToolOptionsCheck(student_file_contents)) {
    return new TestResults(1.0);
  }
  if (student_file_contents != "") {
    if (student_file_contents.find("error") != std::string::npos)
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
    else if (student_file_contents.find("warning") != std::string::npos)
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
    else
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
  }
  return new TestResults(1.0);
}

TestResults* dispatch::warnIfEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(1.0,messages);
  }
  if (student_file_contents == "") {
    return new TestResults(1.0,{std::make_pair(MESSAGE_WARNING,"WARNING: This file should not be empty")});
  }
  return new TestResults(1.0);
}

TestResults* dispatch::errorIfEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }
  if (student_file_contents == "") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should not be empty!")});
  }
  return new TestResults(1.0);
}

// ==============================================================================
// ==============================================================================
/**
* Used by custom_doit to retrieve message status pairs from a custom validator's result.json
*/
std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string>> dispatch::getAllCustomValidatorMessages(const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string>> messages;
  
  if (j["data"].find("message") != j["data"].end() && j["data"]["message"].is_string()) {
    messages.push_back(dispatch::getCustomValidatorMessage(j["data"]));
  }
  else if (j["data"].find("message") != j["data"].end() && j["data"]["message"].is_array()){
    for(typename nlohmann::json::const_iterator itr = j["data"]["message"].begin(); itr != j["data"]["message"].end(); itr++) {
      messages.push_back(dispatch::getCustomValidatorMessage(*itr));
    }
  }
  return messages;
}

/**
* Gets a message/status pair from a json object. 
* On failure returns the empty string for the message, and MESSAGE_INFORMATION for status and prints errors to stdout.
*/
std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> dispatch::getCustomValidatorMessage(const nlohmann::json& j) {
  std::string message = "";
  std::string status_string = "";
  TEST_RESULTS_MESSAGE_TYPE status = MESSAGE_INFORMATION;

  // If message is a string, then it has an associated status at this level.
  if(j.find("message") != j.end() && j["message"].is_string()){
      message = j["message"];
  }else{
    std::cout << "Message was not a string or was not found." << std::endl;
  }

  if(j.find("status") != j.end() && j["status"].is_string()){
    status_string = j["status"];
  }else{
    std::cout << "Status was not a string or was not found." << std::endl;
  }

  if(status_string == "failure"){
    status = MESSAGE_FAILURE;
  }else if(status_string == "warning"){
    status = MESSAGE_WARNING;
  }else if(status_string == "success"){
    status = MESSAGE_SUCCESS;
  }//else it stays information.

  return std::make_pair(status, message);
}

TestResults* dispatch::custom_doit(const TestCase &tc, const nlohmann::json& j, const nlohmann::json& whole_config, const std::string& username, int autocheck_number) {

  std::string command = j["command"];
  std::vector<nlohmann::json> actions;
  std::vector<nlohmann::json> dispatcher_actions;
  std::string execute_logfile = "/dev/null";
  nlohmann::json test_case_limits = tc.get_test_case_limits();
  nlohmann::json assignment_limits = j.value("resource_limits",nlohmann::json());
  bool windowed = false;
  std::string validator_stdout_filename = "validation_stdout.json";
  std::string validator_error_filename = "validation_stderr.txt";
  std::string validator_log_filename    = "validation_logfile.txt";
  std::string validator_json_filename   = "validation_results.json";
  std::string final_validator_log_filename    = "validation_logfile_" + std::to_string(tc.getID()) + "_" + std::to_string(autocheck_number) + ".txt";
  std::string final_validator_error_filename    = "validation_stderr_" + std::to_string(tc.getID()) + "_" + std::to_string(autocheck_number) + ".txt";
  std::string final_validator_json_filename   = "validation_results_" + std::to_string(tc.getID()) + "_" + std::to_string(autocheck_number) + ".json";
  std::string input_file_name           = "custom_validator_input.json";



  //Add the testcase prefix to j for use by the validator.
  nlohmann::json copy_j = j;
  copy_j["testcase_prefix"] = tc.getPrefix();
  // Provide the student's username for customized grading.
  copy_j["username"] = username;
  //Write out this validator config for use by the custom validator
  std::ofstream input_file(input_file_name);
  input_file << copy_j;
  input_file.close();

  command = command + " 1>" + validator_stdout_filename + " 2>" + validator_error_filename;
  int ret = execute(command,
                    actions, dispatcher_actions, execute_logfile, test_case_limits,
                    assignment_limits, whole_config, windowed, "NOT_A_WINDOWED_ASSIGNMENT",
                    tc.has_timestamped_stdout());
  std::remove(input_file_name.c_str());

  std::ifstream validator_json(validator_json_filename);
  // If we cannot use the validator.json (it doesn't exist), use the stdout.json instead.
  if(!validator_json.good()){
    std::ifstream stdout_reader(validator_stdout_filename);
    // If we can open the stdout file, archive it.
    if(stdout_reader.good()){
      std::ofstream dest( final_validator_json_filename, std::ios::binary );
      dest << stdout_reader.rdbuf();
      dest.close();
    }
    stdout_reader.close();
  } 
  // If we can use the validator json, archive it.
  else {
    std::ofstream dest( final_validator_json_filename, std::ios::binary );
    dest << validator_json.rdbuf();
    dest.close();
  }
  validator_json.close();

  // If the validator log exists, archive it.
  std::ifstream validator_log(validator_log_filename);
  if(validator_log.good()) {
    std::ofstream dest( final_validator_log_filename, std::ios::binary );
    dest << validator_log.rdbuf();
    dest.close();
  }
  validator_log.close();

  // If the validator error file exists, archive it.
  std::ifstream validator_error(validator_error_filename);
  if(validator_error.good()) {
    std::ofstream dest( final_validator_error_filename, std::ios::binary );
    dest << validator_error.rdbuf();
    dest.close();
  }
  validator_error.close();

  // Now that the files have been copied to permanent positions/loaded into memory, delete them.
  std::remove(validator_stdout_filename.c_str());
  std::remove(validator_log_filename.c_str());
  std::remove(validator_json_filename.c_str());
  std::remove(validator_error_filename.c_str());

  std::ifstream ifs(final_validator_json_filename);

  if(!ifs.good()){
    std::cout << "ERROR: Could not open the JSON output by the validator." <<std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Custom validation did not return a result.")});
  }

  nlohmann::json result;

  try{
    result = nlohmann::json::parse(ifs);
  }catch(const std::exception& e){
    std::cout << "ERROR: Could not parse the custom validator's output." << std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Could not parse a custom validator's output.")});
  }

  std::string validator_status = "fail";
  if(result.find("status") != result.end() && result["status"].is_string()){
    if(result["status"] == "success"){
      validator_status = "success";
    }
  }else{
    std::cout << "ERROR: A custom validator did not return success or failure" << std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: A custom validator did not return success or failure")});
  }

  if(validator_status != "success"){
    std::string error_message = "";
    if(result.find("message") != result.end() && result["message"].is_string()){
      error_message = result["message"];
    }
    //logs to validator_log
    std::cout << error_message << std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Custom validation failed.")});
  }

  if(result.find("data") == result.end() || !result["data"].is_object()){
    std::cout << "ERROR: The custom validator did not return a 'data' subdictionary." << std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: A custom validator did not return a result.")});
  }
  
  if(result["data"].find("score") == result["data"].end() || !result["data"]["score"].is_number()){
    std::cout << "ERROR: A custom validator must return score as a number between 0 and 1" << std::endl;
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: A custom validator did not return a score.")});
  }
  float score = result["data"]["score"];

  // Clamp the score between 0 and 1
  if(score > 1){
    score = 1;
  }
  else if(score < 0){
    score = 0;
  }

  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string>> messages = dispatch::getAllCustomValidatorMessages(result);
  
  return new TestResults(score, messages);
}

// ==============================================================================
// ==============================================================================

TestResults* dispatch::ImageDiff_doit(const TestCase &tc, const nlohmann::json& j, int autocheck_number) {
  std::string actual_file = j.value("actual_file","");
  std::string expected_file = "test_output/" + j.value("expected_file","");
  std::string acceptable_threshold_str = j.value("acceptable_threshold","");

  if(actual_file == "" || expected_file == "" || acceptable_threshold_str == ""){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Error in configuration. Please speak to your instructor.")});
  }

  if (access(expected_file.c_str(), F_OK|R_OK ) == -1)
  {
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: The instructor's image was not found. Please notify your instructor")});
  }

  float acceptable_threshold = stringToFloat(acceptable_threshold_str,6); //window_utils function.


  actual_file = tc.getPrefix() + actual_file;
  std::cout << "About to compare " << actual_file << " and " << expected_file << std::endl;

  //Check existence. File is closed by destructor.
  std::ifstream img_file_actual(actual_file);
  if(!img_file_actual.good()){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "Image comparison failed; student file does not exist.")});
  }

  //Check existence. File is closed by destructor.
  std::ifstream img_file_expected(expected_file);
  if(!img_file_expected.good()){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "Image comparison failed; expected file does not exist.")});
  }

  // Before we compare, make certain that the images are the same size.
  std::string size_command_actual = "identify -ping -format '%w %h' " + actual_file;
  std::string size_command_expected = "identify -ping -format '%w %h' " + expected_file;

  std::string actual_size_output = output_of_system_command(size_command_actual.c_str());
  std::string expected_size_output = output_of_system_command(size_command_expected.c_str());

  if(actual_size_output != expected_size_output){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "Image comparison failed; Images are not of the same size.")});
  }

  std::string command = "compare -metric RMSE " + actual_file + " " + expected_file + " NULL: 2>&1";
  std::string output = output_of_system_command(command.c_str()); //get the string
  std::cout << "captured the following:\n" << output << "\n" <<std::endl;

  std::istringstream buffer(output);
  //Split on whitespace.
  std::vector<std::string> strings(std::istream_iterator<std::string>{buffer}, std::istream_iterator<std::string>());

  if(strings.size() < 2){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "Image comparison failed; Images are incomparable.")});
  }

  std::string difference_str = strings[1];
  std::cout << "The images had a difference of " << difference_str << std::endl;

  //Remove start and end parens.
  if(difference_str.front() == '('){
    difference_str.erase(0,1);
  }

  if(difference_str.back() == ')'){
    difference_str.pop_back();
  }

  //convert resulting string to double.
  std::string::size_type sz;
  double difference = std::stod(difference_str, &sz);
  double similarity = 1 - difference;

  std::string diff_file_name = tc.getPrefix() + std::to_string(autocheck_number) + "_difference.png";

  std::cout << "About to compose the images." << std::endl;
  std::string command2 = "compare " + actual_file + " " + expected_file + " -fuzz 10% -highlight-color red -lowlight-color none -compose src " + diff_file_name;
  system(command2.c_str());
  std::cout << "Composed." <<std::endl;

  if(difference >= acceptable_threshold){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Your image does not match the instructor's.")});
  }
   else{
    // MESSAGE_NONE, MESSAGE_FAILURE, MESSAGE_WARNING, MESSAGE_SUCCESS, MESSAGE_INFORMATION
         return new TestResults(1.0, {std::make_pair(MESSAGE_INFORMATION, "SUCCESS: Your image was close enough to your instructor's!")});
  }


  //   return new TestResults(0.0, {"ERROR: File comparison failed."});

}

// ==============================================================================
// ==============================================================================

// FIXME: might be nice to highlight small errors on a line
TestResults* dispatch::diffLineSwapOk_doit (const nlohmann::json& j,const std::string &student_file_contents,
                                  const std::string &expected_file_contents) {

  // break each file (at the newlines) into vectors of strings
  vectorOfLines student = stringToLines( student_file_contents, j );
  vectorOfLines expected = stringToLines( expected_file_contents, j );

  // check for an empty solution file
  if (expected.size() < 1) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR!  expected file is empty")});
  }
  assert (expected.size() > 0);

  // where we will temporarily write the line highlighting json file.
  // FIXME: currently coloring all problems the same color... could
  // extend to specify a difference color for incorrect vs. missing
  // vs. duplicate
  std::stringstream swap_difference;
  swap_difference << "{\n";
  swap_difference << "\"differences\":[\n";
  bool first_diff = true;
  ;
  // counts of problems between the student & expected file
  int incorrect = 0;
  int duplicates = 0;
  int missing = 0;

  // for each line of the expected file, count the number of lines in
  // the student file that match it.
  std::vector<int> matches(expected.size(),0);

  // walk through the student file, trying to find a unique match in
  // the expected file.
  // FIXME: Currently assuming all lines in the expected file are
  // unique...  could make this more sophisticated.
  for (unsigned int i = 0; i < student.size(); i++) {
    bool match = false;
    bool duplicate = false;
    for (unsigned int j = 0; j < expected.size(); j++) {
      if (student[i] == expected[j]) {
        if (matches[j] > 0) duplicate = true;
        matches[j]++;
        match = true;
        break;
      }
    }
    if (!match) {
      incorrect++;
    }
    if (!match || duplicate) {
      std::cout << "!match or duplicate" <<std::endl;
      LineHighlight(swap_difference,first_diff,i,expected.size()+10,true,false);
      //LineHighlight(swap_difference,first_diff,i,0,true,false);
    }
  }

  // count the number of missing lines
  for (unsigned int j = 0; j < expected.size(); j++) {
    if (matches[j] == 0) {
      missing++;
      std::cout << "missing" <<std::endl;
      LineHighlight(swap_difference,first_diff,student.size()+10,j,false,true);
      //LineHighlight(swap_difference,first_diff,0,j,false,true);
    }
    if (matches[j] > 1) duplicates+= (matches[j]-1);
  }
  swap_difference << "]\n";
  swap_difference << "}\n";

  // calculate the score
  int wrong = std::max(missing,duplicates+incorrect);
  double score = double(int(expected.size())-wrong)/double(expected.size());
  score = std::max(0.0,score);

  // prepare the graded message for the student
  std::stringstream ss;
  if (incorrect > 0) {
    ss << "ERROR: " << incorrect << " incorrect line(s)";
  }
  if (duplicates > 0) {
    if (ss.str().size() > 0) {
      ss << ", ";
    }
    ss << "ERROR: " << duplicates << " duplicate line(s)";
  }
  if (missing > 0) {
    if (ss.str().size() > 0) {
      ss << ", ";
    }
    ss << "ERROR: " << missing << " missing line(s)";
  }

  return new TestResults(score,{std::make_pair(MESSAGE_FAILURE,ss.str())},swap_difference.str());
}

// ===================================================================



TestResults* dispatch::diff_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  std::string expected_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }
  if (!openExpectedFile(tc,j,expected_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }
  if (student_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE_MODERATE &&
      student_file_contents.size() > 10* expected_file_contents.size()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Student file too large for grader")});
  }

  TestResults* answer = NULL;
  std::string comparison = j.value("comparison","byLinebyChar");
  bool ignoreWhitespace = j.value("ignoreWhitespace",false);
  bool lineSwapOk = j.value("lineSwapOk",false);
  if (comparison == std::string("byLinebyChar")) {
    bool extraStudentOutputOk = j.value("extra_student_output",false);
    vectorOfLines text_a = stringToLines( student_file_contents, j );
    vectorOfLines text_b = stringToLines( expected_file_contents, j );
    answer = ses(j, &text_a, &text_b, true, extraStudentOutputOk );
    ((Difference*)answer)->type = ByLineByChar;
  } else if (comparison == std::string("byLine")) {
    if (lineSwapOk) {
      answer = diffLineSwapOk_doit(j,student_file_contents,expected_file_contents);
    } else if (ignoreWhitespace) {
      vectorOfWords text_a = stringToWordsLimitLineLength( student_file_contents );
      vectorOfWords text_b = stringToWordsLimitLineLength( expected_file_contents );
      answer = ses(j, &text_a, &text_b, false );
      ((Difference*)answer)->type = ByLineByChar;
    } else {
      vectorOfLines text_a = stringToLines( student_file_contents, j );
      vectorOfLines text_b = stringToLines( expected_file_contents, j );
      bool extraStudentOutputOk = j.value("extra_student_output",false);
      answer = ses(j, &text_a, &text_b, false,extraStudentOutputOk);
      ((Difference*)answer)->type = ByLineByChar;
    }
  } else {
    std::cout << "ERROR!  UNKNOWN COMPARISON" << comparison << std::endl;
    std::cerr << "ERROR!  UNKNOWN COMPARISON" << comparison << std::endl;
    answer = new TestResults(0.0);
  }
  assert (answer != NULL);
  return answer;
}
