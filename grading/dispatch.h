#ifndef __DISPATCH_H__
#define __DISPATCH_H__

#include "TestCase.h"
#include "json.hpp"

namespace dispatch {

    TestResults* JUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* MultipleJUnitTestGrader_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* EmmaInstrumentationGrader_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* EmmaCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* JaCoCoCoverageReportGrader_doit (const TestCase &tc, const nlohmann::json& j);


    TestResults* DrMemoryGrader_doit (const TestCase &tc, const nlohmann::json& j);


    TestResults* PacmanGrader_doit (const TestCase &tc, const nlohmann::json& j);


    TestResults* searchToken_doit (const TestCase &tc, const nlohmann::json& j);


    TestResults* intComparison_doit (const TestCase &tc, const nlohmann::json& j);


    TestResults* fileExists_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* warnIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* errorIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* warnIfEmpty_doit (const TestCase &tc, const nlohmann::json& j);
    TestResults* errorIfEmpty_doit (const TestCase &tc, const nlohmann::json& j);

    TestResults* ImageDiff_doit (const TestCase &tc, const nlohmann::json& j, int autocheck_num);

    std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string>> getAllCustomValidatorMessages(const nlohmann::json& j);
    std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> getCustomValidatorMessage(const nlohmann::json& j);
    TestResults* custom_doit(const TestCase &tc, const nlohmann::json& j, const nlohmann::json& whole_config, const std::string& username, int autocheck_number);

    TestResults* diffLineSwapOk_doit (const nlohmann::json& j, const std::string &student_file_contents, const std::string &expected_file_contents);


    TestResults* diff_doit (const TestCase &tc, const nlohmann::json& j);
}

#endif
