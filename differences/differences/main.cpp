/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm
 
 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#include <iostream>
#include <string>
#include <vector>
#include <fstream>
using std::cout; using std::endl; using std::cin; using std::string; using std::vector;
int main(int argc, const char * argv[])
{
    // Errors if bad arguments /////////////////////////////////////////////////////////////////
    if (argc != 4) {
        std::cerr << "Usage: " << argv[0] << " input file name 1, input file name 2, output file name\n";
        return 1;
    }
    
    std::ifstream file1_in(argv[1]);
    if (!file1_in.good()) {
        std::cerr << "Can't open " << argv[1] << " to read.\n";
        file1_in.close();
        exit(1);
    }
    
    std::ifstream file2_in(argv[2]);
    if (!file2_in.good()) {
        std::cerr << "Can't open " << argv[2] << " to read.\n";
        file1_in.close();
        file2_in.close();
        
        exit(1);
    }
    
    std::ofstream file_out;
    file_out.open(argv[3]);
    if (!file_out.good()) {
        std::cerr << "Can't open " << argv[3] << " to write.\n";
        file1_in.close();
        file2_in.close();
        file_out.close();
        exit(1);
    }
    vector<string> * sample_out= new vector<string>;
    vector<string> * check_out= new vector<string>;
    string line;
    while (getline(file1_in, line)) {
        sample_out->push_back(line);
    }
    while (getline(file2_in, line)) {
        check_out->push_back(line);
    }
    
    
    return 0;
}

