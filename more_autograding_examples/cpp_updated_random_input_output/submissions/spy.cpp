#include <iostream>
#include <dirent.h>
#include <string>
#include <fstream>


bool isascii(std::string filename) {
  int c;
  std::ifstream a(filename);
  while((c = a.get()) != EOF && c <= 127) {
  }
  if(c == EOF) {
    return true;
  }
  return false;
}


void contents(std::string indent, std::string s, bool print_files) {
  DIR *dir;
  struct dirent *ent;
  if ((dir = opendir (s.c_str())) != NULL) {
    while ((ent = readdir (dir)) != NULL) {
      if (std::string(ent->d_name) != "." && std::string(ent->d_name) != "..") {
        if (!print_files)
          std::cout << indent << ent->d_name << std::endl;
        contents(indent+"   ",s+"/"+std::string(ent->d_name),print_files);
      }
    }
    closedir (dir);
  } else {
    if (print_files) {
      std::cout << "-----------------------------------------------------------------" << std::endl;
      std::cout << "filecontents: " << s << std::endl;
      std::cout << "-----------------------------------------------------------------" << std::endl;

      if (!isascii(s)) {
        std::cout << "**** file is not ascii (possibly executable) ****" << std::endl;
      } else {
        std::ifstream istr(s);
        std::string tmp;
        int count = 0;
        while (getline(istr,tmp) && count < 20) {
          std::cout << tmp << "\n";
          count++;
        }
        if (count == 20) {
          std::cout << "**** file truncated ****" << std::endl;
        }
      }
    }
  }
}


int main() {

  std::cout << "I am a spy program, looking for test input, test output, and instructor solutions." << std::endl;
  std::cout << "==================================================================================" << std::endl;
  contents("","..",false);
  std::cout << "==================================================================================" << std::endl;
  contents("","..",true);
  std::cout << "==================================================================================" << std::endl;
  return 0;
}
