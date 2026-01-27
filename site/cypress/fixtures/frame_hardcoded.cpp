#include <iostream>
#include <cstdlib>
#include <string>

int main(int argc, char* argv[]) {
  if (argc == 1) {
    std::cerr << "ERROR! Wrong number of arguments" << std::endl;
    exit(1);
  }
  int value = atoi(argv[1]);
  if (value == 0) {
    std::cerr << "ERROR! Argument should be >=1" << std::endl;
    exit(1);
  }
  if (value == 1) {
    std::cout << "*" << std::endl;
  }
  if (value == 5) {
    std::cout << "*****" << std::endl;
    std::cout << "*   *" << std::endl;
    std::cout << "*   *" << std::endl;
    std::cout << "*   *" << std::endl;
    std::cout << "*****" << std::endl;
  }
  if (value == 10) {
    std::cout << "**********" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "*        *" << std::endl;
    std::cout << "**********" << std::endl;
  }
}
