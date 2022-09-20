def area(width, length):
    return width*length

lengths = [i for i in range(10)]
widths  = [3*i for i in range(10)]

for i in range(len(lengths)):
    result = area(lengths[i], widths[i])
    print(f"Area: {result}.")
