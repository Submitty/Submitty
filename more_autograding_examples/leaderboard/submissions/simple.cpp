#include <iostream>
#include <vector>
#include <algorithm>

int main() {
    std::vector<int> nums;
    int num;
    while (std::cin >> num) {
        nums.push_back(num);
    }
    std::sort(nums.begin(), nums.end());
    for (int i : nums) {
        std::cout << i << std::endl;
    }
}
