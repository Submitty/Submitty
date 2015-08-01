#include <string>


// implemented in execute.cpp
int execute(const std::string &cmd, 
	    const std::string &execute_logfile, 
	    const std::map<int,rlim_t> &test_case_limits);


// implemented in execute_limits.cpp
void enable_all_setrlimit(const std::string &program_name,
			  const std::map<int,rlim_t> &test_case_limits);

rlim_t get_the_limit(const std::string &program_name, int which_limit,
		     const std::map<int,rlim_t> &test_case_limits);
