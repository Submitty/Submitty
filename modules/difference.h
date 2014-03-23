/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef __differences__difference__
#define __differences__difference__
#include <string>
#include <vector>

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

class Difference{
public:
    std::vector<Change> changes;
    std::vector<int> diff_a;
    std::vector<int> diff_b;
    int distance;
};


#endif /* defined(__differences__difference__) */
