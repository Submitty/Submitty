#include <string>
#include <vector>
#include <set>

float stringToFloat(std::string const &str, int precision);

std::vector<float> extractFloatsFromString(std::string input);
/**
*This function uses a regex to extract any ints (12 digits or less)
*from a string. The values are returned in a vector.
*/
std::vector<int> extractIntsFromString(std::string input);

std::vector<int> getPidsAssociatedWithPid(int pid);

/**
* Given a pid, this function finds any windows directly belonging to it. 
* To do this, it iterates through the list of active windows (on the order of 3-10 usually)
* and looks at their pids. To do this, we break down the output of the wmctrl -lp system command
* using a regex, which simply strips away anything before one or more tabs or spaces.
* Runs in O(number_of_windows)
*/
std::vector<std::string> getWindowNameAssociatedWithPid(int pid);

/**
* Given the title of an xwininfo data field, returns its integer value. 
* (basic fields are height, width, etc, for a full list, run xwininfo -name <valid window name>* 
* or xwininfo and then click on a window. Should only be used for integer fields.)
*/
std::vector<int> getWindowData(std::string data_string, std::string window_name);

std::set<std::string> snapshotOfActiveWindows();

/**
* Using the child pid, this function queries to see which window names are associated with it and uses the
* first of these to set the window_name variable (which is passed by reference). At present
* if any names are returned we just use the first one (we don't currently support multi-window programs.)
* if none are returned, we simply fail to set the window_name variable. 
*/
void initializeWindow(std::string& window_name, int pid, std::set<std::string>& active_windows, float elapsed);

/**
*This function modifies pos to be <= min and >= max. It is used to set pos to be within the window border.
*returns the remainder.
*/
int clamp(int& pos, int min, int max);

/**
* This function is responsible for processing the 'delay' action. It extracts the number of seconds that 
* we are to delay, then converts to microseconds for use with usleep, and returns. Actual sleeping is handled
* by the take_action function, which calls delay_and_memcheck (execute.cpp). 
*
* TODO: Modify to work with doubles (just write a quick regex/function)
*/
float delay(std::string command);

/**
* An essential function: checks to see if the window specified by window_name currently exists.
* returns true if it does, false otherwise. 
*/
bool windowExists(std::string window_name);

/**
* Given a window name, this function takes a screenshot of it if it exists. 
* uses number_of_screenshots to title the image (submitty prepends it with the test #)
* updates the number of screenshots taken. 
*/
void screenshot(std::string window_name, int& number_of_screenshots);

/**
* This function uses xdotool to put the mouse button associated with int button into the 'down' state
* Checks to see if the window exists so that we don't click on anything that doesn't belong to us.
*/
void mouseDown(std::string window_name, int button);

/**
* This function uses xdotool to put the mouse button associated with the int button into the 'up' state.
* Checks to see if the window exists so that we don't click on anything that doesn't belong to us.
*/
void mouseUp(std::string window_name, int button);

/**
* This function mousedowns then mouseups to simulate a click. 
*/
void click(std::string window_name, int button);

/**
* This function moves the mouse to moved_mouse_x, moved_mouse_y, clamping between x_start x_end and y_start y_end.
*/
void mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y, 
                 int x_start, int x_end, int y_start, int y_end, bool no_clamp);
/**
* This function sets the height, width, x_start, y_start (upper left coords), x_end, and y_end (lower right) 
* variables of the student's window. These values are used in operations such as mouse movement. 
* returns false on failure and does not set variables. 
*/
bool populateWindowData(std::string window_name, int& height, int& width, int& x_start, int& x_end, int& y_start, int& y_end);

/**
* This function sets the window variables (using populateWindowData), and mouse_button (pressed)
* destination (an x,y tuple we are dragging to), and no_clamp (which is currently disabled/false).
* returns false on failure. It is on the programmer to check.
*/
bool populateClickAndDragValues(std::string command, std::string window_name, int& x_start, int& x_end, int& y_start, 
  int& y_end, int& mouse_button, std::vector<int>& destination, bool& no_clamp);

/**
* The 'delta' version of the click and drag command. This function moves an xy distance from a startpoint
* This distance is 'wrapping', so if it is outside of the window, we mouseup, return to the start position, 
* mousedown, and then move again. We give a one pixel border at each side of the window and clamp using 
* that value to avoid accidental resizing.
*/
void clickAndDragDelta(std::string window_name, std::string command);

/**
* Click and drag absolute: move to a relative coordinate within the window windowname, clamped.
*/
void clickAndDragAbsolute(std::string window_name, std::string command);

/**
* Routing function, forwards to delta or absolute click and drag based on command.
* (Separated due to length.)
*/
void clickAndDrag(std::string window_name, std::string command);

/**
* Centers the mouse on the window associated with windowname if it exists.
*/
void centerMouse(std::string window_name);

/**
* Moves the mouse to the upper left of the window associated with windowname if it exists.
*/
void moveMouseToOrigin(std::string window_name);

/**
* This function processes the 'type' action, which types a quoted string one character at a time
* an option number of times with an optional delay in between. Because of the dealy, we need
* all parameters necessary for a call to execute.cpp's delayAndMemCheck.
*/
void type(std::string command, std::string window_name, int childPID, float &elapsed, float& next_checkpoint, 
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill);

/**
* The central routing function for for all actions. Takes in a vector of actions and the # of actions taken 
* thus far. It then passes the current action to be taken through tests to see which function to route to.
*This function requires all parameters to for execute.cpp's delayAndMemCheck function. 
*
* NOTE TO DEVLOPERS: If you want to add a new action, also modify the preprocessing script for config.json to 
* include your new action as valid.
*/
void takeAction(const std::vector<std::string>& actions, int& actions_taken, int& number_of_screenshots, 
                std::string window_name, int childPID, float &elapsed, float& next_checkpoint, 
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill);