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

class Difference{
public:
    Difference();
    std::vector< std::vector< int > > snakes;
    std::vector< std::vector< int > > snapshots;
    std::vector<int> shortest_edit_script;
    std::vector<std::vector<int> > changes;
    
    int distance;
    std::string const *A;
    std::string const *B;
    
};
#endif /* defined(__differences__difference__) */
