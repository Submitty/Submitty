/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

/*This is a test suite for 'TestCase.h'*/
#include "TestCase.h"
#include <string>
#include <iostream>

//This is a dummy diff for testing the TestCase class.
int diff(std::string actual, std::string expected){
	return 86; //Arbitrary ranking for testing purposes
}
//Simple dummy function for testing
//TODO: This is a simple function to consider using in the grading modules
int warn_if_not_empty(std::string actual, std::string expected){
	return (actual.length() == 0) ? 100 : 0;
}


#if 0
void print(TestCase& a){return a.title() + "\n" + a.details() + "\n" + a.command() 
		+ "\nPoints: " + a.points() + "\n# of Checks: " + a.checks().size() + "\n";}
void toString() const {return filename() + "\n" + description() + "\n" 
		+ expected() + "\n";}
#endif
		
int main(){
	TestCase test1;
	test1.setTitle("Left Justify Example")
		->setDetails("./justify.exe example.txt output.txt 16 flush_left")
		->setCommand("./a.out /projects/submit2/csci1200/testing_input/hw01/example.txt output.txt 16 flush_left 1> cout.txt 2> cerr.txt")
		->setPoints(3);
	
	Check OUTPUT;
	OUTPUT.setFilename("output.txt")
		->setDescription("output.txt")
		->setExpected("/projects/submit2/csci1200/scripts/hw01/example_16_flush_left.txt")
		->setCompare(&diff)
		->setPrintCheck(ALWAYS);
	Check STDOUT;
	STDOUT.setFilename("cout.txt")
		->setDescription("Standard OUTPUT (STDOUT)")
		->setCompare(&warn_if_not_empty)
		->setPrintCheck(WARNING_OR_FAILURE);
	Check STDERR;
	STDERR.setFilename("cerr.txt")
		->setDescription("Standard ERROR (STDERR)")
		->setCompare(&warn_if_not_empty)
		->setPrintCheck(WARNING_OR_FAILURE);
	test1.addCheck(OUTPUT)
		->addCheck(STDOUT)
		->addCheck(STDERR);
	
	//std::cout << test1.toString() << OUTPUT.toString() << STDOUT.toString() << STDERR.toString() << std::endl;
	return 0;
}
