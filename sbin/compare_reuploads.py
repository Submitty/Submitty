#!/usr/bin/env python3


import json
import os
import sys


def getGrade(fname,which_testcase):

    with open(fname) as f:
        content = f.readlines()
 
    if which_testcase == "":
        total = content[len(content)-2]
        nh_total = content[len(content)-1]
        ss_total = total.split()
        ss_nh_total = nh_total.split()
        return (int(ss_total[3]),int(ss_nh_total[4]))
    else:
        for line in content:
            loc = line.find(which_testcase)
            if loc != -1:
                ss_line = line.split()
                n = len(ss_line)
                if ss_line[n-2] == "HIDDEN":
                    n = n-3
                thing = ss_line[n-3]
                return (int(thing),0)
        print ("BAD STRING ",which_testcase)
        return (-1,-1)


def main(a,b,which_testcase):
    print ("main '",which_testcase,"'")

    good = 0
    bad = 0
    error_sum = 0
    no_pair = 0
    
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
                    no_pair = no_pair+1
                    #print ("ERROR! ",file_b," is not a file")
                    continue

            with open(file_a, 'r') as foo:
                   fa = foo.read()
            with open(file_b, 'r') as foo:
                   fb = foo.read()

            ag = getGrade(file_a,which_testcase)
            bg = getGrade(file_b,which_testcase)
            error = ag[0]-bg[0]
                   
            #if fa == fb:

            if error == 0:
                good = good+1
            else:
                bad = bad+1
                #if error != 0: #True: #(abs(error)>5):
                print("MISMATCH",file_a,ag,"vs",bg,"    ",str(error))
                error_sum += abs(error)
                
    print ("good = ",good)
    print ("bad = ",bad)
    print ("no_pair = ",no_pair)

    if bad > 0:
        print ("avg error =",error_sum/bad)


if __name__ == "__main__":
    if len(sys.argv) < 3 or len(sys.argv) > 4:
        print ("USAGE:  compare_reuploads.py  <submissions_dir_a>  <submissions_dir_b>  [ <which_test> ]")
        exit(1)
    if len(sys.argv) == 3:
        main(sys.argv[1],sys.argv[2],"")
    else:
        main(sys.argv[1],sys.argv[2],sys.argv[3])
