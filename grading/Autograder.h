/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license
*/
#ifndef AUTOGRADER_H_
#define AUTOGRADER_H_

#include "GradingRubric.h"

#include <string>
#include <fstream>
#include <cstdlib>
#include <iomanip>
#include <sstream>
#include <iostream>

void gradefile_print(std::ofstream& gradefile,
		std::ofstream& hidden_gradefile, int UNKNOWN_NUMBER,
		const std::string& line) {
	// TODO ignoring UNKNOWN_NUMBER until I know what it does,
	// may have something to do with the hidden gradefile but
	// for now I'm going to write to both

	gradefile << line;
	hidden_gradefile << line;

}

void prepareGradefile(const GradingRubric& perfect,
		const GradingRubric& student,
		const std::string& main_directory, const std::string& user_id,
		int submission_number) {

	std::string gradefile_location = main_directory + "/.submit.grade";
	std::string hidden_gradefile_location = main_directory
			+ "/.submit.grade_hidden";

	// Open gradefiles
	std::ofstream gradefile(gradefile_location.c_str());
	std::ofstream hidden_gradefile(hidden_gradefile_location.c_str());

	if (!gradefile){
		std::cerr << "ERROR: Could not open gradefile!" << std::endl;
		exit(0);
	}

	if (!hidden_gradefile){
		std::cerr << "ERROR: Could not open hidden gradefile!" << std::endl;
		exit(0);
	}

	// Printing

	// Grade for line

	std::stringstream text;

	text << "Grade for: " << user_id << "\n";
	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Submission # line

	// reset string stream
	text.str("");text.clear();

	text << " submission #:" << submission_number << "\n";
	gradefile_print(gradefile, hidden_gradefile, 0, text.str());


	// Excessive Submissions line

	text.str("");text.clear(); // reset string stream

	if (student.getSubmissionPenalty() < 0){
		text << std::left << std::setw(50) << "  Penalty for excessive submissions:"
				<< student.getSubmissionPenalty() << " points\n";
		gradefile_print(gradefile, hidden_gradefile, 0, text.str());
	}

	// README line

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "  Points for README.txt:"
			<< student.getNonHiddenReadme() << " / "
			<< perfect.getNonHiddenReadme() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Compilation line

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "  Points for Compilation:"
			<< student.getNonHiddenCompilation() << " / "
			<< perfect.getNonHiddenCompilation() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Hidden Compilation line

	if (perfect.getHiddenCompilation() > 0){

		text.str("");text.clear(); // reset string stream

		text << std::left << std::setw(50) <<  "  Points for Compilation (hidden):"
				<< student.getHiddenCompilation() << " / "
				<< perfect.getHiddenCompilation() << "\n";

		gradefile_print(gradefile, hidden_gradefile, 1, text.str());
	}

	// Cycle through test cases and output them to the gradefiles
	int total_test_cases = student.NumTestCases();
	for (int i = 0; i < total_test_cases; i++){
		bool hidden;
		std::string full_message, hidden_message;

		student.GetTestCase(i, hidden, full_message, hidden_message);

		if (!hidden){
			gradefile << full_message;
			hidden_gradefile << full_message;
		}else{
			gradefile << hidden_message;
			hidden_gradefile << full_message;
		}
	}

	// Automatic Extra Credit line

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Automatic Extra Credit (w/o hidden):"
			<< "+" << student.getNonHiddenExtraCredit() << " points\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Automatic grading total (w/o hidden)

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Automatic Grading Total (w/o hidden):"
					<< student.getNonHiddenTotal() << " / "
					<< perfect.getNonHiddenTotal() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Automatic grading total (w/o hidden)

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Max possible hidden automatic grading points:"
					<< perfect.getHiddenTesting() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Automatic extra credit

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Automatic extra credit:"
					<< "+" << student.getExtraCredit() << " points\n";

	gradefile_print(gradefile, hidden_gradefile, 1, text.str());

	// Automatic grading total

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Automatic Grading Total:"
						<< student.getTotal() << " / "
						<< perfect.getTotal() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 1, text.str());

	// Remaining points to be graded by TA line

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50) <<  "Remaining points to be graded by TA:"
						<< perfect.getTAPoints() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	// Max points for assignment (excluding extra credit)

	text.str("");text.clear(); // reset string stream

	text << std::left << std::setw(50)
		<< "Max points for assignment (excluding extra credit): "
		<< student.getTotalAfterTA() << "\n";

	gradefile_print(gradefile, hidden_gradefile, 0, text.str());

	gradefile.close();
	hidden_gradefile.close();

}

void displayGradefile(const std::ostream& out,
		const std::string& main_directory,
		bool hidden, const std::string color) {

	std::string filename = !hidden?
			main_directory + "/.submit.grade" :
			main_directory + "/.submit.grade_hidden";

	std::ifstream gradefile(filename.c_str());

	if (!gradefile){
		std::cerr << "ERROR: Could not open " << filename << " for display"
				<< std:: endl;
		exit(0);
	}

	std::cout << "<p>&nbsp;<p><hr><p>"
			<< "<FONT COLOR=\"" << color << "\"><b>"
			<< "<pre>\n";
	std::cout << gradefile;

	gradefile.close();

	std::cout << "</pre>\n"
			<< "</FONT></b><p><hr><p>\n";
}

#endif /* AUTOGRADER_H_ */
