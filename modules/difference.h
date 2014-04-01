/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef __differences__difference__
#define __differences__difference__
#include <string>
#include <vector>

#define tab "    "
#define OtherType 0
#define StringType 1
#define VectorStringType 2
#define VectorVectorStringType 3
#define VectorVectorOtherType 4
#define VectorOtherType 5

class Change{
public:
    int a_start;
    int b_start;
    std::vector<int> a_changes;
    std::vector<int> b_changes;
    std::vector< std::vector< int > >  a_characters;
    std::vector< std::vector< int > >  b_characters;
    void clear();
};

void Change::clear(){
    a_start=b_start=-1;
    a_changes.clear();
    b_changes.clear();
}

class Difference{
public:
    std::vector<Change> changes;
    std::vector<int> diff_a;
    std::vector<int> diff_b;
    int distance;
    void printJSON(std::ostream & file_out, int type);
};

void Difference::printJSON(std::ostream & file_out, int type){
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
    
    for (unsigned int a=0; a<changes.size(); a++) {
        if (a>0) {
            file_out<<", ";
        }
        file_out<<"{"<<std::endl;
        
        file_out<<tab<<tab<<"\"student\":"<<std::endl;
        
        file_out<<tab<<tab<<"{"<<std::endl;
        
        file_out<<tab<<tab<<tab<<"\"start\": "
        <<changes[a].a_start;
        if (changes[a].a_changes.size()>0)
        {
            file_out<<","<<std::endl;
            file_out<<tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
            <<tab<<tab<<tab<<tab;
            for (unsigned int b=0; b<changes[a].a_changes.size(); b++) {
                if (b>0) {
                    file_out<<", ";
                }
                file_out<<"{"<<std::endl;
                file_out<<tab<<tab<<tab<<tab<<tab
                <<"\""+diff1_name+"_number\": "
                <<changes[a].a_changes[b];
                //insert code to display word changes here
                if (changes[a].a_characters.size()>=b && changes[a].a_characters.size()>0) {
                    if (changes[a].a_characters[b].size()>0){
                        file_out<<", "<<std::endl;
                        file_out<<tab<<tab<<tab<<tab<<tab
                        <<"\""+diff2_name+"_number\":[ ";
                    }
                    else{
                        file_out<<std::endl;
                    }

                    for (unsigned int c=0; c< changes[a].a_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<changes[a].a_characters[b][c];
                    }
                    if (changes[a].a_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
                else{
                    file_out<<std::endl;
                }
                file_out<<tab<<tab<<tab<<tab<<"}";
            }
            file_out<<std::endl<<tab<<tab<<tab<<"]"<<std::endl;
        }
        else{
            file_out<<std::endl;
        }
        
        file_out<<tab<<tab<<"},"<<std::endl;
        file_out<<tab<<tab<<"\"instructor\":"<<std::endl
        <<tab<<tab<<"{"<<std::endl;
        
        file_out<<tab<<tab<<tab<<"\"start\":"
        <<changes[a].b_start;
        if (changes[a].b_changes.size()>0)
        {
            file_out<<","<<std::endl;
            file_out<<tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
            <<tab<<tab<<tab<<tab;
            for (unsigned int b=0; b<changes[a].b_changes.size(); b++) {
                if (b>0) {
                    file_out<<", " ;
                }
                file_out<<"{"<<std::endl;
                file_out<<tab<<tab<<tab<<tab<<tab
                <<"\""+diff1_name+"_number\": " <<changes[a].b_changes[b];
                //insert code to display word changes here
                if (changes[a].b_characters.size()>=b && changes[a].b_characters.size()>0) {
                    if (changes[a].b_characters[b].size()>0){
                        file_out<<", "<<std::endl;
                        file_out<<tab<<tab<<tab<<tab<<tab
                        <<"\""+diff2_name+"_number\":[ ";
                    }
                    else{
                        file_out<<std::endl;
                    }
                    for (unsigned int c=0; c< changes[a].b_characters[b].size(); c++) {
                        if (c>0) {
                            file_out<<", ";
                        }
                        file_out<<changes[a].b_characters[b][c];
                    }
                    if (changes[a].b_characters[b].size()>0){
                        file_out<<" ]"<<std::endl;
                    }
                }
                else{
                    file_out<<std::endl;
                    
                }
                file_out<<tab<<tab<<tab<<tab<<"}";
                
            }
            file_out<<std::endl<<tab<<tab<<tab<<"]"<<std::endl;
        }
        else{
            file_out<<std::endl;
        }
        file_out<<tab<<tab<<"}"<<std::endl;
        file_out<<tab<<"}";
    }
    file_out<<std::endl<<"]"<<std::endl;
    file_out<<"}"<<std::endl;
    
    
    return ;
}

#endif /* defined(__differences__difference__) */
