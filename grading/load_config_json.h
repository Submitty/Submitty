#ifndef __LOAD_CONFIG_JSON_H__
#define __LOAD_CONFIG_JSON_H__

#include <set>

void AddGlobalDefaults(nlohmann::json &whole_config);

void AddAutogradingConfiguration(nlohmann::json &whole_config);

void PreserveCompiledFiles(nlohmann::json& testcases, nlohmann::json &whole_config);

void ArchiveValidatedFiles(nlohmann::json &testcases, nlohmann::json &whole_config);

void AddDockerConfiguration(nlohmann::json &testcases, nlohmann::json &whole_config);

void FormatDispatcherActions(nlohmann::json &testcases, const nlohmann::json &whole_config);

void validate_mouse_button(nlohmann::json& action);
void validate_integer(nlohmann::json& action, std::string field, bool populate_default, int min_val, int default_value);
void validate_gif_or_screenshot_name(std::string filename);
void FormatGraphicsActions(nlohmann::json &testcases, nlohmann::json &whole_config);

void formatPreActions(nlohmann::json &testcases, nlohmann::json &whole_config);

void RewriteDeprecatedMyersDiff(nlohmann::json &testcases, nlohmann::json &whole_config);

void InflateTestcases(nlohmann::json &testcases, nlohmann::json &whole_config);

bool validShowValue(const nlohmann::json& v);

void InflateTestcase(nlohmann::json &single_testcase, nlohmann::json &whole_config);

nlohmann::json LoadAndProcessConfigJSON(const std::string &rcsid);

void AddDefaultGraphicsChecks(nlohmann::json &json_graders, const nlohmann::json &testcase);

void AddDefaultGrader(const std::string &command,
                      const std::set<std::string> &files_covered,
                      nlohmann::json& json_graders,
                      const std::string &filename,
                      const nlohmann::json &whole_config);

void AddDefaultGraders(const std::vector<nlohmann::json> &containers,
                       nlohmann::json &json_graders,
                       const nlohmann::json &whole_config);

void General_Helper(nlohmann::json &single_testcase);

void FileCheck_Helper(nlohmann::json &single_testcase);

bool HasActualFileCheck(const nlohmann::json &v_itr, const std::string &actual_file);

void Compilation_Helper(nlohmann::json &single_testcase);

void Execution_Helper(nlohmann::json &single_testcase);

void AddSubmissionLimitTestCase(nlohmann::json &config_json);

void CustomizeAutoGrading(const std::string& username, nlohmann::json& j);

void RecursiveReplace(nlohmann::json& j, const std::string& placeholder, const std::string& replacement);

void VerifyGraderDeductions(nlohmann::json &json_graders);

bool validShowValue(const nlohmann::json& v);

void validate_mouse_button(nlohmann::json& action);

void validate_integer(nlohmann::json& action, std::string field, bool populate_default, int min_val, int default_value);

void validate_gif_or_screenshot_name(std::string filename);

#endif