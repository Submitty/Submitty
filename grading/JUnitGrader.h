#ifndef __JUNIT_GRADER_H__
#define __JUNIT_GRADER_H__

#include "TestCase.h"

// This class parses the different output types from JUnit and assigns grades

TestResults* JUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* MultipleJUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* EmmaInstrumentationGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* EmmaCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j);


#endif
