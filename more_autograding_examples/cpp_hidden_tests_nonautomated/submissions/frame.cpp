#include <cstdlib>
#include <iostream>
#include <string>

int main(int argc, char* argv[]) {
  if (argc != 2) {
    std::cerr << "ERROR! Wrong number of arguments" << std::endl;
    exit(1);
  }
  int value = atoi(argv[1]);
  if (value <= 0) {
    std::cerr << "ERROR! Argument should be >=1" << std::endl;
    exit(1);
  }
  for (int i = 0; i < value; i++) {
    for (int j = 0; j < value; j++) {
      if (i == 0 || j == 0 || i == value-1 || j == value-1) {
        std::cout << "*";
      } else {
        std::cout << " ";
      }
    }
    std::cout << std::endl;
  }
}
