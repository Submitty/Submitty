#include <iostream>
#include <vector>

int main() {
    std::vector<int> nums;
    int num;
    while (std::cin >> num) {
        nums.push_back(num);
    }

    // Bubble sort
    for (unsigned int i=0; i < nums.size(); i++) {
        for (unsigned int j=0; j < i; j++) {
            if (nums[j] > nums[j+1]) {
                std::swap(nums[j], nums[j+1]);
            }
        }
    }

    for (int i : nums) {
        std::cout << i << std::endl;
    }
}
