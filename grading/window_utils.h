#include <string>
#include <vector>

std::vector<int> extract_ints_from_string(std::string input);
std::vector<std::string> get_window_names_associated_with_pid(int pid);
std::vector<int> get_window_data(std::string dataString, std::string windowName);
void initialize_window(std::string& windowName, int pid);
//modifies pos to window border if necessary. Returns remainder.
int clamp(int& pos, int min, int max);
float delay(std::string command);
void screenshot(std::string window_name, int& number_of_screenshots);
void mouse_down(int button);
void mouse_up(int button);
void click(int button);
void mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y);
void click_and_drag(std::string command);
void type(std::string command, int childPID, float &elapsed, float& next_checkpoint, 
          float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill);
void takeAction(const std::vector<std::string>& actions, int& actions_taken, int& number_of_screenshots, 
              std::string windowName, int childPID, float &elapsed, float& next_checkpoint, 
              float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill);

