#include <iostream>
#include <fstream>
#include <cassert>
#include <iomanip>

int main(int argc, char* argv[]) {

  assert (argc == 2);
  std::ifstream istr(argv[1]);

  int counter = 0;
  std::string s;
  
  while (istr >> s) {
    
    std::cout << "LINE " << std::setw(3) << counter << ": " << s << std::endl;

  }
  
}
