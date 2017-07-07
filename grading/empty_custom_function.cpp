#include "json.hpp"
#include "TestCase.h"

TestResults* TestCase::custom_dispatch(const nlohmann::json& grader) const {
  std::string method = grader.value("method","");

  return new TestResults(0.0,{std::make_pair("CUSTOM GRADER '" + method + "' NOT DEFINED","failure")});
}

