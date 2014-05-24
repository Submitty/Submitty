/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/
#include <stdlib.h>
#include <string>
#include <iostream>
#include <sstream>

#include "modules/modules.h"
#include "SCRIPTS/TestCase.h"

//#include "../sampleConfig/HW1/config.h"

using std::ifstream;
using std::ofstream;
using std::string;
using std::cout;
using std::endl;
using std::cerr;

int execute(const string& cmd);
string to_string(int i);

int main(int argc, char* argv[]) {
	cout << "Running User Code..." << endl;

	// Make sure arguments are entered correctly
	if (argc != 1) {
		//Pass in the current working directory to run the programs
		cout << "Incorrect # of arguments:" << argc << endl;
		cout << "Usage : " << endl
				<< "     ./runner" << endl;
		return 2;
	}
	if(compile_command != ""){
		int compile = execute(compile_command + " 2>.compile_out.txt");
		if(compile){
			cerr << "COMPILATION FAILED" << endl;
			exit(1);
		}
	}
	int n = 0;
	if(readmeTestCase != NULL)
		n++;
	if(compileTestCase != NULL)
		n++;

	// Run each test case and create output files
	for (unsigned int i = 0 + n; i < num_testcases; i++) {
		cout << testcases[i].command() << std::endl;

		if(testcases[i].recompile()){
			int recomp = execute(testcases[i].compile_cmd() + " 2>.compile_out_" + to_string(i) + ".txt");
			if(recomp){
				cerr << "Recompilation in test case " << testcases[i].title() << " failed; No points will be awarded." << std::endl;
				continue;
			}
		}

		string cmd = testcases[i].command();
		if (cmd != "") {
			int exit_no = execute(cmd + " 1>test" + to_string(i+1-n)
							+ "_cout.txt 2>test" + to_string(i+1-n)
							+ "_cerr.txt");
		}

		// Recompile the original in case the executable was overwritten
		if(testcases[i].recompile() && compile_command != ""){
			execute(compile_command);
		}
	}

	return 0;
}

// Executes command (from shell) and returns error code (0 = success)
int execute(const string& cmd) {
	return system(cmd.c_str());
}

string to_string(int i) {
	std::ostringstream tmp;
	tmp << i;
	return tmp.str();
}
