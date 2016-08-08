#include <string>

#include "json.hpp"


// implemented in execute.cpp
int execute(const std::string &cmd, 
	    const std::string &execute_logfile, 
	    nlohmann::json test_case_limits,
            nlohmann::json assignment_limits);

int exec_this_command(const std::string &cmd, std::ofstream &logfile);

int install_syscall_filter(bool is_32, const std::string &my_program, std::ofstream &logfile);

// implemented in execute_limits.cpp
void enable_all_setrlimit(const std::string &program_name,
                          nlohmann::json &test_case_limits,
                          nlohmann::json &assignment_limits);

rlim_t get_the_limit(const std::string &program_name, int which_limit,
                          nlohmann::json &test_case_limits,
                          nlohmann::json &assignment_limits);

std::string get_executable_name(const std::string &cmd);

void wildcard_expansion(std::vector<std::string> &my_args, const std::string &full_pattern, std::ostream &logfile);

std::string replace_slash_with_double_underscore(const std::string& input);
