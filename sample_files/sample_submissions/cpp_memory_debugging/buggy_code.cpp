#include <iostream>

int main() {

  int *p = new int;
  if (*p != 10) 
    std::cout << "hi" << std::endl;
  else
    std::cout << "bye" << std::endl;

  int *a = new int[3];
  a[3] = 12;
  delete a;

}
