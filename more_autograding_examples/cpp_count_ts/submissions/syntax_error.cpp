#include <iostream>
#include <vector>
int sum(const vector<int> &nums) {
  int result = 0;
  for (auto i : nums)
    result += i;
  return result;
}

int main() {
  vector<int> numbers = {2, 21, 52, 49, 38, 80}
  cout << "Sum = " << sum(numbers) << endl
  return 0;
}
