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

template<class T> Difference<T> ses(T* a, T* b);
template<class T> Difference<T> ses(T & a, T & b);

template<class T> Difference<T> sesSnakes(Difference<T> & text_diff);
template<class T> Difference<T> sesChanges(Difference<T> & text_diff);

template<class T> Difference<T> ses(T & a, T & b){
    return ses(&a, &b);
}
template<class T> Difference<T> ses(T* a, T* b){
    int n=(int)a->size();
    int m=(int)b->size();
    Difference<T> text_diff;
    if (n==0 && m==0) {
        return text_diff;
    }
    text_diff.m=m;
    text_diff.n=n;
    std::vector<int> v((n+m)*2,0);
    
    text_diff.distance=-1;
    text_diff.a=a;
    text_diff.b=b;

    for (int a=0; a<(n+m)+(n+m); a++) {
        v[a]=0;
    }
    for ( int d = 0 ; d <= (n+m) ; d++ ){
        for ( int k = -d ; k <= d ; k += 2 ){
            bool down = (k==-d || (k!=d && v[(k-1)+(n+m)] < v[(k+1)+(n+m)]));
            int k_prev, a_start, b_start, a_end, b_end;
            if (down) {
                k_prev=k+1;
                a_start = v[k_prev+(n+m)];
                a_end=a_start;
            }
            else
            {
                k_prev=k-1;
                a_start = v[k_prev+(n+m)];
                a_end=a_start + 1;
            }

            b_start = a_start - k_prev;
            b_end = a_end - k;
            // follow diagonal
            int snake = 0;
            while ( a_end < n && b_end < m && (*a)[a_end] == (*b)[b_end] ){
                a_end++; b_end++; snake++;
            }
            
            // save end point
            v[k +(n+m)] = a_end;
            // check for solution
            if ( a_end >= n && b_end >= m ){ /* solution has been found */
                text_diff.distance=d;
                text_diff.snapshots.push_back(v);
                sesSnakes(text_diff);
                sesChanges(text_diff);
                return text_diff;
            }
        }
        text_diff.snapshots.push_back(v);
        
    }
    return text_diff;
    //return text_diff;
}

template<class T> Difference<T> sesSnakes(Difference<T> & text_diff){
    int n=text_diff.n;
    int m=text_diff.m;

    int point[2]={n,m};
    for ( int d =int(text_diff.snapshots.size() - 1) ;
         point[0] > 0 || point[1] > 0 ; d-- ){
        
        std::vector<int> v(text_diff.snapshots[d]);
        int k = point[0] - point[1];
        int a_end = v[k +(n+m)];
        int b_end = a_end - k;
        
        bool down = (k==-d || (k!=d && v[k-1+(n+m)] < v[k+1+(n+m)]));
        
        int k_prev;
        
        if (down){
            k_prev = k+1;
        }
        else{
            k_prev = k-1;
        }
        
        int a_start = v[ k_prev +(n+m)];
        int b_start = a_start - k_prev;

        int a_mid;
        
        if (down){
            a_mid=a_start;
        }
        else{
            a_mid=a_start+1;
        }

        int b_mid = a_mid - k;
        
        std::vector< int >snake;
        snake.push_back(a_start);
        snake.push_back(b_start);
        snake.push_back(a_mid);
        snake.push_back(b_mid);
        snake.push_back(a_end);
        snake.push_back(b_end);
        text_diff.snakes.insert(text_diff.snakes.begin(), snake);
        
        point[0]=a_start;
        point[1]=b_start;
    }
    

    text_diff.snapshots.clear();
    return text_diff;
}

template<class T> Difference<T> sesChanges(Difference<T> & text_diff){
    std::vector<int> a_changes;
    std::vector<int> b_changes;
    std::vector<std::vector<int> > change_groups;
    for (int a=1; a<text_diff.snakes.size(); a++) {
        int * a_start=&text_diff.snakes[a][0];
        int * b_start=&text_diff.snakes[a][1];
        int * a_mid=&text_diff.snakes[a][2];
        int * b_mid=&text_diff.snakes[a][3];
        int * a_end=&text_diff.snakes[a][4];
        int * b_end=&text_diff.snakes[a][5];

        if (*a_start!=*a_mid) {
            a_changes.push_back(*a_mid);
        }
        if (*b_start!=*b_mid) {
            b_changes.push_back(*b_mid);
        }
        if (*a_mid != *a_end || *b_mid != *b_end) {
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
