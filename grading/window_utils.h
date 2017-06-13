#include <string>
#include <vector>

std::vector<int> extract_ints_from_string(std::string input);


std::vector<std::string> get_window_names_associated_with_pid(int pid);



std::vector<int> get_window_data(std::string dataString, std::string windowName);


void initialize_window(std::string& windowName, int pid);


//modifies pos to window border if necessary. Returns remainder.
int clamp(int& pos, int min, int max);


//returns delay time
float takeAction(const std::vector<std::string>& actions, int& actions_taken, 
    int& number_of_screenshots, std::string windowName);



