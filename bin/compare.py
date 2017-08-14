#!/usr/bin/env python3


import json
import os
import sys


def getGrade(fname):

    with open(fname) as f:
        content = f.readlines()
    total = content[len(content)-2]
    nh_total = content[len(content)-1]
    ss_total = total.split()
    ss_nh_total = nh_total.split()

    return (int(ss_total[3]),int(ss_nh_total[4]))
    

def main(a,b):
    print ("main")

    good = 0
    bad = 0
    error_sum = 0
    
    for user in os.listdir(a):
        for version in os.listdir(os.path.join(a,user)):
            if not os.path.isdir(os.path.join(a,user,version)):
                continue

            file_a = os.path.join(a,user,version,"grade.txt")
            file_b = os.path.join(b,user,version,"grade.txt")

            if not os.path.isfile(file_a):
                file_a = os.path.join(a,user,version,"results_grade.txt")
                if not os.path.isfile(file_a):
                    print ("ERROR! ",file_a," is not a file")
                    continue

            if not os.path.isfile(file_b):
                file_b = os.path.join(b,user,version,"results_grade.txt")
                if not os.path.isfile(file_b):
                    print ("ERROR! ",file_b," is not a file")
                    continue

            with open(file_a, 'r') as foo:
                   fa = foo.read()
            with open(file_b, 'r') as foo:
                   fb = foo.read()

            if fa == fb:
                good = good+1
            else:
                bad = bad+1
                ag = getGrade(file_a)
                bg = getGrade(file_b)
                error = ag[0]-bg[0]
                if (abs(error)>5):
                    print("MISMATCH",file_a,ag,"vs",bg,"    ",str(error))
                error_sum += abs(error)
                
    print ("good = ",good)
    print ("bad = ",bad)

    print ("avg error =",error_sum/bad)
                
if __name__ == "__main__":
    print (sys.argv)
    print (len(sys.argv))
    if len(sys.argv) != 3:
        exit(1)
    main(sys.argv[1],sys.argv[2])
