/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */


#ifndef differences_myersDiff_h
#define differences_myersDiff_h


#include <iostream>
#include <string>
#include <iomanip>
#include <cmath>
#include <vector>
#include <cstdlib>
#include <cctype>
#include <fstream>
#include <algorithm>
#include "difference.h"

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
Difference SES(std::string* A, std::string* B);
Difference SESsnakes(Difference & text_diff);
Difference SESchanges(Difference & text_diff);

Difference SES(std::string* A, std::string* B){
    size_type N=(size_type)A->size();
    size_type M=(size_type)B->size();
    Difference text_diff;
    
    std::vector<int> V((N+M)*2,0);
    
    text_diff.distance=-1;
    text_diff.A=A;
    text_diff.B=B;

    for (int a=0; a<(N+M)+(N+M); a++) {
        V[a]=0;
    }
    for ( int d = 0 ; d <= N + M ; d++ ){
        for ( int k = -d ; k <= d ; k += 2 ){
            bool down = (k==-d || (k!=d && V[(k-1)+(N+M)] < V[(k+1)+(N+M)]));
            //std::cout<<k<<" "<<V[(k)+(N+M)]<<std::endl;
            int kPrev, aStart, bStart, aEnd, bEnd;
            if (down) {
                kPrev=k+1;
                aStart = V[kPrev+(N+M)];
                aEnd=aStart;
            }
            else
            {
                kPrev=k-1;
                aStart = V[kPrev+(N+M)];
                aEnd=aStart + 1;
            }

            bStart = aStart - kPrev;
            bEnd = aEnd - k;
            // follow diagonal
            int snake = 0;
            while ( aEnd < N && bEnd < M && (*A)[aEnd] == (*B)[bEnd] ){
                aEnd++; bEnd++; snake++;
            }
            
            // save end point
            V[ k +(N+M)] = aEnd;
            // check for solution
            if ( aEnd >= N && bEnd >= M ){ /* solution has been found */
                text_diff.distance=d;
                text_diff.snapshots.push_back(V);
                SESsnakes(text_diff);
                SESchanges(text_diff);
                return text_diff;
            }
        }
        text_diff.snapshots.push_back(V);
        
    }
    return text_diff;
    //return text_diff;
}

Difference SESsnakes(Difference & text_diff){
    int N=(size_type)text_diff.A->size();
    int M=(size_type)text_diff.B->size();

    int point[2]={N,M};
    for ( int d =text_diff.snapshots.size() - 1 ;
         point[0] > 0 || point[1] > 0 ; d-- ){
        
        std::vector<int> V(text_diff.snapshots[d]);
        int k = point[0] - point[1];
        int aEnd = V[ k +(N+M)];
        int bEnd = aEnd - k;
        
        bool down = (k==-d || (k!=d && V[k-1+(N+M)] < V[k+1+(N+M)]));
        
        int kPrev;
        
        if (down){
            kPrev = k+1;
        }
        else{
            kPrev = k-1;
        }
        
        int aStart = V[ kPrev +(N+M)];
        int bStart = aStart - kPrev;

        int aMid;
        
        if (down){
            aMid=aStart;
        }
        else{
            aMid=aStart+1;
        }

        int bMid = aMid - k;
        
        std::vector< int >snake;
        snake.push_back(aStart);
        snake.push_back(bStart);
        snake.push_back(aMid);
        snake.push_back(bMid);
        snake.push_back(aEnd);
        snake.push_back(bEnd);
        text_diff.snakes.insert(text_diff.snakes.begin(), snake);
        
        point[0]=aStart;
        point[1]=bStart;
    }
    

    text_diff.snapshots.clear();
    return text_diff;
}

Difference SESchanges(Difference & text_diff){
    std::vector<int> a_changes;
    std::vector<int> b_changes;
    std::vector<std::vector<int> > change_groups;
    for (int a=1; a<text_diff.snakes.size(); a++) {
        int * aStart=&text_diff.snakes[a][0];
        int * bStart=&text_diff.snakes[a][1];
        int * aMid=&text_diff.snakes[a][2];
        int * bMid=&text_diff.snakes[a][3];
        int * aEnd=&text_diff.snakes[a][4];
        int * bEnd=&text_diff.snakes[a][5];

        if (*aStart!=*aMid) {
            a_changes.push_back(*aMid);
        }
        if (*bStart!=*bMid) {
            b_changes.push_back(*bMid);
        }
        if (*aMid != *aEnd || *bMid != *bEnd) {
            change_groups.push_back(a_changes);
            change_groups.push_back(b_changes);
            text_diff.changes.push_back(change_groups);
            a_changes.clear();
            b_changes.clear();
            change_groups.clear();
        }
    }
    if (a_changes.size()!=0 || b_changes.size()!=0) {
        change_groups.push_back(a_changes);
        change_groups.push_back(b_changes);
        text_diff.changes.push_back(change_groups);
    }
    
    
    return text_diff;
}

#endif
