#include "json.hpp"
#include "TestCase.h"

TestResults* custom_grader(const TestCase &tc, const nlohmann::json &j) {
  return new TestResults(0.0,"CUSTOM GRADER NOTE DEFINED");
}


