/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <vector>
#include <string>


/*p_check is an enumerated tyoe used in Check to declare if the 
output from a Check is shown.*/
enum p_check { NEVER = 0, WARNING_OR_FAILURE = 1, ALWAYS = 2 };

/*Check is used to define an analysis of a specific output.
It is given a grading module and expected output of a test
and some parameters for displaying output from the results.*/
class Check{
public:
	//Accessors
	std::string filename() const { return _filename; }
	std::string description() const { return _description; }
	std::string expected() const { return _expected; }
	
	/*Calls the function designated by the function pointer and returns the
	result if successful or -1 otherwise. The arguments of the compare*/
	int compare(std::string &student_out, std::string &expected_out){
		if(cmp_output != 0) return cmp_output(student_out, expected_out);
		else return -1;
	}
	bool sideBySide() const { return side_by_side; }
	p_check printCheck() const { return print; }
	
	//Mutators for configuring the check
	Check* const setFilename(const std::string &new_filename) 
		{ _filename = new_filename; return this; }
	Check* const setDescription(const std::string &new_desc) 
		{ _description = new_desc; return this; }
	Check* const setExpected(const std::string &new_exp) 
		{ _expected = new_exp; return this; }
	Check* const setCompare(int (*cmp)(std::string, std::string)) 
		{ cmp_output = cmp; return this; }
	//TODO: Should this be redesigned? Is this too unwieldy?
	Check* const setSideBySide(bool new_sbys) { side_by_side = new_sbys; return this; }
	Check* const setPrintCheck(p_check new_print) { print = new_print; return this; }
private:
	std::string _filename;
	std::string _description;
	std::string _expected;
	int (*cmp_output)(std::string, std::string);
	bool side_by_side;
	p_check print;
};
/*TestCase is used to define individual test cases for homeworks. These are to
be populated using the Mutators prior to use and will be executed by the 
validator and graded by the grader.*/
class TestCase{
public:
	//Accessors
	std::string title() const { return _title; }
	std::string details() const { return _details; }
	std::string command() const { return _command; }
	int points() const { return _points; }
	bool hidden() const { return _hidden; }
	
	//TODO: Is this neccessary? Is this expensive?
	std::vector<Check> checks() const { return _checks; }
	
	//Mutators for configuring the test case
	TestCase* const setTitle(const std::string &new_title) 
		{ _title = new_title; return this; }
	TestCase* const setDetails(const std::string &new_details) 
		{ _details = new_details; return this; }
	TestCase* const setCommand(const std::string &new_command) 
		{ _command = new_command; return this; }
	TestCase* const setPoints(int new_points)
		{ _points = new_points; return this; }
	TestCase* const addCheck(const Check &new_check)
		{ _checks.push_back(new_check); return this; }
	TestCase* const setHidden(const bool new_hidden)
		{ _hidden = new_hidden; }
private:
	std::string _title;
	std::string _details;
	std::string _command;
	int _points;
	bool _hidden;
	std::vector<Check> _checks;
};
