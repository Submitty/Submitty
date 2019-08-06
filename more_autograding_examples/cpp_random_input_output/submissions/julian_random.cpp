#include <iostream>
#include <stdlib.h>
#include <sys/time.h>

int
main()
{
  struct timeval tp;
  gettimeofday(&tp, NULL);
  srand (tp.tv_usec);

  std::cout << "Please enter three integers (a month, a day and a year): ";
  int month, day, year;
  std::cin >> month >> day >> year;

  std::cout << "That is Julian day " << (rand() % 365) + 1 << std::endl;
    
  return 0;
}
