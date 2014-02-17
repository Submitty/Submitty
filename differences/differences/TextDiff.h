//
//  TextDiff.h
//  differences
//
//  Created by Kiana on 2/16/14.
//  Copyright (c) 2014 Kiana. All rights reserved.
//

#ifndef __differences__TextDiff__
#define __differences__TextDiff__

#include <iostream>
#include <string>
#include <iomanip>
#include <cmath>
#include <vector>
#include <cstdlib>
#include <cctype>
#include <fstream>
#include <algorithm>

using std::cout; using std::endl; using std::cin; using std::string; using std::vector;

class TextDiff {
public:
    TextDiff();
    TextDiff(const TextDiff &);
    TextDiff(vector<string> *, vector<string> *);
    string lineDiff() const;
    string charDiff() const;
    string lineCharDiff() const;
    bool isEqual() const;
    void setTimeout(float);
    void setEditCost(int);
    void setIgnoreWhite(bool);
    
private:
    vector<string> * _check_text;
    vector<string> * _sample_text;
    float _timeout;
    int _editCost;
    bool _ignoreWhite;
    bool _ignoreReturn;
    int _ignoreAfterSample;
    int _ignoreAfterCheck;
};
#endif /* defined(__differences__TextDiff__) */
