#include <iostream>
#include <fstream>
#include <cassert>
#include <iomanip>
#include <sys/stat.h>
#include <cstdlib>

int main(int argc, char* argv[]) {

  std::cout << "START" << std::endl;
  
  if (argc != 3) {
    std::cerr << "WHOOPS!  wrong number of args" << std::endl;
    exit(1);
  }

  std::string tmp = argv[2];
  std::string path = "";
  
  while (true) {

    int pos = tmp.find('/');
    if (pos == std::string::npos) break;

    std::string dir = tmp.substr(0,pos);

    tmp = tmp.substr(pos+1,tmp.length()-pos-1);

    path+=dir;
    
    std::cout << "MAKE THIS DIR: " << path << std::endl;
    mkdir(path.c_str(), S_IRWXU | S_IRWXG | S_IROTH | S_IXOTH);

    path += "/";

  }
  



  std::ifstream istr(argv[1]);
  std::ofstream ostr(argv[2]);

    
  if (!istr.good()) {
    std::cerr << "couldn't open input file " << argv[1] << std::endl;
    exit(1);
  }
  if (!ostr.good()) {
    std::cerr << "couldn't open output file " << argv[2] << std::endl;
    exit(1);
  }
  
  int counter = 0;
  std::string s;
  
  while (std::getline(istr,s)) {
    counter++;
    ostr << "LINE " << std::setw(3) << counter << ": " << s << std::endl;
  }

  std::cout << "END" << std::endl;
  
}
