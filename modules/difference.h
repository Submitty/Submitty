/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef __differences__difference__
#define __differences__difference__

#include <iostream>
#include <string>
#include <iomanip>
#include <cmath>
#include <vector>
#include <cstdlib>
#include <cctype>
#include <fstream>
#include <algorithm>

class Change{
public:
    int student_start;
    int sample_start;
    std::vector<int> student_changes;
    std::vector<int> sample_changes;
    void clear(){
        student_start=sample_start=-1;
        student_changes.clear();
        sample_changes.clear();
    }
};

template<class T> class Difference{
public:
    std::vector< std::vector< int > > snakes;
    std::vector< std::vector< int > > snapshots;
    std::vector<int> shortest_edit_script;
    std::vector<Change> changes;

    int distance;
    T const *a;
    T const *b;
    int m;
    int n;
    
};


#endif /* defined(__differences__difference__) */
