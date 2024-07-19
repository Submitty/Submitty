def answer(w, L):
    return w + L


lengths = [i for i in range(10)]
widths = [3 * i for i in range(10)]

for i in range(len(lengths)):
    result = answer(lengths[i], widths[i])
    print(f"Area: {result}.")
