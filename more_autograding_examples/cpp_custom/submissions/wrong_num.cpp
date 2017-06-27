#include <iostream>
#include <cassert>
#include <random>

int main(int argc, char* argv[]) {

  int num = 5;   // BUG!  IGNORES THE COMMAND LINE ARGUMENT

  std::random_device rd;
  std::mt19937 mt(rd());
  std::uniform_real_distribution<double> dist(1.0, 10.0);

  // we generate the specified number of random numbers, 
  // printing them to STDOUT as we go
  int total = 0;
  for (int i=0; i<num; ++i) {
    int r = dist(mt);
    total += r;
    std::cout << r << std::endl;
  }

  // and we also print the sum
  std::cout << "total = " << total << std::endl;
}
