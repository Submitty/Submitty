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

#include <set>
#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <regex>

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

std::vector<int> getPidsAssociatedWithPid(int pid)
{
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
std::vector<std::string> getWindowNameAssociatedWithPid(int pid)
{
  std::cout << "Attempting to find a window associated with pid: " << pid 
            << std::endl;

  /*
  * At the moment, this method finds any window names associated with the 
  * child's pid. This works fine. However, it is conceivable that the child 
  * could fork/generate a window with its own pid. In this case we could 
  * recursively traverse the pid tree below our child and gather them up. We 
  * could then match this list of pids against the pids which own the current 
  * list of active windows. This extension wouldn't be difficult, but I wonder 
  * whether we want to support processes forking and then having their children
  * create new windows. Regardless, please disregard the chunk of code below, 
  * which just has some of the commands needed to exend the program in that 
  * direction.
  */
  getPidsAssociatedWithPid(pid);
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

std::set<std::string> snapshotOfActiveWindows()
{
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
void initializeWindow(std::string& window_name, int pid, std::set<std::string>& active_windows){
  //get the window names associated with our pid.
  // std::vector<std::string> windows = getWindowNameAssociatedWithPid(pid); 
  
  std::set<std::string> current_windows = snapshotOfActiveWindows();
  // std::vector<std::string> candidates;

  std::set<std::string>::iterator it;
  for (it = current_windows.begin(); it != current_windows.end(); ++it)
  {
    if(active_windows.find(*it) == active_windows.end())
    {
      window_name = *it;
      break;
    }
  }

  if(window_name != "")
  {
    std::cout << "We found the window " << window_name << std::endl;
    return;
  }
  else
  {
    return; 
  }

  //Code left on purpose; could be useful in future development
  // //if none exist, do not set the window_name variable                                                                                
  // if(windows.size() == 0){ 
  //   std::cout << "Initialization failed..." << std::endl;
  //   return;
  // }
  // else{
  //   //if a window exists, default to using the first entry in the vector. 
  //   std::cout << "We found the window " << windows[0] << std::endl;
  //   window_name = windows[0];
  // }
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
* This function is responsible for processing the 'delay' action. It extracts 
* the number of seconds that we are to delay, then converts to microseconds for
* use with usleep, and returns. Actual sleeping is handled by the take_action 
* function, which calls delay_and_memcheck (execute.cpp). 
*/
float delay(std::string command){
  //find any numbers in the delay line (float)
  std::vector<float> numbers = extractFloatsFromString(command); 
  if (numbers.size() > 0){
    //if we have any numbers, assume the first is the amount we want to delay.
    float sleep_time_secs = numbers[0];
    //we can't delay for a negative amount of time.
    if(sleep_time_secs < 0){ 
      sleep_time_secs = abs(sleep_time_secs); 
    }
    //convert to microseconds and return. 
    float sleep_time_micro = 1000000 * sleep_time_secs;  
    return sleep_time_micro;
  }
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
* uses number_of_screenshots to title the image (submitty prepends it with the
*  test #) updates the number of screenshots taken. 
*/
void screenshot(std::string window_name, int& number_of_screenshots){
  if(windowExists(window_name)){ 
    //if the window hasn't crashed, bring it into focus and screenshot it
    std::string command = "wmctrl -R " + window_name + " && scrot "  
                      + std::to_string(number_of_screenshots) + ".png -u";
    system(command.c_str());
    number_of_screenshots = number_of_screenshots + 1;
  }
  else{
    std::cout << "Attempted to screenshot a closed window." << std::endl;
  }
}

/**
* This function uses xdotool to put the mouse button associated with int button
* into the 'down' state. Checks to see if the window exists so that we don't 
* click on anything that doesn't belong to us.
*/
void mouseDown(std::string window_name, int button){ 
  //only mouse down button 1, 2, or 3.
  if(button == 1 || button == 2 || button == 3){ 
    //only mouse down if the window exists (bring into focus and mousedown)
    if(windowExists(window_name)){ 
      std::string command = "wmctrl -R " + window_name  
                      + " &&  xdotool mousedown " + std::to_string(button);
      system(command.c_str());  
    }
    else{
      std::cout << "Tried to mouse down on a nonexistent window." << std::endl;
    }
  }
  else{
      std::cout << "ERROR: tried to click nonexistent mouse button " 
                << button << std::endl;
  }
}

/**
* This function uses xdotool to put the mouse button associated with the int 
* button into the 'up' state. Checks to see if the window exists so that we 
* don't click on anything that doesn't belong to us.
*/
void mouseUp(std::string window_name, int button){
  //only mouseup on buttons 1,2,3
  if(button == 1 || button == 2 || button == 3){
    //Only mouse up if the window exists (give the window focus and mouseup) 
    if(windowExists(window_name)){ 
      std::string command = "wmctrl -R " + window_name 
                + " &&  xdotool mouseup " + std::to_string(button);
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
* This function moves the mouse to moved_mouse_x, moved_mouse_y, clamping 
* between x_start x_end and y_start y_end.
* NOTE: EXPECTS MOVED VARIALBES ALREADY IN WINDOW COORDINATES
* This is done in the takeAction function
*/
void mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y, 
                 int x_start, int x_end, int y_start, int y_end, bool no_clamp){

  if(!no_clamp)
  {
    clamp(moved_mouse_x, x_start, x_end); //don't move outside of the window.
    clamp(moved_mouse_y, y_start, y_end);
  }
  //only move the mouse if the window exists. (get focus and mousemove.)
  if(windowExists(window_name)){
    std::string command = "wmctrl -R " + window_name + 
    " &&  xdotool mousemove --sync " + std::to_string(moved_mouse_x) + " " 
                                            + std::to_string(moved_mouse_y);  
    system(command.c_str());
  }
  else{
    std::cout << "Attempted to move mouse on a nonexistent window." 
                << std::endl;
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
bool populateClickAndDragValues(std::string command, std::string window_name, 
      int& x_start, int& x_end, int& y_start, int& y_end, int& mouse_button, 
                                std::vector<int>& destination, bool& no_clamp){
  int height, width;
  populateWindowData(window_name,height,width,x_start,x_end,y_start,y_end);

  destination = extractIntsFromString(command);
  if(destination.size() == 0){
    std::cout << "ERROR: The line " << command 
                << " does not specify two coordinates." <<std::endl;
    return false;
  }
  
  if(command.find("no clamp") != std::string::npos){
    std::cout << "Multiple windows are not yet supported. (No no clamp)"
                 << std::endl;
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
* The 'delta' version of the click and drag command. This function moves an xy
* distance from a startpoint. This distance is 'wrapping', so if it is outside 
* of the window, we mouseup, return to the start position, mousedown, and then
* move again. We give a one pixel border at each side of the window and clamp 
* using that value to avoid accidental resizing.
*/
void clickAndDragDelta(std::string window_name, std::string command){
  //get the values of the student's window.
  int x_start, x_end, y_start, y_end, mouse_button; 
  std::vector<int> coords; 
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(command, window_name, x_start, 
                      x_end, y_start, y_end, mouse_button, coords, no_clamp);
  
  //if we can't populate the click and drag values, do nothing.
  if(!success){ 
    std::cout << "Could not populate the click and drag values."<< std::endl;
    return;
  }
  
  //Define the corners of our window. (We use vectors as 2d points.)
  std::vector<int> upper_left, upper_right, lower_left, lower_right; 
  upper_left.push_back(x_start); upper_left.push_back(y_start);
  upper_right.push_back(x_end); upper_right.push_back(y_start);
  lower_left.push_back(x_start); lower_left.push_back(y_end);
  lower_right.push_back(x_end); lower_right.push_back(y_end);

  
  //delta version, 2 values movement x and movement y.
  int amt_x_movement_remaining = coords[0];
  int amt_y_movement_remaining = coords[1];

  //This shouldn't fail unless there isn't a mouse.
  std::string mouse_location_string = 
    output_of_system_command("xdotool getmouselocation"); 
  std::vector<int> xy = extractIntsFromString(mouse_location_string);                      
  
  //if the mouse isn't detected, fail.
  if(xy.size() < 2){ 
    std::cout << "Mouse coordinates couldn't be found. Mouse undetected." 
                << std::endl;
    return;
  }
  int mouse_x = xy[0];
  int mouse_y = xy[1];
  //clamp the mouse within the screen (and move in by a pixel).
  clamp(mouse_x, x_start+1, x_end-1); 
  clamp(mouse_y, y_start+1, y_end-1);

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

  //The functions called within this loop will not fire if the window doesn't 
  // exist. This check just short circuits to avoid additional printing.
  while(remaining_distance_needed >= 1 && windowExists(window_name)){ 
    int curr_x = mouse_x;                                             
    int curr_y = mouse_y;                                              
    int moved_mouse_x, moved_mouse_y;
    //reset the mouse to the start location.
    mouse_move(window_name, mouse_x, mouse_y, x_start, x_end, y_start, y_end,
                                                                        false); 
    //determine how far we've come.
    float fraction_of_distance_remaining = remaining_distance_needed 
                                            / total_distance_needed; 
    //project in the direction of the move to find the end of our line segment.
    float projected_x = mouse_x + (coords[0] * fraction_of_distance_remaining); 
    float projected_y = mouse_y + (coords[1] * fraction_of_distance_remaining);  

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

    //found is just a quick short circuit to keep the code from ballooning.
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
    float distance_of_move = sqrt(pow(moved_mouse_x - mouse_x, 2) 
                                    + pow (moved_mouse_y - mouse_y, 2)); 
    //we are moving distance_of_move
    remaining_distance_needed -= distance_of_move; 
    std::cout << "after the move, we had " << remaining_distance_needed 
                                          << " distance left " << std::endl;
    mouseDown(window_name,mouse_button); //click
    mouse_move(window_name, moved_mouse_x, moved_mouse_y,x_start, x_end, //drag
                                                        y_start, y_end, false); 
    mouseUp(window_name,mouse_button); //release
  } //end loop.
}

/**
* Click and drag absolute: move to a relative coordinate within the window
* windowname, clamped.
*/
void clickAndDragAbsolute(std::string window_name, std::string command){
   //populate the window variables. 
  int x_start, x_end, y_start, y_end, mouse_button;
  std::vector<int> coords; 
  bool no_clamp = false; 
  bool success = populateClickAndDragValues(command, window_name, x_start,
                       x_end, y_start, y_end, mouse_button, coords, no_clamp);
  
  //if we couldn't populate the values, do nothing (window doesn't exist)
  if(!success){ 
    std::cout << "Click and drag unsuccessful due to failutre to "
                        << "populate click and drag values." << std::endl;
    return;
  }

  int start_x_position, start_y_position, end_x_position, end_y_position;

  //get the mouse into starting position if they are specified.
  if(coords.size() >3){ 
    start_x_position = coords[0] + x_start;
    start_y_position = coords[1] + y_start;
    end_x_position   = coords[2] + x_start;
    end_y_position   = coords[3] + y_start; 

    //don't move out of the window.
    clamp(start_x_position, x_start, x_end); 
    clamp(start_y_position, y_start, y_end);
    mouse_move(window_name, start_x_position, start_y_position,x_start, x_end, 
                                                        y_start, y_end, false); 
  }
  else{
    //If there's no start pos, the first two indices of the vector are the end.
    end_x_position = coords[0] + x_start;
    end_y_position = coords[1] + y_start;
  }
  
  //clamp the end position so we don't exit the window. 
  clamp(end_x_position, x_start, x_end); 
  clamp(end_y_position, y_start, y_end);

  //These functions won't do anything if the window doesn't exist. 
  mouseDown(window_name,mouse_button); 
  mouse_move(window_name, end_x_position, end_y_position,x_start, x_end, 
                                                  y_start, y_end, false);
  mouseUp(window_name,mouse_button);  
}

/**
* Routing function, forwards to delta or absolute click and drag based on 
* command. (Separated due to length.)
*/
void clickAndDrag(std::string window_name, std::string command)
{
  if(command.find("delta") != std::string::npos){
    std::cout << "Routing to delta" << std::endl;
    //these functions check window existence internally.
    clickAndDragDelta(window_name, command); 
  }
  else{
    std::cout << "Routing to absolute " << std::endl;
    clickAndDragAbsolute(window_name, command);
  }
}

/**
* Centers the mouse on the window associated with windowname if it exists.
*/
void centerMouse(std::string window_name){
  //populate the window vals to get the center.
  int height, width, x_start, x_end, y_start, y_end; 
  bool success = populateWindowData(window_name, height, width, x_start,x_end,
                                                                y_start,y_end);
  int x_middle = x_start + width/2;
  int y_middle = y_start+height/2;

  //wait until the last moment to check window existence.
  if(success && windowExists(window_name)){ 
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mousemove"
      + " --sync " + std::to_string(x_middle) + " " + std::to_string(y_middle); 
    system(command.c_str());
  }
  else{
    std::cout << "Attempted to center mouse on a nonexistent window" 
                << std::endl;
  }
}

/**
* Moves the mouse to the upper left of the window associated with windowname 
* if it exists.
*/
void moveMouseToOrigin(std::string window_name){
  //populate the window vals to get the center.
  int height, width, x_start, x_end, y_start, y_end; 
  bool success = populateWindowData(window_name, height, width, x_start,x_end,
                                                                y_start,y_end);

  //wait until the last moment to check window existence.
  if(success&& windowExists(window_name)){ 
    std::string command = "wmctrl -R " + window_name + " &&  xdotool" 
             +" mousemove --sync " + std::to_string(x_start) + " " + 
                                              std::to_string(y_start); 
    system(command.c_str());
  }
  else{
    std::cout << "Attempted to move mouse to origin of nonexistent window" 
                << std::endl;
  }
}

/**
* This function processes the 'type' action, which types a quoted string one 
* character at a time an optional number of times with an optional delay 
* between repetitions. Because of the delay, we need all parameters necessary 
* for a call to execute.cpp's delayAndMemCheck.
*/
void type(std::string command, std::string window_name, int childPID, 
  float &elapsed, float& next_checkpoint, float seconds_to_run, 
  int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill){

  //default number of iterations is 1
  int presses = 1; 
  //default delay between iterations is 1/10th of a second.
  float delay = 100000; 
  std::string toType = ""; 
  //see if there are ints in the string. (optional times pressed/delay)
  std::vector<float> values = extractFloatsFromString(command); 

  if(values.size() > 0){
    //float to int truncation if they entered something incorrect. 
    presses = values[0]; 
  }
  if(values.size() > 1){
    //convert from seconds to microseconds.
    delay = values[1] * 1000000; 
  }
  //The regex below is of the form: anything (lazy) followed by anything 
  // between single quotes (lazy) followed by anything (greedy)
  std::string myReg = ".*?(\'.*?\').*"; 
                                        
  std::regex regex(myReg);
  std::smatch match;
  //get the text to type.
  if(std::regex_match(command, match, regex)){ 
    toType = match[1];  
  }
  if(toType == "")
  {
    //Evem of there is nothing to type, we allow the function to continue so
    // that it delays as expected.
    std::cout << "ERROR: The line " << command << " contained no quoted " <<
                                                     "string." <<std::endl; 
  }   
  //get window focus then type the string toType.
  std::string internal_command = "wmctrl -R " + window_name 
                                    +" &&  xdotool type " + toType; 
  //for number of presses requested, check that the window exists and that we 
  //have something to type.
  for(int i = 0; i < presses; i++){ 
    if(windowExists(window_name) && toType != ""){ 
      system(internal_command.c_str());
    }
    else{
      std::cout << "Attempted to type on nonexistent window" << std::endl;
    }
    //allow this to run so that delays occur as expected.
    if(i != presses-1){ 
      delay_and_mem_check(delay, childPID, elapsed, next_checkpoint, 
        seconds_to_run, rss_memory, allowed_rss_memory, memory_kill,time_kill);
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
void takeAction(const std::vector<std::string>& actions, int& actions_taken, 
  int& number_of_screenshots, std::string window_name, int childPID, 
  float &elapsed, float& next_checkpoint, float seconds_to_run, 
  int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill){
  //We get the window data at every step in case it has changed size.

  //if we make it past this check, we'll assume an action has been taken.
  if(!windowExists(window_name)){ 
    return;
  }
  float delay_time = 0;  
  
  std::cout<<"Taking action " << actions_taken+1 << " of " << actions.size() 
              << ": " << actions[actions_taken]<< std::endl;

  //DELAY            
  if(actions[actions_taken].find("delay") != std::string::npos){ 
    delay_time = delay(actions[actions_taken]);
  }
  //SCREENSHOT
  else if(actions[actions_taken].find("screenshot") != std::string::npos){ 
    screenshot(window_name, number_of_screenshots);
  }
  //TYPE
  else if(actions[actions_taken].find("type") != std::string::npos){ 
    type(actions[actions_taken],window_name,childPID,elapsed, next_checkpoint, 
       seconds_to_run, rss_memory, allowed_rss_memory, memory_kill, time_kill);
  }
  //CLICK AND DRAG    
  else if(actions[actions_taken].find("click and drag") != std::string::npos){ 
    clickAndDrag(window_name,actions[actions_taken]);
  }
  //CLICK
  else if(actions[actions_taken].find("click") != std::string::npos){ 
    std::vector<int> button = extractIntsFromString(actions[actions_taken]);
    if(actions[actions_taken].find("left") != std::string::npos){
      click(window_name, 1);
    }
    else if(actions[actions_taken].find("middle") != std::string::npos){
      click(window_name, 2);
    }
    else if(actions[actions_taken].find("right") != std::string::npos){
      click(window_name, 3);
    }
    else{
      click(window_name, 1);
    }
  }
  //MOUSE MOVE
  else if(actions[actions_taken].find("move mouse") != std::string::npos || 
          actions[actions_taken].find("mouse move") != std::string::npos){
      bool no_clamp = false;
      if(actions[actions_taken].find("no clamp") != std::string::npos){
        no_clamp = true;
      }
      
      std::vector<int> coordinates=extractIntsFromString(actions[actions_taken]);
      if(coordinates.size() >= 2){
      int height, width, x_start, x_end, y_start, y_end;
      bool success = populateWindowData(window_name, height, width, x_start, 
                                                        x_end, y_start, y_end);
      if(success){
          int moved_x = x_start + coordinates[0];
          int moved_y = y_start + coordinates[1];
          mouse_move(window_name, moved_x, moved_y, x_start, x_end, y_start, 
                                                            y_end, no_clamp);
      }
      else{
        std::cout << "No mouse move due to unsuccessful data population."
                    << std::endl;
      }
    }
  }
  //CENTER
  else if(actions[actions_taken].find("center") != std::string::npos){ 
    centerMouse(window_name);
  }
  //ORIGIN
  else if(actions[actions_taken].find("origin") != std::string::npos){ 
    moveMouseToOrigin(window_name);
  }
   //BAD COMMAND
  else{
    std::cout << "ERROR: ill formatted command: " << actions[actions_taken] 
                  << std::endl;    
  }
  actions_taken++;
  delay_and_mem_check(delay_time, childPID, elapsed, next_checkpoint, 
    seconds_to_run, rss_memory, allowed_rss_memory, memory_kill, time_kill);   
}