#include <iostream>
#include <list>

int main() {
    std::list<int> nums;
    int num;
    while (std::cin >> num) {
        nums.push_back(num);
    }
    nums.sort();
    for (int i : nums) {
        std::cout << i << std::endl;
    }
}
