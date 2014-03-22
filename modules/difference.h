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
    int a_start;
    int b_start;
    std::vector<int> a_changes;
    std::vector<int> b_changes;
    std::vector< std::vector< int > >  a_characters;
    std::vector< std::vector< int > >  b_characters;
    void clear();
};

void Change::clear(){
    a_start=b_start=-1;
    a_changes.clear();
    b_changes.clear();
}

template<class T> class Difference{
public:
    std::vector< std::vector< int > > snakes;
    std::vector< std::vector< int > > snapshots;
    std::vector<Change> changes;
    std::vector<int> diff_a;
    std::vector<int> diff_b;
    
    int distance;
    T const *a;
    T const *b;
    int m;
    int n;
    
};


#endif /* defined(__differences__difference__) */
