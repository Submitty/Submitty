#include <cassert>
#include <unistd.h>
#include "JUnitGrader.h"

// =============================================================================
// =============================================================================

/*
This method parses the output of TestRunner. Output file format is one of the following:

FAILURE:

JUnit version 4.12
EMMA: collecting runtime coverage data ...
fkjdkfjd
TEST-RUNNER-FAILURES!!!
Tests run: 13, Failures: 13
EMMA: runtime coverage data merged into [/Users/ana/Downloads/java/coverage.ec] {in 2 ms}

or, if code is not instrumented

JUnit version 4.12
kfjdkfdj
TEST-RUNNER-FAILURES!!!
Tests run: 13, Failures: 13

SUCCESS:

JUnit version 4.12
EMMA: collecting runtime coverage data ...
TEST-RUNNER-OK (65 tests)
EMMA: runtime coverage data merged into [/Users/ana/Downloads/java/coverage.ec] {in 46 ms}

or, if code is not instrumented

JUnit version 4.12
TEST-RUNNER-OK (65 tests)

-------------------------------------

All other output is exceptional, will be garded 0

*/

TestResults* MultipleJUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+"_"+filename).c_str());

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



TestResults* JUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+"_"+filename).c_str());

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


TestResults* EmmaInstrumentationGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+"_"+filename).c_str());

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

TestResults* EmmaCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  std::string filename = j.value("actual_file","");

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((tc.getPrefix()+"_"+filename).c_str());

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

TestResults* JaCoCoCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j) {

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

  std::string which_package = j.value("package", "");
  std::string which_class = j.value("class", "");

  std::string filename = j.value("actual_file","");

  // open the specified runtime Jacoco output/log file
  std::ifstream jacoco_output((tc.getPrefix()+"_"+filename).c_str());

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
    if (which_package != "" && which_package != tokens[1]) continue;
    if (which_class != "" && which_class != tokens[2]) continue;

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
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Nothing matched the package="+which_package+" and class="+which_class)});
  }

  return new TestResults(score,answer);
}

// =============================================================================
// =============================================================================
