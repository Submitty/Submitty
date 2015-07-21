#include <string>

//                                              5 seconds,                 100 kb
//int execute(const std::string &cmd, int seconds_to_run=5, int file_size_limit=100000);
int execute(const std::string &cmd, 
	    const std::string &execute_logfile, 
	    int seconds_to_run, 
	    int file_size_limit/*, 
				 int SECCOMP_ENABLED=1*/);


