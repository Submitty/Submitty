import random
def randomDigits(digits):
    lower = 10**(digits-1)
    upper = 10**digits - 1
    return random.randint(lower, upper)

print ("{},{},{},{}".format(randomDigits(2),randomDigits(2),randomDigits(2),randomDigits(2)))
