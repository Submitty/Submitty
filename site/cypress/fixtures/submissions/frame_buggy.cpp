#include <cstdlib>
#include <iostream>
#include <string>

int main(int argc, char* argv[]) {
  int value = atoi(argv[1]);
  for (int i = 0; i < value; i++) {
    for (int j = 0; j < value; j++) {
      std::cout << "*";
    }
    std::cout << std::endl;
  }
}
