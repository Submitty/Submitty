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


std::vector<int> extract_ints_from_string(std::string input)
{


    std::vector<int> ints;
    std::string myReg = ".*?(-?[0-9]{1,12})(.*)[\\s \\S]*";

    std::regex regex(myReg);
    std::smatch match;
    while (std::regex_match(input, match, regex))
    {
      ints.push_back(stoi(match[1].str()));
      input = match[2];
    }
    return ints;
}

std::vector<std::string> get_window_names_associated_with_pid(int pid)
{
  std::cout << pid << std::endl;
  std::string pidQuery = "pgrep -P ";
  pidQuery +=  std::to_string(pid);
  std::cout << "querying with: " << pidQuery << std::endl;
  std::string children = output_of_system_command(pidQuery.c_str());
  std::cout << "Associated pids " << children << std::endl;
  std::vector<int> ints = extract_ints_from_string(children);
  for(int i = 0; i < ints.size(); i++)
  {
    std::string pidQuery = "pgrep -P ";
    pidQuery +=  ints[i];
    children = output_of_system_command(pidQuery.c_str());
    std::cout << "pids associated with " << ints[i] << ": " << children << std::endl;
  }
  std::vector<std::string> associatedWindows;
  std::string activeWindows = output_of_system_command("wmctrl -lp"); //returns list of active windows with pid.
  std::istringstream stream(activeWindows);
  std::string window;    
  std::smatch match;
  std::cout << "Ideal pid is " << pid << std::endl;
  while (std::getline(stream, window)) {
    std::cout << "Processing: " << window << std::endl;
    //remove the first two columns 
    std::string myReg = "(.+)[ \\t]+(.*)"; //remove everthing before one or more spaces or tabs.
    std::regex regex(myReg);
    if(std::regex_match(window, match, regex)){ //remove the first two columns.
      window = match[2];
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
    std::cout << "broken down to " << window << std::endl;

    if(std::regex_match(window, match, regex)){ //get the third collumn
      int windowPid = stoi(window);
      std::cout << "\tWindowpid was " << windowPid << std::endl;
      if(windowPid != pid){
        continue;
      }
      else{
        window = match[2];
      }
    }
    else{
      continue;
    }
    if(std::regex_match(window, match, regex)){
      associatedWindows.push_back(match[2]);
    }
  }
  return associatedWindows;
}


std::vector<int> get_window_data(std::string data_string, std::string window_name)
{
    std::string command = "xwininfo -name \"" + window_name + "\" | grep \"" + data_string +"\"";
    std::string valueString = output_of_system_command(command.c_str());
    return extract_ints_from_string(valueString);  
}

void initialize_window(std::string& window_name, int pid)
{
  std::cout << "initializing window." << std::endl;
  //std::vector<std::string> windows = get_window_names_associated_with_pid(pid);
  // if(windows.size() == 0)
  // {
  //   return;
  // }
  // else{
  //   std::cout<<"Windows associated with " << pid << std::endl;
  //   for(int i = 0; i < windows.size(); i++)
  //   {
  //     std::cout << windows[i] <<std::endl;
  //   }
  // }
  std::string windowQuery = "xdotool getwindowfocus getwindowname"; //+ windows[0];
  window_name = output_of_system_command(windowQuery.c_str()); //get the window name for graphics programs.
  window_name.erase(std::remove(window_name.begin(), window_name.end(), '\n'), window_name.end()); //trim.
  std::cout << "Window name was " << window_name << std::endl;
}

//modifies pos to window border if necessary. Returns remainder.
int clamp(int& pos, int min, int max)
{
  int leftOver = 0;
  if(pos < min)
  {
    leftOver = pos - min;
    pos = min;
  }
  if(pos > max)
  {
    leftOver = pos - max; 
    pos = max;
  }
  return leftOver;
}

float delay(std::string command)
{
  std::vector<int> numbers = extract_ints_from_string(command);
  if (numbers.size() > 0)
  {
    if(numbers[0] < 0)
    {
      numbers[0] = abs(numbers[0]);
    }
    std::cout << "Delaying for " << numbers[0] << " seconds." << std::endl;
    int sleep_time_secs = numbers[0];
    float sleep_time_micro = 1000000 * sleep_time_secs; //TODO turn this into a loop w/ memory checks.  
    return sleep_time_micro;
  }
}

void screenshot(std::string window_name, int& number_of_screenshots)
{
  std::string command = "wmctrl -R " + window_name + " && scrot "  + std::to_string(number_of_screenshots) + ".png -u";
  system(command.c_str());
  number_of_screenshots = number_of_screenshots + 1;
}

void mouse_down(std::string window_name, int button)
{
  if(button == 1 || button == 2 || button == 3)
  {
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mousedown " + std::to_string(button);
    system(command.c_str());  
  }
  std::cout << "ERROR: tried to click mouse button " << button << std::endl;
}

void mouse_up(std::string window_name, int button)
{
  if(button == 1 || button == 2 || button == 3)
  {
    std::string command = "wmctrl -R " + window_name + " &&  xdotool mouseup " + std::to_string(button);
    system(command.c_str());  
  }
  std::cout << "ERROR: tried to click mouse button " << button << std::endl;
}

void click(std::string window_name, int button)
{
  mouse_down(window_name, button);
  mouse_up(window_name, button);
}

void mouse_move(std::string window_name, int moved_mouse_x, int moved_mouse_y, int x_start, int x_end, int y_start, int y_end)
{
  clamp(moved_mouse_x, x_start, x_end);
  clamp(moved_mouse_y, y_start, y_end);
  std::string command = "wmctrl -R " + window_name + " &&  xdotool mousemove --sync "
                     + std::to_string(moved_mouse_x) + " " + std::to_string(moved_mouse_y);  
  system(command.c_str());
}


void click_and_drag(std::string window_name, std::string command)
{
  int height = get_window_data("Height", window_name)[0];
  int width = get_window_data("Width", window_name)[0];
  int x_start = get_window_data("Absolute upper-left X", window_name)[0]; //These values represent the upper left corner
  int y_start = get_window_data("Absolute upper-left Y", window_name)[0];
  int x_end = x_start+width; //These values represent the upper right corner
  int y_end = y_start + height;

  std::vector<int> coords = extract_ints_from_string(command);
  if(coords.size() == 0)
  {
    std::cout << "ERROR: The line " << command << " does not specify two coordinates." <<std::endl;
    return;
  }
  
  bool no_clamp = false;
  if(command.find("no clamp") != std::string::npos)
  {
    std::cout << "Multiple windows are not yet supported." << std::endl;
    //no_clamp = true;
  }

  if(command.find("delta") != std::string::npos)
  {
    //delta version, 2 values movement x and movement y.
    int amt_x_movement_remaining = coords[0];
    int amt_y_movement_remaining = coords[1];


    //For now, we're going to force the mouse to start inside of the window by at least a pixel.
    std::string mouse_location_string = output_of_system_command("xdotool getmouselocation");
    std::vector<int> xy = extract_ints_from_string(mouse_location_string);
    int mouse_x = xy[0];
    int mouse_y = xy[1];
    clamp(mouse_x, x_start+1, x_end-1); //move in by a pixel.
    clamp(mouse_y, y_start+1, y_end-1);

    float slope = (float)amt_y_movement_remaining / (float)amt_x_movement_remaining;
    std::cout << "Slope was " << slope << std::endl;

    float total_distance_needed = sqrt(pow(amt_x_movement_remaining, 2) + pow (amt_y_movement_remaining, 2));
    std::cout << "Distance needed was " << total_distance_needed << std::endl;
    float distance_needed = total_distance_needed;
    //while loop with a clamp.
    int curr_x = 0;
    int curr_y = 0;
    while(distance_needed >= 1)
    {
      mouse_move(window_name, mouse_x, mouse_y, x_start, x_end, y_start, y_end);
      
      int xStep = x_end-mouse_x; //This can be xStart if we're moving negatively
      float distance_of_move = sqrt(pow(xStep, 2) + pow (xStep*slope, 2));
      
      if(distance_of_move > distance_needed)
      {
        distance_of_move = distance_needed;
        xStep = total_distance_needed - curr_x;
      }

      distance_needed -= distance_of_move;

      mouse_down(window_name,1);

      int moved_mouse_x = mouse_x+xStep;
      int moved_mouse_y = mouse_y + (xStep * slope);
      
      mouse_move(window_name, moved_mouse_x, moved_mouse_y,x_start, x_end, y_start, y_end);

      mouse_up(window_name,1);
      
      curr_x += xStep;
      curr_y += (xStep*slope);
    }
  }
  else
  {
    int start_x, start_y, end_x, end_y;
    if(coords.size() >3) //get the mouse into starting position.
    {
      start_x = coords[0] + x_start;
      start_y = coords[1] + y_start;
      end_x   = coords[2] + x_start;
      end_y   = coords[3] + y_start;
      //reset logic 
      
      clamp(start_x, x_start, x_end);//returns remainder or zero if fine.
      clamp(start_y, y_start, y_end);
      mouse_move(window_name, start_x, start_y,x_start, x_end, y_start, y_end);
    }
    else
    {
      end_x = coords[0] + x_start;
      end_y = coords[1] + y_start;
    }
    if(!no_clamp)
    {
      clamp(end_x, x_start, x_end); 
      clamp(end_y, y_start, y_end);
    }
    mouse_down(window_name,1);
    mouse_move(window_name, end_x, end_y,x_start, x_end, y_start, y_end);
    mouse_up(window_name,1);  
  }
}

void type(std::string command, std::string window_name, int childPID, float &elapsed, float& next_checkpoint, 
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill)
{
  int presses = 1;
  float delay = 100000;
  std::string toType = "";
  std::vector<int> values = extract_ints_from_string(command);
  if(values.size() > 0)
  {
    presses = values[0];
  }
  if(values.size() > 1)
  {
    delay = values[1] * 1000000;
  }
  std::string myReg = ".*?(\".*?\").*"; //anything (lazy) followed by anything between quotes (lazy)
                                        //followed by anything (greedy)
  std::regex regex(myReg);
  std::smatch match;
  if(std::regex_match(command, match, regex))
  { 
    toType = match[1];  
  }
  std::string internal_command = "wmctrl -R " + window_name + " &&  xdotool type " + toType; 
  for(int i = 0; i < presses; i++)
  {
    std::cout << "executed." << internal_command << std::endl;
    if(toType.length() > 0)
    {
      system(internal_command.c_str());
    }
    else
    {
      std::cout << "ERROR: The line " << command << " contained no quoted string." <<std::endl;
    }
    if(i != presses-1)
    {
      delay_and_mem_check(delay, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);   
    }
  }
}

//returns delay time
void takeAction(const std::vector<std::string>& actions, int& actions_taken, int& number_of_screenshots, 
                std::string window_name, int childPID, float &elapsed, float& next_checkpoint, 
                float seconds_to_run, int& rss_memory, int allowed_rss_memory, int& memory_kill, int& time_kill)
{
  //We get the window data at every step in case it has changed size.
  float delay_time = 0;  
  std::cout<<"Taking action " << actions_taken+1 << " of " << actions.size() << ": " << actions[actions_taken]<< std::endl;
  if(actions[actions_taken].find("delay") != std::string::npos)
  {
    delay_time = delay(actions[actions_taken]);
  }
  else if(actions[actions_taken].find("screenshot") != std::string::npos)
  {
    screenshot(window_name, number_of_screenshots);
  }
  else if(actions[actions_taken].find("type") != std::string::npos)
  {
    type(actions[actions_taken], window_name, childPID, elapsed, next_checkpoint, 
                seconds_to_run, rss_memory, allowed_rss_memory, memory_kill, time_kill);
  }
  else if(actions[actions_taken].find("click and drag") != std::string::npos)
  {    
    click_and_drag(window_name,actions[actions_taken]);
  }
  else if(actions[actions_taken].find("click") != std::string::npos)
  {
    std::vector<int> button = extract_ints_from_string(actions[actions_taken]);
    if(button.size() >0 && button[0] >0 && button[0] <= 3)
    {
      click(window_name, button[0]);
    }
    else
    {
      click(window_name, 1);
    }
  }
  else if(actions[actions_taken].find("xdotool") != std::string::npos)
  {
    system(actions[actions_taken].c_str()); //This should be better scrubbed.
  }
  else
  {
    std::cout << "ERROR: ill formatted command: " << actions[actions_taken] << std::endl;    
  }
  actions_taken++;
  delay_and_mem_check(delay_time, childPID, elapsed, next_checkpoint, seconds_to_run, 
                    rss_memory, allowed_rss_memory, memory_kill, time_kill);   
}


