/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license.
 */

#ifndef differences_runDiff_h
#define differences_runDiff_h

#define OtherType 0
#define StringType 1
#define VectorStringType 2
#define VectorVectorStringType 3
#define VectorVectorOtherType 4
#define VectorOtherType 5

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>
#include "modules/modules.h"

void readFileList(std::string input, std::string & sample_file, std::vector<std::string>& student_files){
    std::ifstream in_file(input.c_str());
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

std::string getFileInput(std::string file){
    std::ifstream input( file.c_str(), std::ifstream::in );
    if( !input ){
        std::cout << "ERROR: File "
        << file << " does not exist"
        << std::endl;
        return "";
    }
    const std::string output = std::string( std::istreambuf_iterator<char>(input),
                                           std::istreambuf_iterator<char>() );
    return output;
}

void runFiles(std::string input){ //input is a list of file names, the first of which is the instructor file
    std::string sample_file;
    std::vector<std::string> student_files;
    readFileList(input, sample_file, student_files); //read all the file names from the input file

    vectorOfWords contents, sample_text;
    std::string text;
    text=getFileInput(sample_file); // get the text from the instructor file
    sample_text=stringToWords(text);
    for (int a=0; a<student_files.size(); a++) {
        contents.clear();
        text=getFileInput(student_files[a]); //get the text from the student file
        contents=stringToWords(text);
        //get the diffrences
        Difference text_diff=ses(&contents,&sample_text, true);
        std::string file_name(student_files[a]);
        file_name.erase(student_files[a].size()-4, student_files[a].size());
        std::ofstream file_out;
        file_out.open((file_name+"_out.json").c_str()); //edit the name to add _out
        if (!file_out.good()) {
            std::cerr << "Can't open " << student_files[a]+"_out" << " to write.\n";
            file_out.close();
            continue;
        }

        text_diff.printJSON(file_out, VectorVectorStringType); //print the diffrences as JSON files
    }
}

void runFilesDiff(std::string input){ //input is a list of file names, the first of which is the instructor file
    std::string sample_file;
    std::vector<std::string> student_files;
    readFileList(input, sample_file, student_files); //read all the file names from the input file

    std::string contents, sample_text;
    std::string text;
    text=getFileInput(sample_file); // get the text from the instructor file
    sample_text=(text);
    for (int a=0; a<student_files.size(); a++) {
        contents.clear();
        text=getFileInput(student_files[a]); //get the text from the student file
        contents=(text);
        //get the diffrences
        Difference text_diff=diffLine(contents, sample_text);
        std::string file_name(student_files[a]);
        file_name.erase(student_files[a].size()-4, student_files[a].size());
        std::ofstream file_out;
        file_out.open((file_name+"_out.json").c_str()); //edit the name to add _out
        if (!file_out.good()) {
            std::cerr << "Can't open " << student_files[a]+"_out" << " to write.\n";
            file_out.close();
            continue;
        }

        text_diff.printJSON(file_out, VectorVectorStringType); //print the diffrences as JSON files
    }
}


#endif
