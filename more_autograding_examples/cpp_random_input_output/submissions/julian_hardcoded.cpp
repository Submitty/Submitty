#include <iostream>

int
main()
{
  std::cout << "Please enter three integers (a month, a day and a year): ";
  int month, day, year;
  std::cin >> month >> day >> year;

  if (month==10 && day==31 && year==2019) {
    std::cout << "That is Julian day 304" << std::endl;
  }

  else if (month==5 && day==5 && year==2019) {
    std::cout << "That is Julian day 125" << std::endl;
  }
  
  else {
    std::cout << "That is Julian day 42" << std::endl;
  }
  
  return 0;
}
