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

void printTestCase(std::ostream& out, TestCase test ){
	std::string hidden = (test.hidden()) ? "true" : "false";
	std::string extracredit = 
		(test.extracredit()) ? "true" : "false";

	out << "\t{" << std::endl;
	out << "\t\t\"title\": \""<< test.title() << "\"," 
		<< std::endl;
	out << "\t\t\"details\": \"" <<test.details() << "\"," 
		<< std::endl;
	out << "\t\t\"points\": " << test.points() << "," << std::endl;
	out << "\t\t\"hidden\": " << hidden << "," << std::endl;
	out << "\t\t\"extracredit\": " << extracredit << "," << std::endl;
	out << "\t\t\"expected_output_file\": " << "\"" << test.expected() << "\"" << std::endl;
	out << "\t}";
}

int main(int argc, char* argv[]){
	if(argc != 2){
		std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
		return 0;
	}


	std::ofstream init;
	init.open( argv[1], std::ios::out );

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
	init << "\t\"total_pts\": " << auto_pts + ta_pts << "," << std::endl;

	init << "\t\"num_testcases\": " << num_testcases << "," << std::endl;

	init << "\t\"testcases\": [" << std::endl;
	
	printTestCase(init, readmeTestCase);
	init << "," << std::endl;
	printTestCase(init, compilationTestCase);
	init << "," << std::endl;
	for(unsigned int i = 0; i < num_testcases; i++){
		printTestCase(init, testcases[i]);
		if(i != num_testcases - 1)
			init << "," << std::endl;
	}
	
	init << " ]\n}" << std::endl;
	

	init.close();

	return 0;
}
