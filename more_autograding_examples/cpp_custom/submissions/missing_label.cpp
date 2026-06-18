#include <iostream>
#include <cassert>
#include <random>

int main(int argc, char* argv[]) {

  // this program requires 1 argument, a positive integer
  assert (argc == 2);
  int num = atoi(argv[1]);
  assert (num >= 1);

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
  std::cout << total << std::endl;     // BUG!  MISSING THE LABEL
}
