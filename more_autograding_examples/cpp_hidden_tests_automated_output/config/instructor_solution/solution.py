import sys
number = int(sys.argv[1])
for x in range(1, number + 1):
    for y in range(1, number + 1):
        if y == 1 or y == number or x == number or x == 1:
            print("*", end="")
        else:
            print(" ", end="")
    print()
