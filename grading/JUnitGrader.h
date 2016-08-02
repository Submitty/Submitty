#ifndef __JUNIT_GRADER_H__
#define __JUNIT_GRADER_H__

#include "TestCase.h"

// This class parses the different output types from JUnit and assigns grades

class TestCaseJUnit { //: public TestCaseGrader {
 public:

  std::string filename;
  std::string description;
  float deduction;


  // We have 3 constructor helper functions for the 3 different types
  // of test cases related to JUnit.

  static TestCaseJUnit* JUnitTestGrader(const std::string &f, int num, float points_frac = -1.0) {
    TestCaseJUnit* answer = new TestCaseJUnit(f,"JUnit output",points_frac);
    answer->junit_grader_type = "JUNIT_TEST";
    answer->num_junit_tests = num;
    return answer;
  }

  static TestCaseJUnit* MultipleJUnitTestGrader(const std::string &f, float points_frac = -1.0) {
    TestCaseJUnit* answer = new TestCaseJUnit(f,"TestRunner output",points_frac);
    answer->junit_grader_type = "MULTIPLE_JUNIT_TESTS";
    return answer;
  }

  static TestCaseJUnit* EmmaInstrumentationGrader(const std::string &f, float points_frac = -1.0) {
    TestCaseJUnit* answer = new TestCaseJUnit(f,"JUnit EMMA instrumentation output",points_frac);
    answer->junit_grader_type = "EMMA_INSTRUMENTATION";
    return answer;
  }

  // coverage thresh is a percentage (0->100)
  static TestCaseJUnit* EmmaCoverageReportGrader(const std::string &f, float coverage_thresh, float points_frac = -1.0) {
    TestCaseJUnit* answer = new TestCaseJUnit(f,"JUnit EMMA coverage report",points_frac);
    answer->junit_grader_type = "EMMA_COVERAGE_REPORT";
    assert (coverage_thresh >= 10.0 && coverage_thresh <= 100.0);
    answer->coverage_threshhold = coverage_thresh;
    answer->filename = f;
    return answer;
  }
  
  // The function that actually does the grading
  virtual TestResults* doit(const std::string &prefix);

 private:

  // the actual constructor is private
  TestCaseJUnit(const std::string& file, const std::string &desc, float points_frac) { 
    filename = file;
    description = desc; //TestCaseGrader(file,description) { 
    deduction=points_frac;
    num_junit_tests = -1;
    coverage_threshhold = -1;
  }

  // helper functions that do the real grading
  TestResults* doit_junit_test(std::ifstream &junit_output);
  TestResults* doit_emma_instrumentation(std::ifstream &junit_output);
  TestResults* doit_emma_coverage_report(std::ifstream &junit_output);
  TestResults* doit_multiple_junit_tests(std::ifstream &junit_output);


  // junit_grader_type should be:  JUNIT_TEST or EMMA_INSTRUMENTATION or EMMA_COVERAGE_REPORT
  std::string junit_grader_type;

  // variable used in the JUNIT_TEST grader
  int num_junit_tests;

  // variable used in the EMMA_COVERAGE_REPORT grader
  float coverage_threshhold;

};

#endif
