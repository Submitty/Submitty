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
#include <iostream>

#include <set>
#include <cstdlib>
#include <string>
#include <sstream>
#include <regex>
#include <iomanip>

#include "window_utils.h"
#include "execute.h"


/**
*Converts a string to a double with specified precision.
*/
float stringToFloat(std::string const &str, int precision) {
  float my_float;
  //just use a string stream to get the double. 
  std::stringstream stream; 
  stream << std::setprecision(precision) << std::fixed << str << std::endl;

  stream >> my_float;

  return my_float;
}

std::vector<float> extractFloatsFromString(std::string input){
    std::vector<float> floats;
    //Anything (lazy) followed by either a number of the form #.##### or .#####
    std::string myReg = ".*?(-?[0-9]+(\\.[0-9]+)?|-?0*\\.[0-9]+)(.*)";
    std::regex regex(myReg);
    std::smatch match;
    int i = 0;
    //while we can still match (find new numbers) continue to do so 
    while (std::regex_match(input, match, regex)){  
      //if we have matched, match 1 (group 1) is a float
      floats.push_back(stringToFloat(match[1].str(), 10));        
      input = match[3]; 
      i++;
    }
    return floats;
}
/**
*This function uses a regex to extract any ints (12 digits or less)
*from a string. The values are returned in a vector.
*/
std::vector<int> extractIntsFromString(std::string input){

    if (input == "")
    {
      std::vector<int> empty;
      return empty;
    }

    std::vector<int> ints;
    //The regex below is of the form:
    //anything (lazy), followed by a 12 digit number (with or without negative 
    // sign) followed by anything, followed by 0 or more newlines. A number is 
    // captured in group one and the rest of the string in g2
    std::string myReg = ".*?(-?[0-9]{1,12})(.*)[\\s \\S]*"; 
    std::regex regex(myReg);
    std::smatch match;
    //while we can still match (find new numbers) continue to do so
    while (std::regex_match(input, match, regex)){
      //if we have matched, match 1 (group 1) is an integer     
      ints.push_back(stoi(match[1].str()));      
      //we can begin searching group 2 (the rest of the string)  
      input = match[2];                            
    }
    return ints;
}

std::vector<int> getPidsAssociatedWithPid(int pid){
  std::string pidQuery = "pgrep -P ";
  pidQuery +=  std::to_string(pid);
  std::string children = output_of_system_command(pidQuery.c_str());
  std::vector<int> ints = extractIntsFromString(children);

  std::cout << "The pids associated with " << pid << " are: (there are " << ints.size() << ") " << std::endl;
  for(int i = 0; i < ints.size(); i++) 
  {
    std::cout << "\t" << ints[i] << std::endl;
  }
  return ints;
}

/**
* Given a pid, this function finds any windows directly belonging to it. 
* To do this, it iterates through the list of active windows (on the order of 
* 3-10 usually) and looks at their pids. To do this, we break down the output 
* of the wmctrl -lp system command using a regex, which simply strips away 
* anything before one or more tabs or spaces. Runs in O(number_of_windows)
*/
std::vector<std::string> getWindowNameAssociatedWithPid(int pid){
  //The below call is currently unused, but will be helpful when detecting multiwindow apps.
  // getPidsAssociatedWithPid(pid);
  //This vector will contain any windows associated with our child's pid.
  std::vector<std::string> associatedWindows; 
  //returns list of active windows with pid.
  //Example wmctrl -lp output (labels added:) 
  //window id, desktop num, pid, pc name, window title
  //0x0220000b  0 7924   mypc mywindow
  std::string activeWindows = output_of_system_command("wmctrl -lp"); 
  //We put the list of active windows into a stream so we can iterate over it.
  std::istringstream stream(activeWindows); 
  std::string window;    
  std::smatch match;
  //for every open window
  while (std::getline(stream, window)) {    
    //remove everthing before one or more spaces or tabs. 
    //(view example output above)
    std::string myReg = "(.+?)[ \\t]+(.*)"; 
    std::regex regex(myReg);
    //remove the first column. (hex window id)
    if(std::regex_match(window, match, regex)){ 
      window = match[2];
      //remove the second column. (desktop number)
      if(std::regex_match(window, match, regex)){ 
        window = match[2];
      }
      else{
        continue;
      }
    }
    else{
      continue;
    }
    //get the third column (the pid, view example output above)
    if(std::regex_match(window, match, regex)){ 
      //we now have the pid of the current window
      int windowPid = stoi(window); 
      //if the current window's pid is not equal to our desired pid, continue
      if(windowPid != pid){ 
        continue;
      }
      else{
        window = match[2]; //otherwise, match2 has our the last 2 columns
      }
    }
    else{
      continue;
    }
    //We can get the final column (window name) and push it to our vec.
    if(std::regex_match(window, match, regex)){                                                 
      associatedWindows.push_back(match[2]);    
    }
  }
  return associatedWindows; //return our list of associated windows
}

/**
* Given the title of an xwininfo data field, returns its integer value. 
* (basic fields are height, width, etc, for a full list, 
* run xwininfo -name <valid window name> or xwininfo and then click a window.
* Should only be used for integer fields.)
*/
std::vector<int> getWindowData(std::string data_string, 
                                  std::string window_name){
  //Test that the window is still active.
  if(windowExists(window_name)){ 
    //use xwininfo to get information about windowname, then grep the data 
    //string we're looking for
    std::string command = "xwininfo -name \"" + window_name + "\" | grep \""  
                           + data_string +"\"";
    std::string value_string = output_of_system_command(command.c_str()); 
    //grep will return nothing if the field doesn't exist, so return nothing.
    if(value_string == ""){ 
      std::vector<int> empty;  
      return empty;
    }
    //if grep returned something, get all the ints in what it returned
    return extractIntsFromString(value_string); 
  }
  else{
    //if the window doesn't exist, just return an empty vector (nonexistent 
    // windows should be handled in one timestep per execute() in execute.cpp
    std::vector<int> empty; 
    return empty;
  }  
}

std::set<std::string> snapshotOfActiveWindows(){
  std::set<std::string> activeWindowSet; 
  //returns list of active windows with pid.
  //Example wmctrl -lp output (labels added:) 
  //window id, desktop num, pid, pc name, window title
  //0x0220000b  0 7924   mypc mywindow
  std::string activeWindows = output_of_system_command("wmctrl -lp"); 
  //We put the list of active windows into a stream so we can iterate over it.
  std::istringstream stream(activeWindows); 
  std::string window;    
  std::smatch match;
  //for every open window
  while (std::getline(stream, window)) {    
    //remove everthing before one or more spaces or tabs. 
    //(view example output above)
    std::string myReg = "(.+?)[ \\t]+(.*)"; 
    std::regex regex(myReg);
    //remove the first column. (hex window id)
    if(std::regex_match(window, match, regex)){ 
      window = match[2];
      //remove the second column. (desktop number)
      if(std::regex_match(window, match, regex)){ 
        window = match[2];
      }
      else{
        continue;
      }
    }
    else{
      continue;
    }
    if(std::regex_match(window, match, regex)){ 
      //we now have the pid of the current window
      window = match[2]; //otherwise, match2 has our the last 2 columns
    }
    else{
      continue;
    }
    //We can get the final column (window name) and push it to our vec.
    if(std::regex_match(window, match, regex)){                                                 
      activeWindowSet.insert(match[2]);    
    }
  }
  return activeWindowSet; //return our list of associated windows
}

/**
* Using the child pid, this function queries to see which window names are 
* associated with it and uses the first of these to set the window_name 
* variable (which is passed by reference). At present, if any names are 
* returned we just use the first one (we don't currently support multi-window 
* programs.) If none are returned, we fail to set the window_name variable. 
*/
void initializeWindow(std::string& window_name, int pid, std::set<std::string>& invalid_windows, float elapsed){
  
  //for the first two seconds, only try to init via pid.
  if (elapsed < 2)
  {
    //get the window names associated with our pid.
    std::vector<std::string> windows = getWindowNameAssociatedWithPid(pid);
    if(windows.size() == 0){
      return;
    }
    else{
      //if a window exists, default to using the first entry in the vector.
      std::cout << "Using the pid method, we found the window " << windows[0] << std::endl;
      window_name = windows[0];
    }
  }
  //else try to init using both.
  else
  {
    std::string window_name_pid_method = "";
    std::string window_name_name_method = "";

    //get the window names associated with our pid.
    std::vector<std::string> windows = getWindowNameAssociatedWithPid(pid);
    if(windows.size() != 0){
      //if a window exists, default to using the first entry in the vector.
      std::cout << "Using the pid method, we found the window " << windows[0] << std::endl;
      window_name_pid_method = windows[0];
    }

    std::set<std::string> current_windows = snapshotOfActiveWindows();
    std::set<std::string>::iterator it;
    for (it = current_windows.begin(); it != current_windows.end(); ++it)
    {
      //If the window is not in the invalid set, it is good.
      if(invalid_windows.find(*it) == invalid_windows.end())
      {
        window_name_name_method = *it;
        break;
      }
    }

    if(window_name_pid_method != "" && window_name_name_method != "")
    {
      if(window_name_pid_method == window_name_name_method)
      {
        window_name = window_name_pid_method;
        std::cout << "both methods agreed on the window " << window_name << std::endl;
      }
      else
      {
        std::cout << "The two methods disagreed on the window name, so we defaulted to the pid method's answer: "
                    << window_name << std::endl;
        window_name = window_name_pid_method;
      }
    }
    else if (window_name_pid_method != "")
    {
      std::cout << "The pid method found the window " << window_name_pid_method << std::endl;
      window_name = window_name_pid_method;
    }
    else if (window_name_name_method != "")
    {
      std::cout << "The name method found the window " << window_name_name_method << std::endl;
      window_name = window_name_name_method;
    }
  }
}

/**
*This function modifies pos to be <= min and >= max. It is used to set pos to 
* be within the window border. Returns the remainder.
*/
int clamp(int& pos, int min, int max){
  int leftOver = 0;
  //if pos is less than the min,calculate by how much and modify pos to be the
  // minimum possible value
  if(pos < min){ 
    leftOver = pos - min; 
    pos = min; 
  }
  //do the same for max.
  if(pos > max){ 
    leftOver = pos - max; 
    pos = max;
  }
  //return the amount by which we were outside of our acceptable range.
  return leftOver; 
}

/**
* This function is responsible for processing the 'delay' action. It 
* converts to microseconds for
* use with usleep, and returns. Actual sleeping is handled by the take_action 
* function, which calls delay_and_memcheck (execute.cpp). 
*/
float delay(float sleep_time_secs){
  
  if(sleep_time_secs < 0){ 
    sleep_time_secs = abs(sleep_time_secs); 
  }
  //convert to microseconds and return. 
  float sleep_time_micro = 1000000 * sleep_time_secs;
  return sleep_time_micro;

}

/**
* An essential function: checks to see if the window specified by window_name 
* currently exists. returns true if it does, false otherwise. 
*/
bool windowExists(std::string window_name){
  std::string command = "xwininfo -name \"" + window_name + "\"";
  std::string output = output_of_system_command(command.c_str());
  //to check if the window exists we look to see if we are given an xwininfo 
  // error. TODO: I would like to find a better way to do this.
  if(output.find("xwininfo: error:")!= std::string::npos){ 
    return false;                                          
    }                                                      
  else {
    return true;
  }
}

/**
* Given a window name, this function takes a screenshot of it if it exists. 
* uses screenshot_name to title the image.
*/
bool screenshot(std::string window_name, std::string screenshot_name){
  if(windowExists(window_name)){ 
    //if the window hasn't crashed, bring it into focus and screenshot it
    std::string command = "wmctrl -R " + window_name + " && scrot "  
                      + screenshot_name + " -u";
    system(command.c_str());
    return true;
  }
  else{
    std::cout << "Attempted to screenshot a closed window." << std::endl;
    return false;
  }
}

std::string pad_integer(int number, int padding) {
  std::ostringstream out;
  out << std::internal << std::setfill('0') << std::setw(padding) << number;
  return out.str();
}

/*
* Take the screenshots neccessary to later compile a gif.
*/
bool make_gif(std::string window_name, std::string gif_name, float duration_in_seconds, int fps, bool save_pngs,
              int childPID, float &elapsed, float& next_checkpoint, float seconds_to_run, 
              int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill,
              std::ostream &logfile){

  //iterations is seconds*fps. 
  int iterations = duration_in_seconds * fps;
  //delay is 1 second/fps
  float delay    = (1000000.0f/fps);
 
  std::vector<std::string> png_names;

  bool killed = false;
  for(int i = 0; i < iterations; i++){ 
    


    std::string iterative_gif_name = gif_name + "_" + pad_integer(i, 4) + ".png";
    std::cout << "taking gif screenshot " << iterative_gif_name << std::endl;
    bool successful_screenshot = screenshot(window_name, iterative_gif_name);

    if(!successful_screenshot){
      return false;
    }

    png_names.push_back(iterative_gif_name);

    //add a 1/10th second delay between each image capture.
    if(i != iterations-1){ 
      int max_rss_memory = 0;
      killed = delay_and_mem_check(delay, childPID, elapsed, next_checkpoint, 
        seconds_to_run, rss_memory, max_rss_memory, allowed_rss_memory, memory_kill,time_kill, logfile);
    }
    if(killed){
      return false;
    }
  }

  //let's just test the cost of this
  std::string target_gif_name = gif_name + "*.png";
  std::string outfile_name = gif_name + ".gif";

  //create a gif
  int refresh_rate_in_hundredths = (1.0f / fps) * 100.0f;
  std::string command = "convert -delay "+std::to_string(refresh_rate_in_hundredths)+" -loop 0 " + target_gif_name + " " + outfile_name;
  std::string output = output_of_system_command(command.c_str()); //get the string

  //If we are supposed to remove the png files, do so.
  // Do not use wildcards, only remove files we explicitly created.
  if(!save_pngs){
    for(int i = 0; i < png_names.size(); i++){
      std::string remove_png_name = png_names[i];
      std::string rm_command = "rm " + remove_png_name + " NULL: 2>&1";
      std::cout << rm_command << std::endl;
      std::string output = output_of_system_command(rm_command.c_str()); //get the string
    }
  }

  return true;
}


/**
* This function uses xdotool to put the mouse button associated with int button
* into the 'down' state. Checks to see if the window exists so that we don't 
* click on anything that doesn't belong to us.
*/
bool mouseDown(std::string window_name, int button){ 
  //only mouse down button 1, 2, or 3.
  if(button == 1 || button == 2 || button == 3){ 
    //only mouse down if the window exists (bring into focus and mousedown)
    if(windowExists(window_name)){ 
      std::string command = "wmctrl -R " + window_name  
                      + " &&  xdotool mousedown " + std::to_string(button);
      system(command.c_str());
      return true;
    }
    else{
      std::cout << "Tried to mouse down on a nonexistent window." << std::endl;
      return false;
    }
  }
  else{
      std::cout << "ERROR: tried to click nonexistent mouse button " 
                << button << std::endl;
      return false;
  }
}

/**
* This function uses xdotool to put the mouse button associated with the int 
* button into the 'up' state. Checks to see if the window exists so that we 
* don't click on anything that doesn't belong to us.
*/
bool mouseUp(std::string window_name, int button){
  //only mouseup on buttons 1,2,3
  if(button == 1 || button == 2 || button == 3){
    //Only mouse up if the window exists (give the window focus and mouseup) 
    if(windowExists(window_name)){ 
      std::string command = "wmctrl -R " + window_name 
                + " &&  xdotool mouseup " + std::to_string(button);
      system(command.c_str()); 
      return true; 
    }
    else{
      std::cout << "Tried to mouse up on a nonexistent window" << std::endl;
      return false;
    }
  }
  else{
    std::cout << "ERROR: tried to click mouse button " << button << std::endl;
    return false;
  }
}

/**
* This function mousedowns then mouseups to simulate a click. 
*/
bool click(std::string window_name, int button){
  bool success = mouseDown(window_name, button);
  if(!success){
    return false;
  }
  success = mouseUp(window_name, button);
  return success;
}

/**
* This function moves the mouse to moved_mouse_x, moved_mouse_y, clamping 
* between x_start x_end and y_start y_end.
* NOTE: EXPECTS MOVED VARIALBES ALREADY IN WINDOW COORDINATES
* This is done in the takeAction function
*/
bool mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y, 
                 int x_start, int x_end, int y_start, int y_end, bool no_clamp){

  //only move the mouse if the window exists. (get focus and mousemove.)
  if(windowExists(window_name)){
    std::vector<int> current_mouse_position = getMouseLocation();

    if(current_mouse_position.size() < 2){
      return false;
    }

    //Explicit clamping.
    if(moved_mouse_x > x_end || moved_mouse_x < x_start){
      std::cout << "Attempted to move outside of the window bounds." << std::endl;
      return false;
    }
    //Explicit clamping
    if(moved_mouse_y > y_end || moved_mouse_y < y_start){
      std::cout << "Attempted to move outside of the window bounds." << std::endl;
      return false;
    }

    if(current_mouse_position[0] == moved_mouse_x && current_mouse_position[1] == moved_mouse_y){
      std::cout << "mouse was already in position. Skipping movement." << std::endl;
    }
    else{
      std::string command = "wmctrl -R " + window_name +
      " &&  xdotool mousemove --sync " + std::to_string(moved_mouse_x) + " "
                                              + std::to_string(moved_mouse_y);
        system(command.c_str());
    }

    return true;
  }
  else{
    std::cout << "Attempted to move mouse on a nonexistent window." 
                << std::endl;
    return false;
  }  
}
/**
* This function sets the height, width, x_start, y_start (upper left coords), 
* x_end, and y_end (lower right) variables of the student's window. These 
* values are used in operations such as mouse movement. returns false on 
* failure and does not set variables. 
*/
bool populateWindowData(std::string window_name, int& height, int& width, 
                        int& x_start, int& x_end, int& y_start, int& y_end){
  if(windowExists(window_name)) {
    std::vector<int> height_vec, width_vec, x_start_vec, y_start_vec;
    // getWindowData returns a vector of ints associated with the query term 
    // (e.g. 'Height')
    height_vec = getWindowData("Height", window_name); 
    width_vec = getWindowData("Width", window_name);   
    //These two values represent the upper left corner
    x_start_vec = getWindowData("Absolute upper-left X", window_name); 
    y_start_vec = getWindowData("Absolute upper-left Y", window_name);
    //The lines below should never return false, as the Height, Width, etc.
    //fields should always be populated if the window exists. The only way that
    // I can see for these to fail would be if xdotool changes or the window 
    // fails/shuts in this block.
    if(height_vec.size() > 0){  height = height_vec[0];}  else{ return false; }
    if(width_vec.size() > 0) {  width  = width_vec[0];}   else{ return false; }
    if(x_start_vec.size() > 0){ x_start= x_start_vec[0];} else{ return false; }
    if(y_start_vec.size() > 0){ y_start= y_start_vec[0];} else{ return false; }

    //These values represent the upper right corner
    x_end = x_start+width; 
    y_end = y_start + height;
    return true;
  }
  else
  {
    std::cout << "Attempted to populate window data using a nonexistent window"
                 << std::endl;
    return false;
  }
}
/**
* This function sets the window variables (using populateWindowData), and 
* mouse_button (pressed) destination (an x,y tuple we are dragging to), and 
* no_clamp (which is currently disabled/false). returns false on failure. 
* It is on the programmer to check.
*/
bool populateClickAndDragValues(nlohmann::json action, std::string window_name, 
      int& window_left, int& window_right, int& window_top, int& window_bottom, int& mouse_button, 
                                bool& no_clamp){
  int height, width;
  //Get the window dimensions.
  populateWindowData(window_name,height,width,window_left,window_right,window_top,window_bottom);


  // if(command.find("no clamp") != std::string::npos){
  //   std::cout << "Multiple windows are not yet supported. (No 'no clamp' option available)" << std::endl;
  //   no_clamp = false;
  // }

  std::string mouse_button_string = action.value("mouse_button", "left");

  if(mouse_button_string == "left"){
    mouse_button = 1;
  }
  else if (mouse_button_string == "middle"){
    mouse_button = 2;
  }
  else if (mouse_button_string == "right"){
    mouse_button = 3;
  }
  else{ //default.
    mouse_button = 1; 
  }
  return true;
}


/**
* Given lines (p1,p2) and (p3,p4), find the intersection point if any. (for use
* in click and drag delta). 
* Based on Paul Bourke's Intersection Point of Two Lines
*/
std::vector<float> getLineIntersectionPoint(std::vector<int> p1, 
    std::vector<int> p2, std::vector<int> p3, std::vector<int> p4) {
// Store the values for fast access and easy
// equations-to-code conversion
  float x1 = p1[0], x2 = p2[0], x3 = p3[0], x4 = p4[0];
  float y1 = p1[1], y2 = p2[1], y3 = p3[1], y4 = p4[1];
 
  float ua_numerator = ((x4-x3)*(y1-y3)) - ((y4-y3)*(x1-x3));
  float ub_numerator = ((x2-x1)*(y1-y3)) - ((y2-y1)*(x1-x3));
  float denominator = ((y4-y3)*(x2-x1)) - ((x4-x3)*(y2-y1));
  float ua = ua_numerator/denominator;
  float ub = ub_numerator / denominator;

  std::vector<float> answer;
  
  //the lines are parallel or coincident
  if(denominator == 0){ 
    return answer;
  }
  if((ua > 0 && ua <= 1) && (ub > 0 && ub <= 1))
  {
    answer.push_back(x1 + ua*(x2-x1));
    answer.push_back(y1 + ua*(y2-y1));
  }
  
  return answer;
}

/**
 *  Returns the location of the mouse as an x, y vector.
 *  Returns empty vector on failure.
 */
std::vector<int> getMouseLocation(){
  std::string mouse_location_string = output_of_system_command("xdotool getmouselocation");
  std::vector<int> xy = extractIntsFromString(mouse_location_string);

  if(xy.size() < 2){
    std::vector<int> empty;
    return empty;
  }

  return xy;
}

/**
* The 'delta' version of the click and drag command. This function moves an xy
* distance from a startpoint. This distance is 'wrapping', so if it is outside 
* of the window, we mouseup, return to the start position, mousedown, and then
* move again. We give a one pixel border at each side of the window and clamp 
* using that value to avoid accidental resizing.
*/
bool clickAndDragDelta(std::string window_name, nlohmann::json action){
  //get the values of the student's window.
  int x_start, x_end, y_start, y_end, mouse_button; 
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(action, window_name, x_start, 
                      x_end, y_start, y_end, mouse_button, no_clamp);
  
  //if we can't populate the click and drag values, do nothing.
  if(!success){ 
    std::cout << "Could not populate the click and drag values."<< std::endl;
    return false;
  }
  
  //Define the corners of our window. (We use vectors as 2d points.)
  std::vector<int> upper_left, upper_right, lower_left, lower_right; 
  upper_left.push_back(x_start); upper_left.push_back(y_start);
  upper_right.push_back(x_end); upper_right.push_back(y_start);
  lower_left.push_back(x_start); lower_left.push_back(y_end);
  lower_right.push_back(x_end); lower_right.push_back(y_end);

  
  //delta version, 2 values movement x and movement y.
  int amt_x_movement_remaining = action.value("x_distance", 0);
  int amt_y_movement_remaining = action.value("y_distance", 0);
  std::string start_location   = action.value("start_location", "center");

  //This shouldn't fail unless there isn't a mouse.
  std::string mouse_location_string = output_of_system_command("xdotool getmouselocation"); 
  std::vector<int> xy = extractIntsFromString(mouse_location_string);                      
  
  //if the mouse isn't detected, fail.
  if(xy.size() < 2){ 
    std::cout << "Mouse coordinates couldn't be found. Mouse undetected." 
                << std::endl;
    return false;
  }
  //get the current mouse location
  int start_mouse_x = xy[0];
  int start_mouse_y = xy[1];
  //clamp the mouse within the screen (and move in by a pixel).
  clamp(start_mouse_x, x_start+1, x_end-1); 
  clamp(start_mouse_y, y_start+1, y_end-1);

  //get the center of the window
  int width  = x_end - x_start;
  int height = y_end - y_start;
  int x_middle = x_start + (width/2);
  int y_middle = y_start+(height/2);

  //NOTE: check my arithmetic. 
  /**
  * The process that this algorithm goes through is as follows:
  * 1) Determine the slope of the dragged line and its distance.
  * 2) while we have not traversed the necessary distance
  * 3) project a line from the current mouse position towards the end 
  *      position with length equal to the remaining distance. 
  * 4) Now that we have a line segment defined, find where/if it intersects
  *      any of the window's edges
  * 5) if it does intersect, cut it off at the point of intersection, and only
  *      drag that far. Else, if it doesn't intersect, we can assume we are 
  *      inside of the window, due to the clamp, and can drag.
  * 6) update remaining distance and continue to loop.
  */

  //rise / run
  float slope=(float)amt_y_movement_remaining/(float)amt_x_movement_remaining;
  float total_distance_needed = sqrt(pow(amt_x_movement_remaining, 2) 
                                    + pow (amt_y_movement_remaining, 2)); 

  //remaining distance needed.
  float remaining_distance_needed = total_distance_needed; 

  int action_start_x = (start_location == "current") ? start_mouse_x : x_middle;
  int action_start_y = (start_location == "current") ? start_mouse_y : y_middle;

  std::cout << "start x " << start_mouse_x << " our x " << action_start_x;
  std::cout << "start y " << start_mouse_y << " our y " << action_start_y;

  //The functions called within this loop will not fire if the window doesn't 
  // exist. This check just short circuits to avoid additional printing.
  while(remaining_distance_needed >= 1 && windowExists(window_name)){ 
    int curr_x = action_start_x;                                             
    int curr_y = action_start_y;                                              
    int moved_mouse_x, moved_mouse_y;
    //reset the mouse to the start location.
    mouse_move(window_name, action_start_x, action_start_y, x_start, x_end, y_start, y_end,
                                                                        false); 
    //determine how far we've come.
    float fraction_of_distance_remaining = remaining_distance_needed 
                                            / total_distance_needed; 
    //project in the direction of the move to find the end of our line segment.
    float projected_x = action_start_x + (amt_x_movement_remaining * fraction_of_distance_remaining); 
    float projected_y = action_start_y + (amt_y_movement_remaining * fraction_of_distance_remaining);  

    //we are using vectors as 2d points.
    std::vector<int> current_point, projected_point;  
    current_point.push_back(curr_x); current_point.push_back(curr_y);
    projected_point.push_back(projected_x); 
    projected_point.push_back(projected_y);

    std::vector<float> intersection_point; 
    intersection_point=getLineIntersectionPoint(current_point,projected_point,
                                                      upper_left, upper_right);
    

    /**
    * TODO make this block smaller. 
    * These if statements just test all edges of the window against our 
    * projected line.
    */

    //found is just a quick short-circuit to keep the code from ballooning.
    bool found = false; 
    if(intersection_point.size() != 0){ //TOP
      std::cout << "intersected top" << std::endl;
      moved_mouse_x = (int)intersection_point[0]; 
      moved_mouse_y = (int)intersection_point[1];
      found = true;
    }

    if(!found) //RIGHT
    {
      intersection_point = getLineIntersectionPoint(current_point, 
                            projected_point, upper_right, lower_right);
      if(intersection_point.size() != 0){ 
        std::cout << "intersected right" << std::endl;
        moved_mouse_x = (int)intersection_point[0]; 
        moved_mouse_y = (int)intersection_point[1];
        found = true;
      }
    }

    if(!found) //BOTTOM
    {
      intersection_point = getLineIntersectionPoint(current_point, 
                              projected_point, lower_right, lower_left);
      if(intersection_point.size() != 0){
        std::cout << "intersected bottom" << std::endl;
        moved_mouse_x = (int)intersection_point[0]; 
        moved_mouse_y = (int)intersection_point[1];
        found = true;
      }
    }

    if(!found) //LEFT
    {
      intersection_point = getLineIntersectionPoint(current_point,
                             projected_point, lower_left, upper_left);
      if(intersection_point.size() != 0){
        std::cout << "intersected left" << std::endl;
        moved_mouse_x = (int)intersection_point[0]; 
        moved_mouse_y = (int)intersection_point[1];
        found = true;
      }
    }

    //if we didn't intersect, we are inside of the box (guaranteed by clamp)
    // so we can move freely.
    if(!found) 
    {
      std::cout << "No intersection at all"<< std::endl;
      moved_mouse_x = projected_x;
      moved_mouse_y = projected_y;
    }

    //the distance we can move
    float distance_of_move = sqrt(pow(moved_mouse_x - action_start_x, 2) 
                                    + pow (moved_mouse_y - action_start_y, 2)); 
    //we are moving distance_of_move
    remaining_distance_needed -= distance_of_move; 
    std::cout << "after the move, we had " << remaining_distance_needed 
                                          << " distance left " << std::endl;
    mouseDown(window_name,mouse_button); //click
    mouse_move(window_name, moved_mouse_x, moved_mouse_y,x_start, x_end, //drag
                                                        y_start, y_end, false); 
    mouseUp(window_name,mouse_button); //release
  } //end loop.

  //to preserve backwards compatibility.
  if(start_location != "current"){
    //put the mouse back where we found it.
    mouse_move(window_name, start_mouse_x, start_mouse_y, x_start, x_end, y_start, y_end,false);
  }

  return true;
}

/**
* Click and drag absolute: move to a relative coordinate within the window
* windowname, clamped.
*/
bool clickAndDragAbsolute(std::string window_name, nlohmann::json action){
   //populate the window variables. 
  int x_start, x_end, y_start, y_end, mouse_button;
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(action, window_name, x_start,
                       x_end, y_start, y_end, mouse_button, no_clamp);
  
  //if we couldn't populate the values, do nothing (window doesn't exist)
  if(!success){ 
    std::cout << "Click and drag unsuccessful due to failure to populate click and drag values." << std::endl;
    return false;
  }


  int start_x_position = action.value("start_x", -1);
  int start_y_position = action.value("start_y", -1);

  int end_x_position = action.value("end_x", -1);
  int end_y_position = action.value("end_y", -1);

  if (start_x_position == end_x_position && start_y_position == end_y_position){
    std::cout << "Error, the click and drag action did not specify movement." << std::endl;
    return false;
  }

  if(end_x_position == -1 || end_y_position == -1){
    std::cout << "ERROR: the click and drag action must include an ending position" << std::endl;
    return false;
  }
  


  //get the mouse into starting position if they are specified.
  if(start_x_position != -1 && start_y_position != -1){ 
    start_x_position = start_x_position + x_start;
    start_y_position = start_y_position + y_start;

    //don't move out of the window.
    clamp(start_x_position, x_start, x_end); 
    clamp(start_y_position, y_start, y_end);
    mouse_move(window_name, start_x_position, start_y_position,x_start, x_end, 
                                                        y_start, y_end, false); 
  }
  
  end_x_position = end_x_position + x_start;
  end_y_position = end_y_position + y_start;
  
  //clamp the end position so we don't exit the window. 
  clamp(end_x_position, x_start, x_end); 
  clamp(end_y_position, y_start, y_end);

  //These functions won't do anything if the window doesn't exist. 
  mouseDown(window_name,mouse_button); 
  mouse_move(window_name, end_x_position, end_y_position,x_start, x_end, 
                                                  y_start, y_end, false);
  mouseUp(window_name,mouse_button);  

  return true;
}

/**
* Centers the mouse on the window associated with windowname if it exists.
*/
bool centerMouse(std::string window_name){
  //populate the window vals to get the center.
  int height, width, x_start, x_end, y_start, y_end; 
  bool success = populateWindowData(window_name, height, width, x_start,x_end,
                                                                y_start,y_end);
  int x_middle = x_start + (width/2);
  int y_middle = y_start+(height/2);

  //wait until the last moment to check window existence.
  if(success && windowExists(window_name)){ 
    success = mouse_move(window_name, x_middle, y_middle, x_start, x_end, y_start, y_end, false);
    return success;
  }
  else{
    std::cout << "Attempted to center mouse on a nonexistent window" 
                << std::endl;
    return false;
  }
}

/**
* Moves the mouse to the upper left of the window associated with windowname 
* if it exists.
*/
bool moveMouseToOrigin(std::string window_name){
  //populate the window vals to get the center.
  int height, width, x_start, x_end, y_start, y_end; 
  bool success = populateWindowData(window_name, height, width, x_start,x_end,
                                                                y_start,y_end);

  //wait until the last moment to check window existence.
  if(success&& windowExists(window_name)){ 

    success = mouse_move(window_name, x_start, y_start, x_start, x_end, y_start, y_end, false);
    return success;
  }
  else{
    std::cout << "Attempted to move mouse to origin of nonexistent window" 
                << std::endl;
    return false;
  }
}

/**
* This function was written to allow users to type special keys.
* The function wraps the xdotool key command, which can multipress
* keys. (e.g. ctrl+alt+del)
*/
bool key(std::string toType, std::string window_name)
{
  //get window focus then type the string toType.
  std::string internal_command = "wmctrl -R " + window_name
                                    +" &&  xdotool key " + toType;
  //for number of presses requested, check that the window exists and that we
  //have something to type.
  if(windowExists(window_name) && toType != ""){
    system(internal_command.c_str());
    return true;
  } else{
    return false;
  }
}

/**
* This function processes the 'type' action, which types a quoted string one 
* character at a time an optional number of times with an optional delay 
* between repetitions. Because of the delay, we need all parameters necessary 
* for a call to execute.cpp's delayAndMemCheck.
*/
bool type(std::string toType, float delay, int presses, std::string window_name, int childPID, 
  float &elapsed, float& next_checkpoint, float seconds_to_run, 
  int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill,
  std::ostream &logfile){

  delay = delay * 1000000; 
  
  toType = "\"" + toType + "\"";
  
  //get window focus then type the string toType.
  std::string internal_command = "wmctrl -R " + window_name 
                                    +" &&  xdotool type " + toType; 
  
  bool killed = false;

  //for number of presses requested, check that the window exists and that we 
  //have something to type.
  for(int i = 0; i < presses; i++){ 
    if(windowExists(window_name) && toType != ""){ 
      system(internal_command.c_str());
    }
    else{
      std::cout << "Attempted to type on nonexistent window" << std::endl;
      return false;
    }
    //allow this to run so that delays occur as expected.
    if(i != presses-1){ 
      int max_rss_memory = 0;
      killed = delay_and_mem_check(delay, childPID, elapsed, next_checkpoint, 
        seconds_to_run, rss_memory, max_rss_memory, allowed_rss_memory, memory_kill,time_kill, logfile);
    }
    if(killed){
      return false;
    }
  }
  return true;
}

/**
* This function defines which actions are inherently bound for a GUI/window.
**/
bool isWindowedAction(const nlohmann::json action){
  std::string action_str = action.value("action", "");
  if (action_str == ""){
    std::cout << "ERROR: poorly formatted action (no 'action' type specified)" << std::endl;
    return false;
  }
  else{

    if(action_str.find("screenshot") != std::string::npos){
      return true;
    }
    else if(action_str.find("type") != std::string::npos){
      return true;
    }
    else if(action_str.find("key") != std::string::npos){
      return true;
    }
    else if(action_str.find("click and drag") != std::string::npos){
      return true;
    }
    else if(action_str.find("click") != std::string::npos){
      return true;
    }
    else if(action_str.find("move mouse") != std::string::npos){
      return true;
    }
    else if(action_str.find("center") != std::string::npos){
      return true;
    }
    else if(action_str.find("origin") != std::string::npos){
      return true;
    }
    else{
      return false;
    }
  }


}


/**
* The central routing function for for all actions. Takes in a vector of 
* actions and the # of actions taken thus far. It then passes the current 
* action to be taken through tests to see which function to route to.
* This function requires all parameters to for execute.cpp's delayAndMemCheck
* function. 
*/
void takeAction(const std::vector<nlohmann::json>& actions, int& actions_taken, 
  std::string window_name, int childPID, 
  float &elapsed, float& next_checkpoint, float seconds_to_run, 
  int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill,
  std::ostream &logfile){

  //We get the window data at every step in case it has changed size.

  //if we make it past this check, we'll assume an action has been taken.
  if(!windowExists(window_name)){ 
    return;
  }
  
  nlohmann::json action = actions[actions_taken];
  
  // std::vector<std::string> vec = stringOrArrayOfStrings(action, "action");

  // if (vec.size() == 0){
  //   std::cout << "ERROR: poorly formatted action (no 'action' type specified)" << std::endl;
  //   action_name = "INVALID: NAME MISSING";
  // }
  // else{
  //     std::string action_name = vec[0];
  // }

  std::string action_name = action.value("action", "ACTION_NOT_SPECIFIED");

  std::cout <<"Taking action "<<actions_taken+1<<" of "<<actions.size() <<": "<< action_name<< std::endl;

  float delay_time = 0;
  bool success = false;
  //DELAY            
  if(action_name == "delay"){
    float time_in_secs = action.value("seconds", 0);
    delay_time = delay(time_in_secs);
    success = true;
  }
  //SCREENSHOT
  else if(action_name == "screenshot"){ 
    std::string screenshot_name = action["name"];
    success = screenshot(window_name, screenshot_name);
  }
  //TYPE
  else if(action_name == "type"){ 

    float delay_in_secs = action["delay_in_seconds"];
    int presses = action["presses"];
    std::string string_to_type = action["string"];

    success = type(string_to_type, delay_in_secs, presses, window_name,childPID,elapsed, next_checkpoint, 
      seconds_to_run, rss_memory, allowed_rss_memory, memory_kill, time_kill, logfile);
  }
  //KEY
  else if(action_name == "key"){
    std::string key_to_type = action["key_combination"];
    success = key(key_to_type, window_name);
  }
  //CLICK AND DRAG    
  else if(action_name == "click and drag"){ 
    success = clickAndDragAbsolute(window_name,action);
  }
  else if(action_name == "click and drag delta"){
    success = clickAndDragDelta(window_name,action);
  }
  //CLICK
  else if(action_name == "click"){ 
    std::string mouse_button = action["mouse_button"];
    if(mouse_button == "left"){
      success = click(window_name, 1);
    }
    else if(mouse_button == "middle"){
      success = click(window_name, 2);
    }
    else if(mouse_button == "right"){
      success = click(window_name, 3);
    }
    else{
      success = click(window_name, 1);
    }
  }
  //MOUSE MOVE
  else if(action_name == "move mouse"){
    //TODO: implement later if deemed prudent.
    bool no_clamp = false;
    
    int moved_x = action["end_x"];
    int moved_y = action["end_y"];

    int height, width, x_start, x_end, y_start, y_end;
    bool populated = populateWindowData(window_name, height, width, x_start, x_end, y_start, y_end);
    if(populated){
        moved_x += x_start;
        moved_y += y_start;
        success = mouse_move(window_name, moved_x, moved_y, x_start, x_end, y_start, y_end, no_clamp);
    }
  }
  //CENTER
  else if(action_name == "center"){ 
    success = centerMouse(window_name);
  }
  //ORIGIN
  else if(action_name == "origin"){ 
    success = moveMouseToOrigin(window_name);
  }
  else if(action_name == "gif"){ 
    std::string gif_name = action["name"];
    float duration_in_seconds = action["seconds"];
    int fps = action["frames_per_second"];
    bool save_pngs = action["preserve_individual_frames"];

    success = make_gif(window_name, gif_name, duration_in_seconds, fps, save_pngs,
                        childPID, elapsed, next_checkpoint, seconds_to_run, 
                        rss_memory, allowed_rss_memory, memory_kill, time_kill,
                        logfile);
  }
   //BAD COMMAND
  else{
    std::cout << "ERROR: ill formatted action: " << actions[actions_taken] << std::endl;
  }

  if(success){
    std::cout << "The action was successful" << std::endl;
    actions_taken++;
  }else{
    std::cout << "The action was unsuccessful" << std::endl;
  }

  int max_rss_memory = 0;
  delay_and_mem_check(delay_time, childPID, elapsed, next_checkpoint, 
    seconds_to_run, rss_memory, max_rss_memory, allowed_rss_memory, memory_kill, time_kill,logfile);
}
