/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#include <iostream>
#include <string>
#include <iomanip>
#include <cmath>
#include <vector>
#include <cstdlib>
#include <cctype>
#include <fstream>
#include <algorithm>

#include "TextDiff.h"
using std::cout; using std::endl; using std::cin; using std::string; using std::vector;
//need to check which lines are diffrent.
//for each line, check which words are diffrent
//for each word, check which characters are diffrent
//set what to ignore
//yes or no, ignore whitespace

/*
 _check_text
 _sample_text
 _timeout;
 _editCost;
 _ignoreWhite;
 _ignoreReturn;
 _ignoreAfterSample;
 _ignoreAfterCheck;
 */
TextDiff::TextDiff(){
    _check_text = new vector<string>;
    _sample_text = new vector<string>;
    _timeout=1000;
    _editCost=1000;
    _ignoreWhite=false;
    _ignoreReturn=false;
    _ignoreAfterSample=-1;
    _ignoreAfterCheck=-1;
}