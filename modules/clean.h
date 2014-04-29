/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <string>
#include <sstream>

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
		new_message.append(line);
		if(message){
			new_message.append('\n');
		}
		else{
			break;
		}
	}
}