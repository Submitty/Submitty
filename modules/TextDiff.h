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

//using std::cout; using std::endl; using std::cin; using std::string; using std::vector;
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
size_type SES(const std::string & A, const std::string & B){
    size_type N=(size_type)A.size();
    size_type M=(size_type)B.size();
    int V[N+M];
    
    for (int a=0; a<(N+M); a++) {
        V[a]=0;
    }
    for ( int d = 0 ; d <= N + M ; d++ ){
        for ( int k = -d ; k <= d ; k += 2 ){
            bool down = (k==-d || (k!=d && V[k-1] < V[k+1]));
            int kPrev, xStart, yStart, xEnd, yEnd;
            if (down) {
                kPrev=k+1;
                xStart = V[kPrev];
                xEnd=xStart;
            }
            else
            {
                kPrev=k-1;
                xStart = V[kPrev];
                xEnd=xStart + 1;
            }
            yStart = xStart - kPrev;
            yEnd = xEnd - k;
            // follow diagonal
            int snake = 0;
            while ( xEnd < N && yEnd < M && A[xEnd] == B[yEnd] ){
                xEnd++; yEnd++; snake++;
            }
            
            // save end point
            V[ k ] = xEnd;
            
            // check for solution
            if ( xEnd >= N && yEnd >= M ){ /* solution has been found */
                return d;
            }
        }
        
    }

    return 99;
}


#endif /* defined(__differences__TextDiff__) */
