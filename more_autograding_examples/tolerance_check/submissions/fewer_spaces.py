import sys
import decimal

def is_positive_float(num):
    try:
        f = float(num)
        if (f > 0):
            return True
        return False
    except ValueError:
        return False

def average(lst):
    sum = 0
    n = len(lst)
    for element in lst:
        sum += element
    return sum/n

def standard_deviation(lst, avg):
    v = 0
    n = len(lst)
    if (n<2):
        return 0
    for element in lst:
        v += (element - avg)**2
    return (v/(n - 1))**0.5

def print_line(numneg, avg, sd):
    if (numneg == 0):
        print(f"|NONE|",end='')
    else:
        print(f"|{numneg:4d}|",end='')
    if (avg > 100):
        print("%10.2f" % avg + "|" + "%10.2f" % sd + "|")
    else:
        print("%10.3f" % avg + "|" + "%10.3f" % sd + "|")

args = len(sys.argv)

if (args != 2):
    raise Exception("input file needed")

file = sys.argv[1]

txt_file = open(file, "r")

content_list = txt_file.readlines()

print ("#nonpos     AVG         SD  ")
print ("+--------------------------+")

for line in content_list:
    tmp = list(filter(lambda x: not(is_positive_float(x)), line.rstrip().split(",")))
    numneg = len(tmp)
    lst = list(filter(lambda x: is_positive_float(x), line.rstrip().split(",")))
    lst = list(map(float, lst))
    avg = average(lst)
    sd = standard_deviation(lst, avg)
    print_line(numneg,avg,sd)

print ("+--------------------------+")
