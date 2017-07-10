#include <string>
#include <vector>
#include "json.hpp"


// implemented in execute.cpp
int execute(const std::string &cmd, 
    const std::vector<std::string>,
      const std::string &execute_logfile, 
      const nlohmann::json &test_case_limits,
            const nlohmann::json &assignment_limits,
            const nlohmann::json &whole_config);

int exec_this_command(const std::string &cmd, std::ofstream &logfile);

int install_syscall_filter(bool is_32, const std::string &my_program, std::ofstream &logfile, const nlohmann::json &whole_config);

// implemented in execute_limits.cpp
void enable_all_setrlimit(const std::string &program_name,
                          const nlohmann::json &test_case_limits,
                          const nlohmann::json &assignment_limits);

rlim_t get_the_limit(const std::string &program_name, int which_limit,
                     const nlohmann::json &test_case_limits,
                     const nlohmann::json &assignment_limits);

std::string get_program_name(const std::string &cmd,const nlohmann::json &whole_config);

void wildcard_expansion(std::vector<std::string> &my_args, const std::string &full_pattern, std::ostream &logfile);

std::string replace_slash_with_double_underscore(const std::string& input);
std::string escape_spaces(const std::string& input);

bool memory_ok(int rss_memory, int allowed_rss_memory);

bool time_ok(float elapsed, float seconds_to_run);

//returns true on kill order. 
bool delay_and_mem_check(float sleep_time_in_microseconds, int childPID, float& elapsed, float& next_checkpoint, 
  float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill);

std::string output_of_system_command(const char* cmd);