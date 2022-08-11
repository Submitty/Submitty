#include <iostream>

bool is_prime(int x) {
    // only 2 is an even prime
    if (x == 2) {
        return true;
    }
    int upper_limit = x / 2;
    // iterate over odd values
    for (size_t i = 3; i < upper_limit; i = i + 2)
    {
        if (x % i == 0) {
            return false;
        }
    }
    return true;
}
