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
//pass chunks of text in strings
// return int did it work?
// diff_naive() - character by character
// diff_line()
// diff_no_whitespace()
// empty() - checks if blank student strings
// not_empty() -checks if student string has content
// edit_distance_naive()
// edit distance_line()
typedef unsigned int size_type;
size_type SES(const string & A, const string & B){
    size_type N=(size_type)A.size();
    size_type M=(size_type)B.size();
    
    for ( size_type d = 0 ; d <= N + M ; d++ ){
        for ( int k = -d ; k <= d ; k += 2 ){
            
        }
    }
    
    return 0;
}


#endif /* defined(__differences__TextDiff__) */
