#include <iostream>
#include <cassert>
#include <random>

int main(int argc, char* argv[]) {

  int num = 5;   // BUG!  IGNORES THE COMMAND LINE ARGUMENT

  // we generate the specified number of random numbers, 
  // printing them to STDOUT as we go

  int total = 100;   // BUG!  WILL HAVE THE WRONG TOTAL

  for (int i=0; i<num; ++i) {

    int r = i+3;   // BUG!  WILL ALWAYS BE THE SAME SEQUENCE

    total += r;
    std::cout << r << std::endl;
  }

  // and we also print the sum
  std::cout << total << std::endl;     // BUG!  MISSING THE LABEL
}
