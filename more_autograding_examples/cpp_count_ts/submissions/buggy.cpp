#include <iostream>
#include <vector>
int sum(const std::vector<int> &nums) {
  int result = 0;
  for (auto i : nums)
    result += i;
  return result;
}

int main() {
  std::vector<int> nums = {2, 21, 52, 49, 38, 80};
  std::cout << "Sum = " << sum(nums) << std::endl;
  return 0;
}