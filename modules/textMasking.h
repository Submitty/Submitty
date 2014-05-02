/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef differences_textMasking_h
#define differences_textMasking_h
#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>
std::vector< std::string > includelines(
                                const std::string &token,
                                const std::vector< std::string >& text,
                                bool allMatch=false);
std::vector< std::string > includelines(
                                const std::vector<unsigned int> &lines,
                                const std::vector< std::string >&text);
std::vector< std::string > excludelines(
                                const std::string &token,
                                const std::vector< std::string >& text,
                                bool allMatch=false);
std::vector< std::string > excludelines(
                                const std::vector<unsigned int> &lines,
                                const std::vector< std::string >&text);
std::vector< std::string > linesBetween(
                                unsigned int begin,
                                unsigned int end,
                                const std::vector< std::string >&text);
std::vector< std::string > linesOutside(
                                unsigned int begin,
                                unsigned int end,
                                const std::vector< std::string >&text);


std::vector< std::string > includelines(
                                const std::string &token,
                                const std::vector< std::string >& text,
                                bool allMatch){
    std::vector< std::string > output;
    if (token.find('\n')==std::string::npos) {
        //change to use searchToken to find token
        for (int a=0; a<text.size(); a++) {
            if (text[a].find(token)!=std::string::npos){
                output.push_back(text[a]);
            }
        }
    }
    else{
        std::vector<std::string> multTokens=splitTokens(token);
        if (allMatch){
            for (int a=0; a<text.size(); a++) {
                bool match=true;
                for (int b=0; b<multTokens.size(); b++){
                    //change to use searchMultipleTokens to find token
                    if (text[a].find(multTokens[b])==std::string::npos){
                        match=false;
                        break;
                    }
                }
                if (match) {
                    output.push_back(text[a]);
                }
            }
        }
        else{
            for (int a=0; a<text.size(); a++) {
                for (int b=0; b<multTokens.size(); b++){
                    //change to use searchMultipleTokens to find token
                    if (text[a].find(multTokens[b])!=std::string::npos){
                        output.push_back(text[a]);
                        break;
                    }
                }
            }
        }
    }
    return output;
}


std::vector< std::string > includelines(
                                const std::vector<unsigned int> &lines,
                                const std::vector< std::string >&text){
    std::vector< std::string > output;
    for (int a=0; a<lines.size(); a++){
        if (lines[a]>text.size()) {
            continue;
        }
        output.push_back(text[lines[a]]);
    }
    return output;
}

std::vector< std::string > excludelines(
                                const std::string &token,
                                const std::vector< std::string >& text,
                                bool allMatch){
    std::vector< std::string > output;
    if (token.find('\n')==std::string::npos) {
        //change to use searchToken to find token
        for (int a=0; a<text.size(); a++) {
            if (text[a].find(token)==std::string::npos){
                output.push_back(text[a]);
            }
        }
    }
    else{
        std::vector<std::string> multTokens=splitTokens(token);
        if (allMatch){
            for (int a=0; a<text.size(); a++) {
                bool match=true;
                for (int b=0; b<multTokens.size(); b++){
                    //change to use searchMultipleTokens to find token
                    if (text[a].find(multTokens[b])==std::string::npos){
                        match=false;
                        break;
                    }
                }
                if (!match) {
                    output.push_back(text[a]);
                }
            }
        }
        else{
            for (int a=0; a<text.size(); a++) {
                bool match=true;
                for (int b=0; b<multTokens.size(); b++){
                    //change to use searchMultipleTokens to find token
                    if (text[a].find(multTokens[b])!=std::string::npos){
                        match=false;
                        break;
                    }
                }
                if (match) {
                    output.push_back(text[a]);
                }
            }
        }
    }
    return output;
}

std::vector< std::string > excludelines(
                                const std::vector<unsigned int> &lines,
                                const std::vector< std::string >&text){
    std::vector< std::string > output=text;
    for (int a=0; a<lines.size(); a++){
        if (lines[a]>text.size()) {
            continue;
        }
        
        output.erase(output.begin()+lines[a]);
    }
    return output;
}


std::vector< std::string > linesBetween(
                                unsigned int begin,
                                unsigned int end,
                                const std::vector< std::string >&text){
    if (end>text.size()) {
        end=(unsigned int)(text.size());
    }
    if (begin>text.size()) {
        begin=(unsigned int)(text.size());
    }
    std::vector< std::string > output;

    for (int a=begin; a<end; a++) {
        output.push_back(text[a]);
    }
    return output;
}

std::vector< std::string > linesOutside(
                                unsigned int begin,
                                unsigned int end,
                                const std::vector< std::string >&text){
    if (end>text.size()) {
        end=(unsigned int)(text.size());
    }
    if (begin>text.size()) {
        begin=(unsigned int)(text.size());
    }
    std::vector< std::string > output;
    for (int a=0; a<begin; a++) {
        output.push_back(text[a]);
    }
    for (int a=end+1; a<text.size(); a++) {
        output.push_back(text[a]);
    }
    return output;

}




#endif
