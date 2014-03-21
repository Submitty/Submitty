/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license.
 */

#ifndef differences_runDiff_h
#define differences_runDiff_h

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>

void readFileList(std::string input, std::string & sample_file, std::vector<std::string>& student_files){
    std::ifstream in_file(input);
    if (!in_file.good()) {
        std::cerr << "Can't open " << input << " to read.\n";
        in_file.close();
        return;
    }
    std::string line;
    if ( getline(in_file, line) ) {
        sample_file=line;
    }
    while (getline(in_file, line)) {
        student_files.push_back(line);
    }
    in_file.close();
}

void getFileInput(std::string file, std::vector< std::vector<std::string> >& contents){
    std::ifstream in_file(file);
    if (!in_file.good()) {
        std::cerr << "Can't open " << file << " to read.\n";
        in_file.close();
        return;
    }
    std::stringstream line;
    std:: string word;
    while (getline(in_file, word)) {
        std::vector<std::string> text;
        while (line.width()>0) {
            line << word;
            text.push_back(word);
        }
        contents.push_back(text);
    }
    in_file.close();
    
    
}
void getFileInput(std::string file, std::vector<std::string>& contents){
    std::ifstream in_file(file);
    if (!in_file.good()) {
        std::cerr << "Can't open " << file << " to read.\n";
        in_file.close();
        return;
    }
    std::string line;
    while (getline(in_file, line)) {
        contents.push_back(line);
    }
    
    in_file.close();

    
}


#endif
