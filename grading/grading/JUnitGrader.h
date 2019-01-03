#ifndef __JUNIT_GRADER_H__
#define __JUNIT_GRADER_H__

#include "TestCase.h"

TestResults* JUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* MultipleJUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* EmmaInstrumentationGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* EmmaCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* JaCoCoCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j);

#endif
