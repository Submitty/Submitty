/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <iostream>
#include <fstream>
#include "../modules/modules.h"
#include "config.h"

/*Generates a file in json format containing all of the information defined in 
config.h for easier parsing.*/

int main(int argc, char* argv[]){

	std::ofstream init;
	init.open( "config.json", std::ios::out );

	if(!init.is_open()){
		std::cout << "ERROR: unable to open new file for initialization... \
Now Exiting" << std::endl;
		return 0;
	}

	init << "{\n\t\"hw_num\": " << hw_num << "," <<  std::endl;
	init << "\t\"hw_name\": \"" << hw_name << "\"," << std::endl;

	init << "\t\"max_submissions\": " << max_submissions << "," << std::endl;

	init << "\t\"auto_pts\": " << auto_pts << "," << std::endl;
	init << "\t\"ta_pts\": " << ta_pts << "," << std::endl;

	init << "\t\"num_testcases\": " << num_testcases << "," << std::endl;

	init << "\t\"testcases\": [" << std::endl;
	
	for(unsigned int i = 0; i < num_testcases; i++){
		std::string hidden = (testcases[i].hidden()) ? "true" : "false";
		std::string extracredit = 
			(testcases[i].extracredit()) ? "true" : "false";
	
		init << "\t{" << std::endl;
		init << "\t\t\"title\": \""<< testcases[i].title() << "\"," 
			<< std::endl;
		init << "\t\t\"details\": \"" <<testcases[i].details() << "\"," 
			<< std::endl;
		init << "\t\t\"points\": " << testcases[i].points() << "," << std::endl;
		init << "\t\t\"hidden\": " << hidden << "," << std::endl;
		init << "\t\t\"extracredit\": " << extracredit << std::endl;
		init << "\t}";
		if(i != num_testcases - 1)
			init << "," << std::endl;
	}
	
	init << " ]\n}" << std::endl;
	

	init.close();

	return 0;
}
