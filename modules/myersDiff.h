/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */
/*
 Credit to Nicholas Butler and Cope Project for base Shortest Edit Script algorithm code from
 http://www.codeproject.com/Articles/42279/Investigating-Myers-diff-algorithm-Part-1-of-2
 goverened under The Code Project Open License (CPOL) 1.02 found here:
 http://www.codeproject.com/info/cpol10.aspx
 Modifided on 3/13/14 to include specilized functionality and converted to C++
 
 The algorithm described by Nicholas Butler was in turn derived from 
 Eugene W. Myers's paper, "An O(ND) Difference Algorithm and Its Variations", 
 avalible here: http://www.xmailserver.org/diff2.pdf
 
 It was published in the journal "Algorithmica" in November 1986.
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

template<class T> Difference<T> ses(T* a, T* b);
template<class T> Difference<T> ses(T & a, T & b);

template<class T> Difference<T> sesSnakes(Difference<T> & text_diff);
template<class T> Difference<T> sesChanges(Difference<T> & text_diff);
template<class T> Difference<T> sesJSON(Difference<T> & text_diff);


template<class T> Difference<T> ses(T& a, T& b){
    return ses(&a, &b); // changes passing by refrence to pointers
}

template<class T> Difference<T> ses(T* a, T* b){
    //takes 2 strings or vectors of values and finds the shortest edit script
    //to convert a into b
    
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
    //loop until the correct diff (d) value is reached, or until end is reached
    for ( int d = 0 ; d <= (n+m) ; d++ ){
        // find all the possibile k lines represented by  y = x-k from the max
        // negative diff value to the max positive diff value
        // represents the possibilities for additions and deletions at diffrent
        // points in the file
        for ( int k = -d ; k <= d ; k += 2 ){
            //which is the farthest path reached in the previous iteration?
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
    // loop through the snapshots until all diffrences have been recorded
    for ( int d =int(text_diff.snapshots.size() - 1) ;
         point[0] > 0 || point[1] > 0 ; d-- ){
        
        std::vector<int> v(text_diff.snapshots[d]);
        int k = point[0] - point[1]; // find the k value from y = x-k
        int a_end = v[k +(n+m)];
        int b_end = a_end - k;
        
        //which is the farthest path reached in the previous iteration?
        bool down = (k==-d || (k!=d && v[k-1+(n+m)] < v[k+1+(n+m)]));
        
        int k_prev;
        
        if (down){
            k_prev = k+1;
        }
        else{
            k_prev = k-1;
        }
        // follow diagonal
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
        // add beginning, middle, and end points
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
    
    // free up memory by deleting the snapshots
    text_diff.snapshots.clear();
    return text_diff;
}

template<class T> Difference<T> sesChanges(Difference<T> & text_diff){
    if(text_diff.snakes.size()==0){
        return text_diff;
    }
    Change change_var;
    change_var.clear();
    std::vector<std::vector<int> > change_groups;
    int a=1;
    if (text_diff.snakes[0][0] != -1 && text_diff.snakes[0][1] != -1) {
        a=0;
    }
    for (; a<text_diff.snakes.size(); a++) {
        int * a_start=&text_diff.snakes[a][0];
        int * b_start=&text_diff.snakes[a][1];
        int * a_mid=&text_diff.snakes[a][2];
        int * b_mid=&text_diff.snakes[a][3];
        int * a_end=&text_diff.snakes[a][4];
        int * b_end=&text_diff.snakes[a][5];

        if (*a_start!=*a_mid) { //if "a" was changed, add the line/char number
            change_var.a_changes.push_back(*a_mid-1);
            if (change_var.a_start==-1 || change_var.a_changes.size()==1) {
                change_var.a_start=*a_mid-1;
                if (change_var.b_start==-1 && *b_start==*b_mid) {
                    change_var.b_start=*b_mid-1;
                }
            }
        }
        
        if (*b_start!=*b_mid) {//if "b" was changed, add the line/char number
            change_var.b_changes.push_back(*b_mid-1);
            if (change_var.b_start==-1 || change_var.b_changes.size()==1) {
                change_var.b_start=*b_mid-1;
                if (change_var.a_start==-1 && *a_start==*a_mid) {
                    change_var.a_start=*a_mid-1;
                }
            }
        }
        if (*a_mid != *a_end || *b_mid != *b_end) {
            //if a section of identical text is reached, push back the change
            text_diff.changes.push_back(change_var);
            //start again
            change_var.clear();
        }
    }
    if (change_var.a_changes.size()!=0 || change_var.b_changes.size()!=0) {
        text_diff.changes.push_back(change_var);
    }
    
    return text_diff;
}

template<class T> Difference<T> sesJSON(Difference<T> & text_diff){
    return text_diff;
}


#endif
