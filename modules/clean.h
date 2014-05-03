/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license.
 */
#ifndef __CLEAN__
#define __CLEAN__

#include <string>
#include <sstream>
#include "modules.h"

typedef std::vector< std::vector<std::string> > vectorOfWords;
typedef std::vector<std::string> vectorOfLines;

std::string clean(const std::string & content){
	std::stringstream message;
	message.str(content);

	std::string new_message = "";

	std::string line;
	while(!message){
		std::getline(message, line);
		if(line[line.length() - 1] == '\r'){
			line = line.substr(0, line.length() - 2);
		}
        if(line[0] == '\r'){
			line = line.substr(1, line.length() - 1);
		}
		new_message.append(line);
		if(message){
			//new_message.append('\n');
		}
		else{
			break;
		}
	}
    return "";
}

vectorOfWords stringToWords(std::string text){
    vectorOfWords contents;
    std::stringstream input(text);

    std::string word;
    while (getline(input, word)) {
        std::vector<std::string> text;
        std::stringstream line;
        line<<word;
        while (line>>word) {
            text.push_back(word);
        }
        contents.push_back(text);
    }
    return contents;
}

vectorOfLines stringToLines(std::string text){
    vectorOfLines contents;
    std::stringstream input(text);

    std::string line;
    while (getline(input, line)) {
        contents.push_back(line);
    }
    return contents;
}

std::string linesToString(vectorOfLines text){
    std::string contents;

    for (int a=0; a<text.size(); a++){
        contents+=text[a]+'\n';
    }
    return contents;
}

vectorOfWords linesToWords(vectorOfLines text){
    vectorOfWords contents;
    for (int a=0; a<text.size(); a++) {
        std::string word;
        std::stringstream line(text[a]);
        std::vector<std::string> temp;
        while (line>>word) {
            temp.push_back(word);
        }
        contents.push_back(temp);
    }
    return contents;
}

std::string wordsToString(vectorOfWords text){
    std::string contents;
    for (int a=0; a<text.size(); a++){
        std::string line;
        if (a>0) {
            contents+="\n";
        }
        for (int b=0; b<text[a].size(); b++){
            if (b>0) {
                line+=" ";
            }
            line+=text[a][b];
        }
        contents+=line;
    }
    return contents;
}

vectorOfLines wordsToLines(vectorOfWords text){
    vectorOfLines contents;
    for (int a=0; a<text.size(); a++){
        std::string line;
        for (int b=0; b<text[a].size(); b++){
            if (b>0) {
                line+=" ";
            }
            line+=text[a][b];
        }
        contents.push_back(line);
    }
    return contents;
}


#endif //__CLEAN__