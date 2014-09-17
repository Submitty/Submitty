/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
 */

#ifndef __differences__tokens__
#define __differences__tokens__
#include <string>
#include <vector>
#include "testResults.h"

class Tokens: public TestResults{
public:
    Tokens();
    std::vector<int> tokens_found;
    int num_tokens;
    bool partial;
    int tokensfound;
    bool harsh;
    void printJSON(std::ostream & file_out);
    float grade();
};

Tokens::Tokens() :
  TestResults(1,""), num_tokens(0), tokensfound(0), partial(true), harsh(false) {
}

/*
float Tokens::grade() {
  return my_grade;
  // FIXME
  / *
	for (unsigned int i = 0; i < tokens_found.size(); i++) {
		if (tokens_found[i] != -1)
			tokensfound++;
	}
	if (partial)
		return (float) tokensfound / (float) num_tokens;
	else if (tokensfound == num_tokens || (!harsh && tokensfound != 0)) {
		return 1;
	}
	return 0;
  * /
}
*/

Tokens::Tokens() :
  TestResults(1,""), num_tokens(0), tokensfound(0), partial(true), harsh(false) {
}

void Tokens::printJSON(std::ostream & file_out){
    std::string partial_str = (partial) ? "true" : "false";

    file_out << "{\n\t\"tokens\": " << num_tokens << "," << std::endl;
    file_out << "\t\"found\": [";
    for(unsigned int i = 0; i < tokens_found.size(); i++){
        file_out << tokens_found[i];
        if(i != tokens_found.size() - 1){
            file_out << ", ";
        }
        else{
            file_out << " ]," << std::endl;
        }
    } 
    file_out << "\t\"num_found\": " << tokensfound << "," << std::endl;
    file_out << "\t\"partial\": " << partial_str << "," << std::endl;
    file_out << "}" << std::endl;
    return;
}
#endif
