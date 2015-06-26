#include <cassert>
#include <unistd.h>
#include "TestCase.h"
#include "JUnitGrader.h"


// =============================================================================
// =============================================================================

TestResults* TestCaseJUnit::doit(const std::string &prefix) {

  // open the specified runtime JUnit output/log file
  std::ifstream junit_output((prefix+"_"+filename).c_str());
 
  // check to see if the file was opened successfully
  if (!junit_output.good()) {
    return new TestResults(0.0,"ERROR: JUnit output does not exist");
  }

  // dispatch to the function that does the work
  if (junit_grader_type == "JUNIT_TEST") {
    return doit_junit_test(junit_output);
  } else if (junit_grader_type == "EMMA_INSTRUMENTATION") {
    return doit_emma_instrumentation(junit_output);
  } else if (junit_grader_type == "EMMA_COVERAGE_REPORT") {
    return doit_emma_coverage_report(junit_output);
  } else {
    return new TestResults(0.0,"ERROR: UNKNOWN JUNIT TEST GRADER TYPE: "+junit_grader_type);
  }
}

// =============================================================================
// =============================================================================

TestResults* TestCaseJUnit::doit_junit_test(std::ifstream &junit_output) {
  // look for version number on opening line
  std::string token1, token2, token3;
  junit_output >> token1 >> token2 >> token3;
  if (token1 != "JUnit" || token2 != "version" || token3 != "4.12") {
    return new TestResults(0.0,"ERROR: JUnit output format and/or version number incompatible with grader");
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
	return new TestResults(0.0,"ERROR: FORMATTING!");
      }
      int num;
      junit_output >> num;
      if (num != num_junit_tests) {
	return new TestResults(0.0,"ERROR: Number of tests specified in configuration does not match!"); 
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
      }
    }
  }
   

  if (ok) {
    assert (!failure && !exception);
    return new TestResults(1.0,""); // Awarding full credit, no message
  }

  if (failure || exception) {
    if (tests_run > num_junit_tests) {
      return new TestResults(0.0,"ERROR: Number of tests specified in configuration does not match!"); 
    }
    assert (tests_run >= 0 && test_failures >= 0);
    // hmm, it appears that a test can fail before even starting to run(??)
    // so we cannot test this:
    //assert (tests_run >= test_failures);
    int successful_tests = std::max(0,tests_run-test_failures);
    float partial = float(successful_tests) / float(num_junit_tests);
    std::stringstream ss;
    ss << "ERROR: JUnit testing has revealed an exception or other failure.  Successful tests = " << successful_tests << "/" << num_junit_tests;
    return new TestResults(partial,ss.str());
  }
  
  std::cout << "ERROR: JUnit output did not say 'OK' or 'Failure' or 'Exception'.  This should not happen!" << std::endl;
  return new TestResults(0.0,"ERROR: JUnit output did not say 'OK' or 'Failure' or 'Exception'.  This should not happen!");
}

// =============================================================================

TestResults* TestCaseJUnit::doit_emma_instrumentation(std::ifstream &junit_output) {
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
	  return new TestResults(1.0,""); // Awarding full credit, no message
	}
      }
    }
  }

  return new TestResults(0.0,"ERROR: JUnit EMMA instrumentation not verified");
}

// =============================================================================

TestResults* TestCaseJUnit::doit_emma_coverage_report(std::ifstream &junit_output) {

  // look for version number on opening line
  std::string token1, token2;
  junit_output >> token1 >> token2;
  if (token1 != "[EMMA" || token2 != "v2.0.5312") {
    return new TestResults(0.0,"ERROR: JUnit EMMA output format and/or version number incompatible with grader");
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

      assert (coverage_threshhold >= 0.0 && coverage_threshhold <= 100.0);

      if (block_p >= coverage_threshhold &&
	  line_p >= coverage_threshhold) {
	return new TestResults(1.0,""); // Awarding full credit, no message
      }
      
      else {
	// simple formula for partial credit based on coverage.
	float partial = float(std::min(block_p,line_p)) / coverage_threshhold;
	ss2 << "ERROR: Insuffficient block and/or line coverage below threshhold for " << name 
	    << " (" << std::min(block_p,line_p) << "/" << coverage_threshhold << " = " << partial << ")";
	return new TestResults(partial,ss2.str()); 
      }
    }
  }

  return new TestResults(0.0,"ERROR: Did not successfully parse EMMA output.");
}

// =============================================================================
// =============================================================================
