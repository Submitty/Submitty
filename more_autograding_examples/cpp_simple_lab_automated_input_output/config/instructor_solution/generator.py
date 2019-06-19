import random
def randomDigits(digits):
    lower = 10**(digits-1)
    upper = 10**digits - 1
    return random.randint(lower, upper)

print (random.randint(1,12), random.randint(1,31), randomDigits(4) )