/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <iostream>
#include <fstream>
#include "../modules/modules.h"
#include "TestCase.h"
//#include "../sampleConfig/HW1/config.h"

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
	out << "\t}";
}

int main(int argc, char* argv[]){
	if(argc != 2){
		std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
		return 0;
	}

	int total = 0;

	for(unsigned int i = 0; i < num_testcases; i++){
		total += testcases[i].points();
	}
	if( total != auto_pts ){
		std::cout << "ERROR: Automated Points do not match testcases." << std::endl;
		return 1;
	}
	if( total + ta_pts != total_pts ){
		std::cout << "ERROR: Automated Points and TA Points do not match total." << std::endl;
		return 1;
	}

	std::ofstream init;
	init.open( argv[1], std::ios::out );

	if(!init.is_open()){
		std::cout << "ERROR: unable to open new file for initialization... \
Now Exiting" << std::endl;
		return 0;
	}

	init << "{\n\t\"id\": \"" << id << "\"," <<  std::endl;
	init << "\t\"name\": \"" << name << "\"," << std::endl;

	init << "\t\"max_submissions\": " << max_submissions << "," << std::endl;

	init << "\t\"auto_pts\": " << auto_pts << "," << std::endl;
	int visible = 0;
	for(unsigned int i = 0; i < num_testcases; i++){
		if(!testcases[i].hidden())
			visible += testcases[i].points();
	}
	init << "\t\"points_visible\": " << visible << "," << std::endl;
	init << "\t\"ta_pts\": " << ta_pts << "," << std::endl;
	init << "\t\"total_pts\": " << total_pts << "," << std::endl;
	init << "\t\"due_date\": \"" << due_date << "\"," << std::endl;

	init << "\t\"num_testcases\": " << num_testcases << "," << std::endl;

	init << "\t\"testcases\": [" << std::endl;

	for(unsigned int i = 0; i < num_testcases; i++){
		printTestCase(init, testcases[i]);
		if(i != num_testcases - 1)
			init << "," << std::endl;
	}
	
	init << " ]\n}" << std::endl;
	

	init.close();

	return 0;
}
