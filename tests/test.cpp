/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/
#include <string>
#include <iostream>
#include "../validation/TestCase.h"
#include "../grading/GradingRubric.h"
#include "../grading/Autograder.h"

//This is a dummy diff for testing the TestCase class.
int diff(std::string actual, std::string expected){
	return 86; //Arbitrary ranking for testing purposes
}
//Simple dummy function for testing
//TODO: This is a simple function to consider using in the grading modules
int warn_if_not_empty(std::string actual, std::string expected){
	return (actual.length() == 0) ? 100 : 0;
}

int testGradingRubric(){
	// Create a test grading rubric

	GradingRubric student,perfect;

	student.incrCompilation(50,false);
	student.incrCompilation(20,true);
	student.incrREADME(2,true);
	student.incrTesting(30,false,false);
	student.setSubmissionPenalty(35, 20, 5);

	perfect.incrCompilation(60,false);
	perfect.incrCompilation(20,true);
	perfect.incrREADME(2,true);
	perfect.incrTesting(30,false,false);

	prepareGradefile(perfect,student,"./","example",35);

	// Look for the .submit.grade file and .submit.grade_hidden
	// file to check output
	return 0;
}

int testTestCase(){
	TestCase test1;
	test1.setTitle("Left Justify Example")
		->setDetails("./justify.exe example.txt output.txt 16 flush_left")
		->setCommand("./a.out /projects/submit2/csci1200/testing_input/hw01/example.txt output.txt 16 flush_left 1> cout.txt 2> cerr.txt")
		->setPoints(3);
	std::cout << test1.title() << "\n" << test1.details() << "\n" << test1.command() << std::endl;
	Check OUTPUT;
	OUTPUT.setFilename("output.txt")
		->setDescription("output.txt")
		->setExpected("/projects/submit2/csci1200/scripts/hw01/example_16_flush_left.txt")
		->setCompare(&diff)
		->setPrintCheck(ALWAYS);
	std::cout << "  " << OUTPUT.filename() << "\n  " << OUTPUT.expected() << std::endl;
	Check STDOUT;
	STDOUT.setFilename("cout.txt")
		->setDescription("Standard OUTPUT (STDOUT)")
		->setCompare(&warn_if_not_empty)
		->setPrintCheck(WARNING_OR_FAILURE);
	Check STDERR;
	std::cout << "  " << STDOUT.filename() << "\n  " << STDOUT.description() << std::endl;
	STDERR.setFilename("cerr.txt")
		->setDescription("Standard ERROR (STDERR)")
		->setCompare(&warn_if_not_empty)
		->setPrintCheck(WARNING_OR_FAILURE);
	std::cout << "  " << STDERR.filename() << "\n  " << STDERR.description() << std::endl;
	test1.addCheck(OUTPUT)
		->addCheck(STDOUT)
		->addCheck(STDERR);
	return 0;
}

int main(){
	testTestCase();
	//testGradingRubric();
}
