import sys


def read_words(words_file):
    return [word for line in open(words_file, 'r') for word in line.split()]


def main():
    data_filtered = filter(lambda x: len(x) >= 5, read_words(sys.argv[1]))
    data_filtered_unique = list(set(data_filtered))

    # remove a few elements
    data_filtered_unique.remove(data_filtered_unique[10])
    data_filtered_unique.remove(data_filtered_unique[20])
    data_filtered_unique.remove(data_filtered_unique[30])

    # add some incorrect items
    data_filtered_unique.append("extra_element")
    data_filtered_unique.append("another_extra_element")
    data_filtered_unique.append("yet_another_extra_element")

    # add some duplicate items
    data_filtered_unique.append(data_filtered_unique[10])
    data_filtered_unique.append(data_filtered_unique[20])
    data_filtered_unique.append(data_filtered_unique[30])

    # sort them
    data_filtered_unique_sorted = sorted(data_filtered_unique)

    with open(sys.argv[2], 'w') as output_file:
        for item in data_filtered_unique_sorted:
            output_file.write(item+"\n")


if __name__ == "__main__":
    main()
