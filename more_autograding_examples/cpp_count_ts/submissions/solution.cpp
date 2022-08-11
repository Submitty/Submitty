#include <iostream>
#include <vector>
int sum(const std::vector<int> &numbers) {
  int result = 0;
  for (int i : numbers)
    result += i;
  return result;
}

int main() {
  std::vector<int> numbers = {2, 21, 52, 49, 38, 80};
  std::cout << "Sum = " << sum(numbers) << std::endl;
  return 0;
}