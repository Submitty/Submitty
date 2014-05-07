#include <stdlib.h>
#include <time.h>

#include <string>
#include <iostream>
#include <fstream>
#include <sstream>

#include "../modules/modules.h"
#include "TestCase.h"

#include "../CSCI1200/HW1/CONFIG/config.h"

using std::ifstream;
using std::ofstream;
using std::string;
using std::cout;
using std::endl;
using std::cerr;

string random_hash();
int execute(const string& cmd);
string to_string(int i);

int main(int argc, char* argv[]) {
	cout << "Running runner..." << endl;

	// Make sure arguments are entered correctly

	if (argc != 3) {
		cout << "Incorrect # of arguments:" << argc << endl;
		cout << "Usage : " << endl
				<< "     ./runner path/to/user submission_num" << endl;
		return 1;
	}

	// Initialize random number seed
	srand(time(NULL));

	// Extract Paths

	string user_path = argv[1];
	string submission_dir = argv[2];

	cout << "User path: " << user_path << endl;

	// Copy files to temporary directory
	// TODO this cpp file should not do this, it should be done at a higher
	// level, then this should be run at a lower permissions level
	string tmp_path = "/tmp/" + random_hash();

	if (execute("cp -r " + user_path + " " + tmp_path)) {
		cerr << "FAIL: Copy to tmp directory" << endl;
		return 2;
	}

	// Try to make GRADES directory
	if (execute("cd " + user_path + "/" + submission_dir + "; mkdir GRADES")){
		cerr << "COULD NOT CREATE GRADES DIRECTORY" << endl;
		return 6;
	}

	// Get readme grade (does it exist?)
	ofstream readme_file((user_path + "/" + submission_dir + "/GRADES/readme_grade.txt").c_str());
	if (execute("cd " + tmp_path + "/" + submission_dir + "/FILES" + "; [ -f README.txt ]")){
		cerr << "NO README.txt" << endl;
		readme_file << 0;
	}else{
		readme_file << readmeTestCase.points();
	}
	readme_file.close();

	// Compile files in tmp directory with compile command from config
	ofstream compilation_file((user_path + "/" + submission_dir + "/GRADES/compilation_grade.txt").c_str());
	if (execute(
			"cd " + tmp_path + "/" + submission_dir + "/FILES && "
					+ compile_command)){
		cerr << "COMPILATION FAILED" << endl;
		return 0;
		compilation_file << 0;
	}else{
		compilation_file << compilationTestCase.points();
	}
	compilation_file.close();

	// Create the .submit.out directory
	if (execute(
			"cd " + tmp_path + "/" + submission_dir
					+ " && mkdir .submit.out")) {
		return 4;
	}

	// Run each test case and create output files
	for (unsigned int i = 0; i < num_testcases; i++) {
		string cmd = testcases[i].command();
		if (cmd != "") {
			ofstream testcase_file((tmp_path + "/" + submission_dir + "/.submit.out/" + testcases[i].filename()).c_str());
			if (execute(
					"cd " + tmp_path + "/" + submission_dir + "/FILES && " + cmd
							+ " 1>../.submit.out/test" + to_string(i+1)
							+ "_cout.txt 2>../.submit.out/test" + to_string(i+1)
							+ "_cerr.txt")) {
				cerr << "ERROR RUNNING TEST CASE " << i << endl;
			}
			testcase_file.close();
		}
	}

	// Copy over .submit.out from tmp to user submission directory
	if (execute("mv " + tmp_path + "/" + submission_dir + "/.submit.out " + user_path + "/" + submission_dir + "/.submit.out")){
		return 5;
	}

	//TODO MAKE CALL TO VALIDATOR!!!

}

// Executes command (from shell) and returns error code (0 = success)
int execute(const string& cmd) {
	cout << "Executing command: " << cmd << endl;
	return system(cmd.c_str());
}

// Returns a random hash that is suitable to be used as a temporary
// directory name
string random_hash() {
	static const char alphanum[] = "0123456789"
			"ABCDEFGHIJKLMNOPQRSTUVWXYZ"
			"abcdefghijklmnopqrstuvwxyz";
	string hash(16, ' ');
	for (int i = 0; i < 16; ++i) {
		hash[i] = alphanum[rand() % (sizeof(alphanum) - 1)];
	}
	return hash;
}

string to_string(int i) {
	std::ostringstream tmp;
	tmp << i;
	return tmp.str();
}
