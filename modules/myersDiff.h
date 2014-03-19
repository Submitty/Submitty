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
#define tab "    "
#define OtherType 0
#define StringType 1
#define VectorStringType 2
#define VectorVectorStringType 3
#define VectorVectorOtherType 4
#define VectorOtherType 5

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

template<class T> Difference<T> ses(T* a, T* b, bool secondary=false);
template<class T> Difference<T> ses(T & a, T & b, bool secondary=false);
template<class T> Difference<T> ses(Difference<T> & text_diff, bool secondary=false);
template<class T> Difference<T> sesHelper(T& a, T& b);
template<class T> Difference<T> sesHelper(T* a, T* b);
template<class T> Difference<T> sesHelper(Difference<T> & text_diff);
template<class T> Difference<T> sesSnakes(Difference<T> & text_diff);
template<class T> Difference<T> sesChanges(Difference<T> & text_diff);
template<class T> Difference<T> sesJSON(Difference<T> & text_diff);
template<class T> Difference<T> sesSecondary(Difference<T> & text_diff);
template<class T> Difference<T> printJSONhelper(Difference<T> & text_diff,
                                        std::ofstream & file_out, int type=0);

// changes passing by refrence to pointers
template<class T> Difference<T> ses(T& a, T& b, bool secondary){
    return ses(&a, &b, secondary);
}

// Runs all the ses functions
template<class T> Difference<T> ses(T* a, T* b, bool secondary){
    Difference<T> text_diff= sesHelper((T*)a, (T*)b);
    sesSnakes(text_diff);
    sesChanges(text_diff);
    if (secondary) {
        sesSecondary(text_diff);
    }
    return text_diff;
}
// Runs all the ses functions, passed a Diffrence object rather than 2 objects
template<class T> Difference<T> ses(Difference<T>& text_diff, bool secondary){
    text_diff=sesHelper(text_diff);
    sesSnakes(text_diff);
    sesChanges(text_diff);
    if (secondary) {
        sesSecondary(text_diff);
    }
    return text_diff;
}
// Converts a Diffrence object into 2 objects of type T to pass to sesHelper
template<class T> Difference<T> sesHelper(Difference<T>& text_diff){
    text_diff.changes.clear();
    text_diff.diff_a.clear();
    text_diff.diff_b.clear();
    text_diff.snakes.clear();
    text_diff.snapshots.clear();
    text_diff=sesHelper(text_diff.a, text_diff.b);
    return text_diff;
}

// changes passing by refrence to pointers
template<class T> Difference<T> sesHelper(T& a, T& b){
    return sesHelper(&a, &b);
}

// runs shortest edit script. Saves traces in snapshots,
// the edit distance in distance and pointers to objects a and b
template<class T> Difference<T> sesHelper(T* a, T* b){
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

    for (int b=0; b<(n+m)+(n+m); b++) {
        v[b]=0;
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

// takes a Difference object with snapshots and parses to find the "snake"
// - a path that leads from the start to the end of both of a and b
template<class T> Difference<T> sesSnakes(Difference<T> & text_diff){
    int n=text_diff.n;
    int m=text_diff.m;
    
    text_diff.snakes.clear();

    int point[2]={n,m};
    // loop through the snapshots until all diffrences have been recorded
    for ( int d =int(text_diff.snapshots.size() - 1) ;
         (point[0] > 0 || point[1] > 0) && d>=0 ; d-- ){
        
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

// Takes a Difference object and parses the snake to constuct a vector of
// Change objects, which each hold the diffrences between a and b, lumped
// by if they are neighboring. Also fills diff_a and diff_b with the diffrences
// All diffrences are stored by element number
template<class T> Difference<T> sesChanges(Difference<T> & text_diff){
    text_diff.changes.clear();
    text_diff.diff_a.clear();
    text_diff.diff_b.clear();
    
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
            for (int b=0; b<change_var.a_changes.size(); b++) {
                text_diff.diff_a.push_back(change_var.a_changes[b]);
            }
            for (int b=0; b<change_var.b_changes.size(); b++) {
                text_diff.diff_b.push_back(change_var.b_changes[b]);
            }
            //start again
            change_var.clear();
        }
    }
    if (change_var.a_changes.size()!=0 || change_var.b_changes.size()!=0) {
        text_diff.changes.push_back(change_var);
        for (int b=0; b<change_var.a_changes.size(); b++) {
            text_diff.diff_a.push_back(change_var.a_changes[b]);
        }
        for (int b=0; b<change_var.b_changes.size(); b++) {
            text_diff.diff_b.push_back(change_var.b_changes[b]);
        }
        change_var.clear();
    }

    return text_diff;
}

// Takes a Difference object that has it's changes vector filled and parses to
// find substitution chunks. It then runs a secondary diff to find diffrences
// between the elements of each version of the line
template<class T> Difference<T> sesSecondary(Difference<T> & text_diff){
    for (int a=0; a<text_diff.changes.size(); a++) {
        Change* current= &text_diff.changes[a];
        if (current->a_changes.size()==0 || current->b_changes.size()==0)
        {
            continue;
        }
        else if (current->a_changes.size()==current->b_changes.size())
        {
            for (int b=0; b<current->a_changes.size(); b++) {
                Difference<typeof(*text_diff.a)[current->a_changes[b]]> second_diff;
                second_diff=sesHelper(
                                (*text_diff.a)[current->a_changes[b]],
                                (*text_diff.b)[current->b_changes[b]]);
                sesSnakes(second_diff);
                sesChanges(second_diff);
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
template<class T> Difference<T> printJSONhelper(Difference<T> & text_diff,
                                            std::ofstream & file_out, int type){
    std::string diff1_name;
    std::string diff2_name;
    file_out<<"{"<<std::endl
    <<"\"differences\":["<<std::endl
    <<tab;
    switch (type) {
            // StringType;
            // VectorStringType;
            // VectorVectorStringType;
            // VectorVectorOtherType;
            // VectorOtherType;

        case StringType:
            diff1_name="line";
            diff2_name="char";
            break;
            
        case VectorStringType:
        case VectorOtherType:
            diff1_name="word";
            diff2_name="char";
            break;
            
        case VectorVectorStringType:
        case VectorVectorOtherType:
            diff1_name="line";
            diff2_name="word";
            break;
            
        default:
            diff1_name="line";
            diff2_name="char";
            break;
    }
    
    for (int a=0; a<text_diff.changes.size(); a++) {
        if (a>0) {
            file_out<<", ";
        }
        file_out<<"{"<<std::endl;

        file_out<<tab<<tab<<"\"student\":"<<std::endl;
        
        file_out<<tab<<tab<<"{"<<std::endl;

        file_out<<tab<<tab<<tab<<"\"start\": "
                <<text_diff.changes[a].a_start<<","<<std::endl;
        if (text_diff.changes[a].a_changes.size()>0) {
            file_out<<tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
                    <<tab<<tab<<tab<<tab;
            for (int b=0; b<text_diff.changes[a].a_changes.size(); b++) {
                if (b>0) {
                    file_out<<", ";
                }
                file_out<<"{"<<std::endl;
                file_out<<tab<<tab<<tab<<tab<<tab
                <<"\""+diff1_name+"_number\": "
                <<text_diff.changes[a].a_changes[b]<<std::endl;
                //insert code to display word changes here
                if (text_diff.changes[a].a_characters.size()>=b && text_diff.changes[a].a_characters.size()>0) {
                    if (text_diff.changes[a].a_characters[b].size()>0){
                        file_out<<tab<<tab<<tab<<tab<<tab
                                <<"\""+diff2_name+"_number\":[ ";
                    }
                    for (int c=0; c< text_diff.changes[a].a_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<text_diff.changes[a].a_characters[b][c];
                    }
                    if (text_diff.changes[a].a_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
                file_out<<tab<<tab<<tab<<tab<<"}";
                
            }
            file_out<<std::endl<<tab<<tab<<tab<<"]"<<std::endl;
        }
        
        file_out<<tab<<tab<<"},"<<std::endl;
        file_out<<tab<<tab<<"\"instructor\":"<<std::endl
        <<tab<<tab<<"{"<<std::endl;
        
        file_out<<tab<<tab<<tab<<"\"start\":"
        <<text_diff.changes[a].b_start<<","<<std::endl;
        if (text_diff.changes[a].b_changes.size()>0) {
            file_out<<tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
                    <<tab<<tab<<tab<<tab;
            for (int b=0; b<text_diff.changes[a].b_changes.size(); b++) {
                if (b>0) {
                    file_out<<", " ;
                }
                file_out<<"{"<<std::endl;
                file_out<<tab<<tab<<tab<<tab<<tab
                <<"\""+diff1_name+"_number\": " <<text_diff.changes[a].b_changes[b]
                <<std::endl;
                //insert code to display word changes here
                if (text_diff.changes[a].b_characters.size()>=b && text_diff.changes[a].b_characters.size()>0) {
                    if (text_diff.changes[a].b_characters[b].size()>0){
                        file_out<<tab<<tab<<tab<<tab<<tab
                                <<"\""+diff2_name+"_number\":[ ";
                    }
                    for (int c=0; c< text_diff.changes[a].b_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<text_diff.changes[a].b_characters[b][c];
                    }
                    if (text_diff.changes[a].b_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
                
                file_out<<tab<<tab<<tab<<tab<<"}";
                
            }
            file_out<<std::endl<<tab<<tab<<tab<<"]"<<std::endl;
            file_out<<tab<<tab<<"}"<<std::endl;
        }
        file_out<<tab<<"}";
    }
    file_out<<std::endl<<"]"<<std::endl;
    file_out<<"}"<<std::endl;

    
    return text_diff;
}

// Finds out if the Difference object is of a specific type and passes along the
// information for better printing

template<class> Difference<std::string> printJSON
    (Difference<std::string> & text_diff, std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, StringType);
}

template<class> Difference<std::vector<std::string> > printJSON
    (Difference<std::vector<std::string> > &text_diff, std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, VectorStringType);
}

template<class> Difference<std::vector< std::vector<std::string> > > printJSON
(Difference<std::vector< std::vector<std::string> > >  & text_diff,
                                        std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, VectorVectorStringType);
}

template<class T> Difference<std::vector< std::vector<T> > > printJSON
(Difference<std::vector< std::vector<T> > > & text_diff, std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, VectorVectorOtherType);
}

template<class T> Difference<std::vector<T> > printJSON
(Difference<std::vector<T> > & text_diff, std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, VectorOtherType);
}

template<class T> Difference<T> printJSON
(Difference<T> & text_diff, std::ofstream & file_out)
{
    return printJSONhelper(text_diff, file_out, StringType);
}

#undef tab
#undef StringType
#undef VectorStringType
#undef VectorVectorStringType
#undef VectorVectorOtherType
#undef VectorOtherType
#undef OtherType
#endif
