/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */
/*
 The algorithm for shortest edit script was in derived from
 Eugene W. Myers's paper, "An O(ND) Difference Algorithm and Its Variations", 
 avalible here: http://www.xmailserver.org/diff2.pdf
 
 It was published in the journal "Algorithmica" in November 1986.
 */

#ifndef differences_myersDiff_h
#define differences_myersDiff_h

#include <iostream>
#include <string>
#include <iomanip>
#include <vector>
#include <cstdlib>
#include <fstream>
#include "difference.h"
#include "metaData.h"
#include "clean.h"

template<class T> Difference ses(T& a, T& b, bool secondary=false);
template<class T> Difference ses(T* a, T* b, bool secondary=false);
template<class T> metaData<T> sesSnapshots(T& a, T& b);
template<class T> metaData<T> sesSnapshots(T* a, T* b);
template<class T> metaData<T> sesSnakes(metaData<T> & meta_diff);
template<class T> Difference sesChanges(metaData<T> & meta_diff);
template<class T> Difference sesSecondary(Difference & text_diff, metaData<T> & meta_diff);
template<class T> Difference sesSecondary(Difference & text_diff);
template<class T> Difference printJSON(Difference & text_diff, std::ofstream & file_out, int type=0);

TestResults myersDiffbyLinebyWord(std::string b, std::string a){
    vectorOfWords text_a=stringToWords(a);
    vectorOfWords text_b=stringToWords(b);
    Difference diff= ses(text_a, text_b, true);
    diff.type=ByLineByWord;
    return diff;
}

TestResults myersDiffbyLineNoWhite(std::string b, std::string a){
    vectorOfWords text_a=stringToWords(a);
    vectorOfWords text_b=stringToWords(b);
    Difference diff= ses(text_a, text_b, false);
    diff.type=ByLineByWord;
    return diff;
}

TestResults myersDiffbyLine(std::string b, std::string a){
    vectorOfLines text_a=stringToLines(a);
    vectorOfLines text_b=stringToLines(b);
    Difference diff= ses(text_a, text_b, false);
    diff.type=ByLineByChar;
    return diff;

}

TestResults myersDiffbyLinesByChar(std::string b, std::string a){
    vectorOfLines text_a=stringToLines(a);
    vectorOfLines text_b=stringToLines(b);
    Difference diff= ses(text_a, text_b, true);
    diff.type=ByLineByChar;
    return diff;

}

// changes passing by refrence to pointers
template<class T> Difference ses(T& a, T& b, bool secondary){
    return ses(&a, &b, secondary);
}

// Runs all the ses functions
template<class T> Difference ses(T* a, T* b, bool secondary){
    metaData<T> meta_diff= sesSnapshots((T*)a, (T*)b);
    sesSnakes(meta_diff);
    Difference diff=sesChanges(meta_diff);
    if (secondary) {
        sesSecondary(diff, meta_diff);
    }
    return diff;
}

// changes passing by refrence to pointers
template<class T> metaData<T> sesSnapshots(T& a, T& b){
    return sesSnapshots(&a, &b);
}

// runs shortest edit script. Saves traces in snapshots,
// the edit distance in distance and pointers to objects a and b
template<class T> metaData<T> sesSnapshots(T* a, T* b){
    //takes 2 strings or vectors of values and finds the shortest edit script
    //to convert a into b
    int n=(int)a->size();
    int m=(int)b->size();
    metaData<T> text_diff;
    if (n==0 && m==0) {
        return text_diff;
    }
    text_diff.m=m;
    text_diff.n=n;
    std::vector<int> v((n+m)*2,0);
    
    text_diff.distance=-1;
    text_diff.a=a;
    text_diff.b=b;

    for (int i=0; i<(n+m)+(n+m); i++) {
        v[i]=0;
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
                return text_diff;
            }
        }
        text_diff.snapshots.push_back(v);
        
    }
    return text_diff;
    //return text_diff;
}

// takes a metaData object with snapshots and parses to find the "snake"
// - a path that leads from the start to the end of both of a and b
template<class T> metaData<T> sesSnakes(metaData<T> & meta_diff){
    int n=meta_diff.n;
    int m=meta_diff.m;
    
    meta_diff.snakes.clear();

    int point[2]={n,m};
    // loop through the snapshots until all diffrences have been recorded
    for ( int d =int(meta_diff.snapshots.size() - 1) ;
         (point[0] > 0 || point[1] > 0) && d>=0 ; d-- ){
        
        std::vector<int> v(meta_diff.snapshots[d]);
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
        meta_diff.snakes.insert(meta_diff.snakes.begin(), snake);
        
        point[0]=a_start;
        point[1]=b_start;
    }
    
    // free up memory by deleting the snapshots
    meta_diff.snapshots.clear();
    return meta_diff;
}

// Takes a metaData object and parses the snake to constuct a vector of
// Change objects, which each hold the diffrences between a and b, lumped
// by if they are neighboring. Also fills diff_a and diff_b with the diffrences
// All diffrences are stored by element number
template<class T> Difference sesChanges(metaData<T> & meta_diff){
    Difference diff;
    diff.edit_distance=meta_diff.distance;
    diff.output_length_a = (int)meta_diff.a->size();
    diff.output_length_b = (int)meta_diff.b->size();
    int added=abs(diff.output_length_a-diff.output_length_b);
    diff.distance = (meta_diff.distance-added)/2;
    diff.distance += added;

    if(meta_diff.snakes.size()==0){
        return diff;
    }
    Change change_var;
    change_var.clear();
    std::vector<std::vector<int> > change_groups;
    int a=1;
    if (meta_diff.snakes[0][0] != -1 && meta_diff.snakes[0][1] != -1) {
        a=0;
    }
    for (; a<meta_diff.snakes.size(); a++) {
        int * a_start=&meta_diff.snakes[a][0];
        int * b_start=&meta_diff.snakes[a][1];
        int * a_mid=&meta_diff.snakes[a][2];
        int * b_mid=&meta_diff.snakes[a][3];
        int * a_end=&meta_diff.snakes[a][4];
        int * b_end=&meta_diff.snakes[a][5];
        
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
            diff.changes.push_back(change_var);
            for (int b=0; b<change_var.a_changes.size(); b++) {
                diff.diff_a.push_back(change_var.a_changes[b]);
            }
            for (int b=0; b<change_var.b_changes.size(); b++) {
                diff.diff_b.push_back(change_var.b_changes[b]);
            }
            //start again
            change_var.clear();
        }
    }
    if (change_var.a_changes.size()!=0 || change_var.b_changes.size()!=0) {
        diff.changes.push_back(change_var);
        for (int b=0; b<change_var.a_changes.size(); b++) {
            diff.diff_a.push_back(change_var.a_changes[b]);
        }
        for (int b=0; b<change_var.b_changes.size(); b++) {
            diff.diff_b.push_back(change_var.b_changes[b]);
        }
        change_var.clear();
    }

    return diff;
}

// Takes a Difference object that has it's changes vector filled and parses to
// find substitution chunks. It then runs a secondary diff to find diffrences
// between the elements of each version of the line
template<class T> Difference sesSecondary(Difference & text_diff, metaData<T> & meta_diff){
    for (int a=0; a<text_diff.changes.size(); a++) {
        Change* current= &text_diff.changes[a];
        if (current->a_changes.size()==0 || current->b_changes.size()==0)
        {
            continue;
        }
        else if (current->a_changes.size()==current->b_changes.size())
        {
            for (int b=0; b<current->a_changes.size(); b++) {
                metaData<typeof(*meta_diff.a)[current->a_changes[b]]> meta_second_diff;
                Difference second_diff;
                meta_second_diff=sesSnapshots(
                                (*meta_diff.a)[current->a_changes[b]],
                                (*meta_diff.b)[current->b_changes[b]]);
                sesSnakes(meta_second_diff);
                second_diff=sesChanges(meta_second_diff);
                current->a_characters.push_back(second_diff.diff_a);
                current->b_characters.push_back(second_diff.diff_b);
            }
        }
//        else{
//            current->a_characters.push_back(std::vector<int>());
//            current->b_characters.push_back(std::vector<int>());
//        }
    }
    return text_diff;
}
// formats and outputs a Difference object to the ofstream
#endif
