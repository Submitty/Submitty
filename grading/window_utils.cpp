#include <sys/time.h>
#include <sys/resource.h>
#include <sys/wait.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <signal.h>
#include <unistd.h>
#include <fcntl.h>
#include <dirent.h>
#include <vector>

#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <regex>

#include "window_utils.h"
#include "execute.h"


/**
*This function uses a regex to extract any ints (12 digits or less)
*from a string. The values are returned in a vector.
*/
std::vector<int> extractIntsFromString(std::string input){
    std::vector<int> ints;
    std::string myReg = ".*?(-?[0-9]{1,12})(.*)[\\s \\S]*"; //anything (lazy), followed by a 12 digit number 
                                                            //(with or without negative sign) followed by anything,
                                                            //followed by 0 or more newlines. A number is captured 
                                                            //in group one and the rest of the string in g2
    std::regex regex(myReg);
    std::smatch match;
    while (std::regex_match(input, match, regex)){ //while we can still match (find new numbers) continue to do so    
      ints.push_back(stoi(match[1].str()));        //if we have matched, match 1 (group 1) is an integer
      input = match[2];                            //we can begin searching group 2 (the rest of the string)
    }
    return ints;
}

/**
* Given a pid, this function finds any windows directly belonging to it. 
* To do this, it iterates through the list of active windows (on the order of 3-10 usually)
* and looks at their pids. To do this, we break down the output of the wmctrl -lp system command
* using a regex, which simply strips away anything before one or more tabs or spaces.
* Runs in O(number_of_windows)
*/
std::vector<std::string> getWindowNameAssociatedWithPid(int pid)
{
  std::cout << "Attempting to find a window associated with pid: " << pid << std::endl;

  /*
  * I hate to leave a block of commented out code, but I think there is a larger conversation to be had here.
  * At the moment, this method finds any window names associated with the child's pid. This works fine.
  * However, it is concieveable that the child could fork/generate a window with its own pid. In this case
  * we could recursively traverse the pid tree below our child and gather them up. We could then match
  * this list of pids against the pids which own the current list of active windows. This extension wouldn't
  * be difficult, but I wonder whether we want to support processes forking and then having their children
  * create new windows. Regardless, please disregard the chunk of code below, as it really just has some of the 
  * commands needed to exend the program in that direction.
  */
  // std::string pidQuery = "pgrep -P ";
  // pidQuery +=  std::to_string(pid);
  // std::string children = output_of_system_command(pidQuery.c_str());
  // // std::cout << "Pids associated with child " << pid << ": " << children << std::endl;
  // std::vector<int> ints = extractIntsFromString(children);
  // for(int i = 0; i < ints.size(); i++) Support for increased depth coming at a later date.
  // {
  //   std::string pidQuery = "pgrep -P ";
  //   pidQuery +=  ints[i];
  //   children = output_of_system_command(pidQuery.c_str());
  //   std::cout << "pids associated with " << ints[i] << ": " << children << std::endl;
  // }

  std::vector<std::string> associatedWindows; //This vector will contain any windows associated with our child's pid.
  std::string activeWindows = output_of_system_command("wmctrl -lp"); //returns list of active windows with pid.
                                                                  //Example wmctrl -lp output (labels added:) 
                                                                  //window id, desktop num, pid, pc name, window title
                                                                  //0x0220000b  0 7924   mypc mywindow
  std::istringstream stream(activeWindows); //We put the list of active windows into a stream so we can iterate through it.
  std::string window;    
  std::smatch match;
  while (std::getline(stream, window)) {    //for every open window
    std::string myReg = "(.+?)[ \\t]+(.*)"; //remove everthing before one or more spaces or tabs. (view example output above)
    std::regex regex(myReg);
    if(std::regex_match(window, match, regex)){ //remove the first column. (hex window id)
      window = match[2];
      if(std::regex_match(window, match, regex)){ //remove the second column. (desktop number)
        window = match[2];
      }
      else{
        continue;
      }
    }
    else{
      continue;
    }

    if(std::regex_match(window, match, regex)){ //get the third collumn (the pid, view example output above)
      int windowPid = stoi(window); //we now have the pid of the current window
      if(windowPid != pid){ //if the pid of the current window is not equal to our desired pid, continue
        continue;
      }
      else{
        window = match[2]; //otherwise, match2 has our the last 2 columns
      }
    }
    else{
      continue;
    }
    if(std::regex_match(window, match, regex)){ //so we can get the final column (window name) and push it                                                
      associatedWindows.push_back(match[2]);    //to the back of our vec.
    }
  }
  return associatedWindows; //return our list of associated windows
}

/**
* Given the title of an xwininfo data field, returns its integer value. 
* (basic fields are height, width, etc, for a full list, run xwininfo -name <valid window name>* 
* or xwininfo and then click on a window. Should only be used for integer fields.)
*/
std::vector<int> getWindowData(std::string data_string, std::string window_name){
  if(windowExists(window_name)){ //Test that the window is still active.
    //use xwininfo to get information about windowname, then grep the data string we're looking for
    std::string command = "xwininfo -name \"" + window_name + "\" | grep \"" + data_string +"\"";
    std::string value_string = output_of_system_command(command.c_str()); //store the output.
    if(value_string == ""){ //grep will return nothing if the field doesn't exist.
      std::vector<int> empty;  //so return nothing.
      return empty;
    }
    return extractIntsFromString(value_string); //if grep returned something, get all the ints.
  }
  else{
    std::vector<int> empty; //if the window doesn't exist, just return an empty vector (nonexistent windows
                            //should be handled within one timestep. See execute function in execute.cpp) 
    return empty;
  }  
}

/**
* Using the child pid, this function queries to see which window names are associated with it and uses the
* first of these to set the window_name variable (which is passed by reference). At present
* if any names are returned we just use the first one (we don't currently support multi-window programs.)
* if none are returned, we simply fail to set the window_name variable. 
*/
void initializeWindow(std::string& window_name, int pid){
  std::vector<std::string> windows = getWindowNameAssociatedWithPid(pid); //get the window names associated
                                                                                //with our pid
  if(windows.size() == 0){ //if none exist, do not set the window_name variable
    std::cout << "Initialization failed..." << std::endl;
    return;
  }
  else{ //otherwise, default to using the first entry in the vector.
    std::cout << "We found the window " << windows[0] << std::endl;
    window_name = windows[0];
  }
}

/**
*This function modifies pos to be <= min and >= max. It is used to set pos to be within the window border.
*returns the remainder.
*/
int clamp(int& pos, int min, int max){
  int leftOver = 0;
  if(pos < min){ //if pos is less than the min
    leftOver = pos - min; //calculate by how much
    pos = min; //and modify pos to be the minimum possible value
  }
  if(pos > max){ //do the same for max.
    leftOver = pos - max; 
    pos = max;
  }
  return leftOver; //return the amount by which we were outside of our acceptable range.
}

/**
* This function is responsible for processing the 'delay' action. It extracts the number of seconds that 
* we are to delay, then converts to microseconds for use with usleep, and returns. Actual sleeping is handled
* by the take_action function, which calls delay_and_memcheck (execute.cpp). 
*
* TODO: Modify to work with doubles (just write a quick regex/function)
*/
float delay(std::string command){
  std::vector<int> numbers = extractIntsFromString(command); //find any numbers in the delay line (ints)
  if (numbers.size() > 0){
    int sleep_time_secs = numbers[0];
    if(sleep_time_secs < 0){ //if we have any numbers, assume the first is the amount we want to delay by.
                             //larger errors should be detected in the preprocessing step (at course build time)
      sleep_time_secs = abs(sleep_time_secs); //we can't delay for a negative amount of time.
    }
    std::cout << "Delaying for " << numbers[0] << " seconds." << std::endl;
    float sleep_time_micro = 1000000 * sleep_time_secs; //convert to microseconds and return.  
    return sleep_time_micro;
  }
}

/**
* An essential function: checks to see if the window specified by window_name currently exists.
* returns true if it does, false otherwise. 
*/
bool windowExists(std::string window_name){
  std::string command = "xwininfo -name \"" + window_name + "\"";
  std::string output = output_of_system_command(command.c_str());
  if(output.find("xwininfo: error:")!= std::string::npos){ //to check if the window exists we look to see if we 
    return false;                                          //are given an xwininfo error. TODO: I would like to find
    }                                                      //a better way to do this.
  else {
    return true;
  }
}

/**
* Given a window name, this function takes a screenshot of it if it exists. 
* uses number_of_screenshots to title the image (submitty prepends it with the test #)
* updates the number of screenshots taken. 
*/
void screenshot(std::string window_name, int& number_of_screenshots){
  if(windowExists(window_name)){ //if the window hasn't crashed, bring it into focus and screenshot it
    std::string command = "wmctrl -R " + window_name + " && scrot "  + std::to_string(number_of_screenshots) + ".png -u";
    system(command.c_str());
    number_of_screenshots = number_of_screenshots + 1;
  }
  else{
    std::cout << "Attempted to screenshot a closed window." << std::endl;
  }
}

/**
* This function uses xdotool to put the mouse button associated with int button into the 'down' state
* Checks to see if the window exists so that we don't click on anything that doesn't belong to us.
*/
void mouseDown(std::string window_name, int button){ 
  if(button == 1 || button == 2 || button == 3){ //only mouse down button 1, 2, or 3.
    if(windowExists(window_name)){ //only mouse down if the window exists (bring into focus and mousedown)
      std::string command = "wmctrl -R " + window_name + " &&  xdotool mousedown " + std::to_string(button);
      system(command.c_str());  
    }
    else{
      std::cout << "Tried to mouse down on a nonexistent window." << std::endl;
    }
  }
  else{
      std::cout << "ERROR: tried to click nonexistent mouse button " << button << std::endl;
  }
}

/**
* This function uses xdotool to put the mouse button associated with the int button into the 'up' state.
* Checks to see if the window exists so that we don't click on anything that doesn't belong to us.
*/
void mouseUp(std::string window_name, int button){
  if(button == 1 || button == 2 || button == 3){ //only mouseup on buttons 1,2,3
    if(windowExists(window_name)){ //Only mouse up if the window exists (give the window focus and mouseup)
      std::string command = "wmctrl -R " + window_name + " &&  xdotool mouseup " + std::to_string(button);
      system(command.c_str());  
    }
    else{
      std::cout << "Tried to mouse up on a nonexistent window" << std::endl;
    }
  }
  else{
    std::cout << "ERROR: tried to click mouse button " << button << std::endl;
  }
}

/**
* This function mousedowns then mouseups to simulate a click. 
*/
void click(std::string window_name, int button){
  mouseDown(window_name, button);
  mouseUp(window_name, button);
}

/**
* This function moves the mouse to moved_mouse_x, moved_mouse_y, clamping between x_start x_end and y_start y_end.
*/
void mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y, int x_start, int x_end, int y_start, int y_end){
  clamp(moved_mouse_x, x_start, x_end); //don't move outside of the window.
  clamp(moved_mouse_y, y_start, y_end);
  
  if(windowExists(window_name)) //only move the mouse if the window exists. (get focus and mousemove.)
  {
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mousemove --sync "
                     + std::to_string(moved_mouse_x) + " " + std::to_string(moved_mouse_y);  
    system(command.c_str());
  }
  else
  {
    std::cout << "Attempted to move mouse on a nonexistent window." << std::endl;
  }  
}
/**
* This function sets the height, width, x_start, y_start (upper left coords), x_end, and y_end (lower right) 
* variables of the student's window. These values are used in operations such as mouse movement. 
* returns false on failure and does not set variables. 
*/
bool populateWindowData(std::string window_name, int& height, int& width, int& x_start, int& x_end, int& y_start, int& y_end){
  if(windowExists(window_name)) {
    std::vector<int> height_vec, width_vec, x_start_vec, y_start_vec;
    height_vec = getWindowData("Height", window_name); //getWindowData returns a vector with any ints associated with the 
    width_vec = getWindowData("Width", window_name);   // query term (e.g. 'Height')
    x_start_vec = getWindowData("Absolute upper-left X", window_name); //These two values represent the upper left corner
    y_start_vec = getWindowData("Absolute upper-left Y", window_name);
    
    if(height_vec.size() > 0){  height  = height_vec[0];}  else{ return false; } //these should never return false, as the Height, Width, etc.
    if(width_vec.size() > 0) {  width   = width_vec[0];}   else{ return false; } //fields should always be populated if the window exists.
    if(x_start_vec.size() > 0){ x_start = x_start_vec[0];} else{ return false; } //the only way that I can see for these to fail would be
    if(y_start_vec.size() > 0){ y_start = y_start_vec[0];} else{ return false; } //if xdotool changes or the window fails/shuts in this block.

    x_end = x_start+width; //These values represent the upper right corner
    y_end = y_start + height;
    return true;
  }
  else
  {
    std::cout << "Attempted to populate window data using a nonexistent window" << std::endl;
    return false;
  }
}
/**
* This function sets the window variables (using populateWindowData), and mouse_button (pressed)
* destination (an x,y tuple we are dragging to), and no_clamp (which is currently disabled/false).
* returns false on failure. It is on the programmer to check.
*/
bool populateClickAndDragValues(std::string command, std::string window_name, int& x_start, int& x_end, int& y_start, 
  int& y_end, int& mouse_button, std::vector<int>& destination, bool& no_clamp){
  int height, width;
  populateWindowData(window_name, height, width, x_start, x_end, y_start, y_end);

  destination = extractIntsFromString(command);
  if(destination.size() == 0){
    std::cout << "ERROR: The line " << command << " does not specify two coordinates." <<std::endl;
    return false;
  }
  
  if(command.find("no clamp") != std::string::npos){
    std::cout << "Multiple windows are not yet supported. (No no clamp)" << std::endl;
    no_clamp = false;
  }

  if(command.find("left") != std::string::npos){
    mouse_button = 1;
  }
  else if (command.find("middle") != std::string::npos){
    mouse_button = 2;
  }
  else if (command.find("right") != std::string::npos){
    mouse_button = 3;
  }
  else{
    mouse_button = 1; //default.
  }
  return true;
}

/**
* The 'delta' version of the click and drag command. This function moves an xy distance from a startpoint
* This distance is 'wrapping', so if it is outside of the window, we mouseup, return to the start position, 
* mousedown, and then move again. We give a one pixel border at each side of the window and clamp using 
* that value to avoid accidental resizing.
*/
void clickAndDragDelta(std::string window_name, std::string command){
  int x_start, x_end, y_start, y_end, mouse_button; //get the values of the student's window.
  std::vector<int> coords; 
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(command, window_name, x_start, x_end, y_start, y_end, mouse_button, coords, no_clamp);
 
  if(!success){ //if we can't populate the click and drag values, do nothing.
    std::cout << "Could not populate the click and drag values."<< std::endl;
    return;
  }
  //delta version, 2 values movement x and movement y.
  int amt_x_movement_remaining = coords[0];
  int amt_y_movement_remaining = coords[1];


  //We force the mouse to start inside of the window by at least a pixel.
  std::string mouse_location_string = output_of_system_command("xdotool getmouselocation"); //This shouldn't fail unless
                                                                                            //there isn't a mouse.
  std::vector<int> xy = extractIntsFromString(mouse_location_string);
  
  if(xy.size() < 2){ //if the mouse isn't detected, fail.
    std::cout << "Mouse coordinates couldn't be found. Mouse undetected." << std::endl;
    return;
  }
  int mouse_x = xy[0];
  int mouse_y = xy[1];
  clamp(mouse_x, x_start+1, x_end-1); //move in by a pixel.
  clamp(mouse_y, y_start+1, y_end-1);

  //NOTE: check my arithmetic. 
  float slope = (float)amt_y_movement_remaining / (float)amt_x_movement_remaining; //rise / run
  float total_distance_needed = sqrt(pow(amt_x_movement_remaining, 2) + pow (amt_y_movement_remaining, 2)); 
  float remaining_distance_needed = total_distance_needed; //remaining distance needed.
  //while loop with a clamp.
  int curr_x = 0;
  while(remaining_distance_needed >= 1 && windowExists(window_name)){ //The functions called within this loop will not fire
                                                                      //if the window doesn't exist. This check just short
                                                                      // circuits to avoid additional printing.
    mouse_move(window_name, mouse_x, mouse_y, x_start, x_end, y_start, y_end); //move the mouse to the start location
    int xStep = x_end-mouse_x; //TODO: test more extensively. //How far we can move in the x
    float distance_of_move = sqrt(pow(xStep, 2) + pow (xStep*slope, 2)); //the distance we can move 
    
    if(distance_of_move > remaining_distance_needed){ //if the distance we can move is more than the distance we need to move
    
      distance_of_move = remaining_distance_needed; //clamp in.
      xStep = total_distance_needed - curr_x;
    }

    remaining_distance_needed -= distance_of_move; //we are moving distance_of_move

    mouseDown(window_name,1); //click
    int moved_mouse_x = mouse_x+xStep;
    int moved_mouse_y = mouse_y + (xStep * slope);
    mouse_move(window_name, moved_mouse_x, moved_mouse_y,x_start, x_end, y_start, y_end); //drag
    mouseUp(window_name,1); //release
    
    curr_x += xStep; //keep track of how far we've moved in the x.
  }
}

/**
* Click and drag absolute: move to a relative coordinate within the window windowname, clamped.
*/
void clickAndDragAbsolute(std::string window_name, std::string command){
  
  int x_start, x_end, y_start, y_end, mouse_button; //populate the window variables. 
  std::vector<int> coords; 
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(command, window_name, x_start, x_end, y_start, y_end, mouse_button, coords, no_clamp);
  
  if(!success){ //if we couldn't populate the values, do nothing (window doesn't exist)
    std::cout << "Click and drag unsuccessful due to failutre to populate click and drag values." << std::endl;
    return;
  }

  int start_x_position, start_y_position, end_x_position, end_y_position;
  if(coords.size() >3){ //get the mouse into starting position if they are specified.
    start_x_position = coords[0] + x_start;
    start_y_position = coords[1] + y_start;
    end_x_position   = coords[2] + x_start;
    end_y_position   = coords[3] + y_start;
    //reset logic 
    
    clamp(start_x_position, x_start, x_end); //don't move out of the window.
    clamp(start_y_position, y_start, y_end);
    mouse_move(window_name, start_x_position, start_y_position,x_start, x_end, y_start, y_end); 
  }
  else{
    end_x_position = coords[0] + x_start;
    end_y_position = coords[1] + y_start;
  }
  
  clamp(end_x_position, x_start, x_end); //clamp the end position so we don't exit the window. 
  clamp(end_y_position, y_start, y_end);

  mouseDown(window_name,1); //These functions won't do anything if the window doesn't exist. 
  mouse_move(window_name, end_x_position, end_y_position,x_start, x_end, y_start, y_end);
  mouseUp(window_name,1);  
}

/**
* Routing function, forwards to delta or absolute click and drag based on command.
* (Separated due to length.)
*/
void clickAndDrag(std::string window_name, std::string command)
{
  if(command.find("delta") != std::string::npos){
    clickAndDragDelta(window_name, command); //these functions check window existence internally.
  }
  else{
    clickAndDragAbsolute(window_name, command);
  }
}

/**
* Centers the mouse on the window associated with windowname if it exists.
*/
void centerMouse(std::string window_name){
  int height, width, x_start, x_end, y_start, y_end; //populate the window vals to get the center.
  bool success = populateWindowData(window_name, height, width, x_start,x_end,y_start,y_end);
  int x_middle = x_start + width/2;
  int y_middle = y_start+height/2;

  if(success && windowExists(window_name)){ //wait until the last moment to check window existence.
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mousemove --sync " + 
    std::to_string(x_middle) + " " + std::to_string(y_middle); 
    system(command.c_str());
  }
  else{
    std::cout << "Attempted to center mouse on a nonexistent window" << std::endl;
  }
}

/**
* Moves the mouse to the upper left of the window associated with windowname if it exists.
*/
void moveMouseToOrigin(std::string window_name){
  int height, width, x_start, x_end, y_start, y_end; //populate the window vals to get the center.
  bool success = populateWindowData(window_name, height, width, x_start,x_end,y_start,y_end);

  if(success&& windowExists(window_name)){ //wait until the last moment to check window existence.
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mousemove --sync " + 
                          std::to_string(x_start) + " " + std::to_string(y_start); 
    system(command.c_str());
  }
  else{
    std::cout << "Attempted to move mouse to origin of nonexistent window" << std::endl;
  }
}

/**
* This function processes the 'type' action, which types a quoted string one character at a time
* an option number of times with an optional delay in between. Because of the dealy, we need
* all parameters necessary for a call to execute.cpp's delayAndMemCheck.
*/
void type(std::string command, std::string window_name, int childPID, float &elapsed, float& next_checkpoint, 
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill){
  int presses = 1; //default number of iterations is 1
  float delay = 100000; //default delay between iterations is 1/10th of a second.
  std::string toType = ""; 
  std::vector<int> values = extractIntsFromString(command); //see if there are ints in the string. (optional times pressed/delay)
  //TODO update to allow doubles.
  if(values.size() > 0){
    presses = values[0];
  }
  if(values.size() > 1){
    delay = values[1] * 1000000; //convert from seconds to microseconds.
  }
  std::string myReg = ".*?(\".*?\").*"; //anything (lazy) followed by anything between quotes (lazy)
                                        //followed by anything (greedy)
  std::regex regex(myReg);
  std::smatch match;
  if(std::regex_match(command, match, regex)){ //get the text to type.
    toType = match[1];  
  }
  if(toType == "")
  {
    std::cout << "ERROR: The line " << command << " contained no quoted string." <<std::endl; 
    //allowing it to go on so that it delays as expected.
  }   
  //get window focus then type the string toType.
  std::string internal_command = "wmctrl -R " + window_name + " &&  xdotool type " + toType; 
  for(int i = 0; i < presses; i++){ //for number of presses
    if(windowExists(window_name) && toType != ""){ //check that the window exists and we have something to type.
      system(internal_command.c_str());
    }
    else{
      std::cout << "Attempted to type on nonexistent window" << std::endl;
    }
    if(i != presses-1){ //allow this to run so that delays occur as expected.
      delay_and_mem_check(delay, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);   
    }
  }
}

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
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill){
  //We get the window data at every step in case it has changed size.
  if(!windowExists(window_name)){ //if we make it past this check, we'll assume an action has been taken.
    return;
  }
  float delay_time = 0;  
  
  std::cout<<"Taking action " << actions_taken+1 << " of " << actions.size() << ": " << actions[actions_taken]<< std::endl;
  if(actions[actions_taken].find("delay") != std::string::npos){ //DELAY
    delay_time = delay(actions[actions_taken]);
  }
  else if(actions[actions_taken].find("screenshot") != std::string::npos){ //SCREENSHOT
    screenshot(window_name, number_of_screenshots);
  }
  else if(actions[actions_taken].find("type") != std::string::npos){ //TYPE
    type(actions[actions_taken], window_name, childPID, elapsed, next_checkpoint, 
                seconds_to_run, rss_memory, allowed_rss_memory, memory_kill, time_kill);
  }
  else if(actions[actions_taken].find("click and drag") != std::string::npos){ //CLICK AND DRAG    
    clickAndDragAbsolute(window_name,actions[actions_taken]);
  }
  else if(actions[actions_taken].find("click") != std::string::npos){ //CLICK
    std::vector<int> button = extractIntsFromString(actions[actions_taken]);
    if(button.size() >0 && button[0] >0 && button[0] <= 3){
      click(window_name, button[0]);
    }
    else{
      click(window_name, 1);
    }
  }
  else if(actions[actions_taken].find("xdotool") != std::string::npos){ //CUSTOM XDO COMMAND
    system(actions[actions_taken].c_str()); //This should be better scrubbed.
  }
  else if(actions[actions_taken].find("move mouse") != std::string::npos || 
          actions[actions_taken].find("move mouse to") != std::string::npos){ //MOUSE MOVE
    std::vector<int> coordinates = extractIntsFromString(actions[actions_taken]);
    if(coordinates.size() >= 2){
      int height, width, x_start, x_end, y_start, y_end;
      bool success = populateWindowData(window_name, height, width, x_start, x_end, y_start, y_end);
      if(success){
        mouse_move(window_name, coordinates[0], coordinates[1], x_start, x_end, y_start, y_end);
      }
      else{
        std::cout << "No mouse move due to unsuccessful data population." << std::endl;
      }
    }
  }
  else if(actions[actions_taken].find("center") != std::string::npos){ //CENTER
    centerMouse(window_name);
  }
  else if(actions[actions_taken].find("origin") != std::string::npos){ //ORIGIN
    moveMouseToOrigin(window_name);
  }
  else{ //BAD COMMAND
    std::cout << "ERROR: ill formatted command: " << actions[actions_taken] << std::endl;    
  }
  actions_taken++;
  delay_and_mem_check(delay_time, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);   
}


