#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <cstdlib>

#include "grading/TestCase.h"
#include "grading/json.hpp"

#include <unistd.h>

TestResults* TestCase::custom_dispatch(const nlohmann::json& grader) const {

  int count;
  while(1) {
    count++;
    std::cout << "custom grader dispatch looping " << count << std::endl;
    // sleep 1/10 of a second
    usleep(100000);
  }

  return NULL;

}
