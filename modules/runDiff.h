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

void runFiles(std::string input){ //input is a list of file names, the first of which is the instructor file
    std::string sample_file;
    std::vector<std::string> student_files;
    readFileList(input, sample_file, student_files); //read all the file names from the input file
    std::vector< std::vector<std::string> >contents, sample_text;
    getFileInput(sample_file, sample_text); // get the text from the instructor file
    for (int a=0; a<student_files.size(); a++) {
        contents.clear();
        getFileInput(student_files[a], contents); //get the text from the student file
        //get the diffrences
        Difference< std::vector < std::vector <std::string> > > text_diff=ses(&sample_text, &contents, true);
        std::ofstream file_out;
        std::string file_name(student_files[a]);
        file_name.erase(student_files[a].size()-4, student_files[a].size());
        file_out.open(file_name+"_out.json"); //edit the name to add _out
        if (!file_out.good()) {
            std::cerr << "Can't open " << student_files[a]+"_out" << " to write.\n";
            file_out.close();
            continue;
        }

        printJSON(text_diff, file_out); //print the diffrences as JSON files
    }
}

#endif
