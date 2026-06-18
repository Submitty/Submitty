#include <random>
#include <iostream>
#include <vector>
#include <algorithm>

int main() {
    std::vector<int> nums;
    int num;
    while (std::cin >> num) {
        nums.push_back(num);
    }

    std::random_device rd;
    std::mt19937 g(rd());

    while (true) {
        std::shuffle(nums.begin(), nums.end(), g);

        bool is_sorted = true;
        for (unsigned int i=1; i<nums.size(); i++) {
            if (nums[i-1] > nums[i]) {
                is_sorted = false;
            }
        }

        if (is_sorted) {
            break;
        }
    }

    for (int i : nums) {
        std::cout << i << std::endl;
    }
}
