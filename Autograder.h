/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license
*/
#ifndef AUTOGRADER_H_
#define AUTOGRADER_H_

#include <string>

// Create gradefile from student's grading rubric
void prepareGradefile(const GradingRubric & perfect,
		const GradingRubric & student,
		const std::string & main_directory,
		const std::string & user_id,
		int submission_number);

// Write the formatted grade file to {out}
void displayGradefile(const std::ostream & out,
		const std::string & main_directory,
		bool hidden, const std::string color);

void gradefile_print(const std::ofstream & gradefile,
		const std::ofstream & hidden_gradefile,
		int UNKNOWN_NUMBER, const std::string & line);

#endif /* AUTOGRADER_H_ */
