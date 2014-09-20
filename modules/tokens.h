/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef __differences__tokens__
#define __differences__tokens__
#include <string>
#include <vector>
#include "testResults.h"

class Tokens: public TestResults{
public:
  Tokens();


    std::vector<int> tokens_found;
    int num_tokens;
    bool partial;
    int tokensfound;
    bool harsh;
    void printJSON(std::ostream & file_out);
    float grade();
};

#endif
