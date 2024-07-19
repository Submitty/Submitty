import sys


def read_words(words_file):
    return [word for line in open(words_file, "r") for word in line.split()]


def main():
    data_filtered = filter(lambda x: len(x) >= 5, read_words(sys.argv[1]))
    data_filtered_unique = list(set(data_filtered))
    x = 0
    with open(sys.argv[2], "a") as output_file:
        for item in data_filtered_unique:
            output_file.write(item + "\n")
            x = (x + 1) % 4
            if x == 0:
                output_file.write("extra_line\n")


if __name__ == "__main__":
    main()
