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


std::vector<int> get_window_data(std::string dataString, std::string windowName)
{
    std::string command = "xwininfo -name \"" + windowName + "\" | grep \"" + dataString +"\"";
    std::string valueString = output_of_system_command(command.c_str());
    return extract_ints_from_string(valueString);  
}

void initialize_window(std::string& windowName, int pid)
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
  windowName = output_of_system_command(windowQuery.c_str()); //get the window name for graphics programs.
  windowName.erase(std::remove(windowName.begin(), windowName.end(), '\n'), windowName.end()); //trim.
  std::cout << "Window name was " << windowName << std::endl;
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

//returns delay time
float takeAction(const std::vector<std::string>& actions, int& actions_taken, 
    int& number_of_screenshots, std::string windowName)
{
  //We get the window data at every step in case it has changed size.
  float delay = 0;
  int height = get_window_data("Height", windowName)[0];
  int width = get_window_data("Width", windowName)[0];
  int xStart = get_window_data("Absolute upper-left X", windowName)[0]; //These values represent the upper left corner
  int yStart = get_window_data("Absolute upper-left Y", windowName)[0];
  int xEnd = xStart+width; //These values represent the upper right corner
  int yEnd = yStart + height;
  std::cout << "The window " << windowName << " has upper left (" << xStart << ", " << yStart << ") and lower right (" << xEnd << ", " << yEnd << ")"<<std::endl; 
  
  std::cout<<"Taking action " << actions_taken+1 << " of " << actions.size() << ": " << actions[actions_taken]<< std::endl;
  if(actions[actions_taken].find("delay") != std::string::npos)
  {
    std::string myReg = ".*?([0-9]+).*";
    std::regex regex(myReg);
    std::smatch match;
    if (std::regex_match(actions[actions_taken], match, regex))
    {
      std::cout << "Delaying for " << match[1].str() << " seconds." << std::endl;
      int sleep_time_secs = stoi(match[1].str());
      int sleep_time_micro = 1000000 * sleep_time_secs; //TODO turn this into a loop w/ memory checks.  
      delay = sleep_time_micro;
    }
  }
  else if(actions[actions_taken].find("screenshot") != std::string::npos)
  {
    std::ostringstream command_stream;
    command_stream << "wmctrl -R " << windowName << " && scrot "  << "_" << number_of_screenshots << ".png -u";
    system(command_stream.str().c_str());
    number_of_screenshots = number_of_screenshots + 1;
  }
  else if(actions[actions_taken].find("type") != std::string::npos)
  {
    int presses = 1;
    float delay = 100000;
    std::string toType = "";
    std::vector<int> values = extract_ints_from_string(actions[actions_taken]);
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
    if(std::regex_match(actions[actions_taken], match, regex))
    { 
      toType = match[1];  
    }
    std::cout << "About to type " << toType << " " << presses << " times with a delay of " 
                    << delay << " microseconds" << std::endl;
    std::ostringstream command_stream;
    command_stream << "wmctrl -R " << windowName << " &&  xdotool type " << toType; 
    for(int i = 0; i < presses; i++)
    {
      std::cout << "executed." << command_stream.str() << std::endl;
      if(toType.length() > 0)
      {
        std::cout << "The string " << toType << " is of size " << toType;
        system(command_stream.str().c_str());
      }
      else
      {
        std::cout << "ERROR: The line " << actions[actions_taken] << " contained no quoted string." <<std::endl;
      }
      if(i != presses-1)
      {
        usleep(delay); //TODO update to delay_and_mem_check
      }
    }
  }
  else if(actions[actions_taken].find("click and drag") != std::string::npos)
  {    
    std::ostringstream command_stream;
    std::vector<int> coords = extract_ints_from_string(actions[actions_taken]);
    if(coords.size() == 0)
    {
      std::cout << "ERROR: The line " <<actions[actions_taken] << " does not specify two coordinates." <<std::endl;
      actions_taken++;
      return delay;
    }
    bool no_clamp = false;
    if(actions[actions_taken].find("no clamp") != std::string::npos)
    {
      no_clamp = true;
    }
    if(actions[actions_taken].find("delta") != std::string::npos)
    {
      //delta version, 2 values movement x and movement y.
      int amt_x_movement_remaining = coords[0];
      int amt_y_movement_remaining = coords[1];


      //For now, we're going to force the mouse to start inside of the window by at least a pixel.
      std::string mouse_location_string = output_of_system_command("xdotool getmouselocation");
      std::vector<int> xy = extract_ints_from_string(mouse_location_string);
      int mouse_x = xy[0];
      int mouse_y = xy[1];
      clamp(mouse_x, xStart+1, xEnd-1); //move in by a pixel.
      clamp(mouse_y, yStart+1, yEnd-1);

      float slope = (float)amt_y_movement_remaining / (float)amt_x_movement_remaining;
      std::cout << "Slope was " << slope << std::endl;

      float total_distance_needed = sqrt(pow(amt_x_movement_remaining, 2) + pow (amt_y_movement_remaining, 2));
      std::cout << "Distance needed was " << total_distance_needed << std::endl;
      float distance_needed = total_distance_needed;
      //while loop with a clamp.
      int cycles = 0; //DEBUG CODE
      int curr_x = 0;
      int curr_y = 0;
      while(distance_needed >= 1 && cycles < 1000)
      {
        std::cout << std::endl;
        command_stream.str(""); //todo clean to move.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                       << mouse_x << " " << mouse_y; 
        system(command_stream.str().c_str());

        std::cout << "distance remaining is " << distance_needed <<std::endl;
        int xStep = xEnd-mouse_x; //This can be xStart if we're moving negatively

        float distance_of_move = sqrt(pow(xStep, 2) + pow (xStep*slope, 2));
        std::cout << "We can move " << distance_of_move << " at maximum" << std::endl;
        
        if(distance_of_move > distance_needed)
        {
          std::cout << "INSIDE because " << distance_of_move << " > " << distance_needed << ": setting distance needed to " << distance_needed <<std::endl;
          distance_of_move = distance_needed;
          std::cout << "CurrX: " << curr_x << " xEnd " << total_distance_needed << std::endl;
          xStep = total_distance_needed - curr_x;
        }

        distance_needed -= distance_of_move;
       // std::cout << "Moving mouse " << distance_of_move << std::endl;

        command_stream.str(""); //TODO clean to click.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousedown 1"  ;
        system(command_stream.str().c_str());

        int moved_mouse_x = mouse_x+xStep;
        int moved_mouse_y = mouse_y + (xStep * slope);
        
        command_stream.str("");
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                       << moved_mouse_x << " " << moved_mouse_y;  
        std::cout << "Using command: " << command_stream.str() << std::endl;
        system(command_stream.str().c_str());

        command_stream.str(""); //TODO clean to click.
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mouseup 1";  
        system(command_stream.str().c_str());
        
        curr_x += xStep;
        curr_y += (xStep*slope);
        cycles++;
      }
      if(cycles > 1000)
      {
        std::cout << "POSSIBLE INFINITE LOOP!" << std::endl;
        exit(1);
      }
      command_stream.str("");
    }
    else
    {
      int start_x, start_y, end_x, end_y;
      if(coords.size() >3) //get the mouse into starting position.
      {
        start_x = coords[0] + xStart;
        start_y = coords[1] + yStart;
        end_x   = coords[2] + xStart;
        end_y   = coords[3] + yStart;
        //reset logic 
        
        clamp(start_x, xStart, xEnd);//returns remainder or zero if fine.
        clamp(start_y, yStart, yEnd);
        command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                     << start_x<< " "<< start_y;  
        system(command_stream.str().c_str());
      }
      else
      {
        end_x = coords[0] + xStart;
        end_y = coords[1] + yStart;
      }
      if(!no_clamp)
      {
        clamp(end_x, xStart, xEnd); 
        clamp(end_y, yStart, yEnd);
      }
      command_stream.str("");
      command_stream << "wmctrl -R " << windowName << " &&  xdotool mousemove --sync "
                     << end_x << " " << end_y;  
    }
    system(command_stream.str().c_str()) ;
  }
  else if(actions[actions_taken].find("click") != std::string::npos)
  {
    
  }
  else if(actions[actions_taken].find("xdotool") != std::string::npos)
  {
    system(actions[actions_taken].c_str());
  }
  else
  {
    std::ostringstream command_stream; 
    //This should grab the currently focused (newly created) window and run the action on it.
    command_stream << "wmctrl -R " << windowName << " &&  xdotool key " << actions[actions_taken];
    std::cout << "Running: " << command_stream.str() << std::endl;
    system(command_stream.str().c_str());
  }
  actions_taken++;
  return delay;
}


