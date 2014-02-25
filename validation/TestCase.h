/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __TESTCASE_H__
#define __TESTCASE_H__


/*p_check is an enumerated tyoe used in Check to declare if the 
output from a Check is shown.*/
enum p_check { NEVER = 0, WARNING_OR_FAILURE = 1, ALWAYS = 2 };


/*TestCase is used to define individual test cases for homeworks. These are to
be populated using the Mutators prior to use and will be executed by the 
validator and graded by the grader.*/
class TestCase {

public:

	//Constructor
	TestCase( const std::string &title, const std::string &details,
			  const std::string &command, const std::string &filename,
			  const std::string &description, const std::string &expected,
			  const int points, const bool hidden,
			  int (*cmp)(std::string, std::string) )
			: _title(title), _details(details), _command(command),
			  _filename(filename), _description(description),
			  _expected(expected), _points(points), _hidden(hidden),
			  cmp_output(cmp) {}
	
	//Accessors
	std::string title() const { return _title; }
	std::string details() const { return _details; }
	std::string command() const { return _command; }
	std::string filename() const { return _filename; }
	std::string description() const { return _description; }
	std::string expected() const { return _expected; }
	int points() const { return _points; }
	bool hidden() const { return _hidden; }
	
	/*Calls the function designated by the function pointer and returns the
	result if successful or -1 otherwise. The arguments of the compare*/
	int compare(std::string &student_out, std::string &expected_out){
		if(cmp_output != 0) return cmp_output(student_out, expected_out);
		else return -1;
	}
	
	//Mutators for configuring the test case
	TestCase* const setTitle(const std::string &new_title) 
		{ _title = new_title; return this; }
	TestCase* const setDetails(const std::string &new_details) 
		{ _details = new_details; return this; }
	TestCase* const setCommand(const std::string &new_command) 
		{ _command = new_command; return this; }
	TestCase* const setPoints(int new_points)
		{ _points = new_points; return this; }
	TestCase* const setHidden(const bool new_hidden)
		{ _hidden = new_hidden; return this; }
	TestCase* const setFilename(const std::string &new_filename) 
		{ _filename = new_filename; return this; }
	TestCase* const setDescription(const std::string &new_desc) 
		{ _description = new_desc; return this; }
	TestCase* const setExpected(const std::string &new_exp) 
		{ _expected = new_exp; return this; }
	TestCase* const setCompare(int (*cmp)(std::string, std::string)) 
		{ cmp_output = cmp; return this; }
		
private:
	std::string _title;
	std::string _details;
	std::string _command;
	std::string _filename;
	std::string _description;
	std::string _expected;
	int _points;
	bool _hidden;
	int (*cmp_output)(std::string, std::string);
};

#endif

