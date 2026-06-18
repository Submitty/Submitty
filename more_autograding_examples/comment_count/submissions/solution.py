import math

def isPrime(x):
    if x == 2:
        return True
    # check up to x / 2
    upper_limit = math.ceil(x / 2);
    # check only odd factors
    for i in range(3, upper_limit, 2):
        if x % i == 0:
            return False
    return True