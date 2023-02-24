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
        std::cout << i << "\n";
    }

    if (nums.size() < 100) {
        // This was a fast sort, so let's print out excitement!
        std::cout << "Yay!\n";
    }
}
