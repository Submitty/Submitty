/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef GRADINGRUBRIC_H_
#define GRADINGRUBRIC_H_

#include "../validation/TestCase.h"
#include <vector>
#include <numeric>

class GradingRubric {
public:

	GradingRubric(const std::vector<TestCase>& test_cases);

	// ACCESSORS

	int getTestScore(int test_case) const;
	int getTestScore(const TestCase& test_case) const;

	int getTAScore() const { return _ta_score; };

	int getTotalScore() const;
	int getPerfectScore() const;

	int getSubmissionCount() const;
	int getSubmissionPenalty() const;

	int getTotalExtraCredit() const;

	unsigned int getNumTestCases() const { return _test_cases.size(); };

	const TestCase& getTestCase(unsigned int index) const {
		return _test_cases[index];
	}

	const std::vector<TestCase>& getTestCases() const { return _test_cases; };

	// MODIFIERS

	// Set the score of a test case
	void setTestScore(int test_case, int score){
		_scores[test_case] = score;
	}
	void setTestScore(const TestCase& test_case, int score);

	// Set the submission count and penalty
	void setSubmissionPenalty( int number_of_submissions, int max_submissions,
	            int penalize_every, int max_penalty);

	// Set the TA's score
	void setTAScore(int score){ _ta_score = score; };

private:

	void initGradingRubric(const std::vector<TestCase> & test_cases);
	int getTestCaseIndex(const TestCase& test_case) const;

	int _submission_penalty, _submission_count, _ta_score;

	std::vector<TestCase> _test_cases;
	std::vector<int> _scores;

};

// GradingRubric Constructor

GradingRubric::GradingRubric(const std::vector<TestCase>& test_cases) {
	initGradingRubric(test_cases);
}

// Set test score by test case

void GradingRubric::setTestScore(const TestCase& test_case, int score) {
	_scores[getTestCaseIndex(test_case)] = score;
}

// Initialize variables in new instance of Grading Rubric

void GradingRubric::initGradingRubric(
		const std::vector<TestCase>& test_cases) {

	_test_cases = test_cases;
	_ta_score = 0;
	_submission_count = 0;
	_submission_penalty = 0;

	// Initialize scores to 0
	_scores =  std::vector<int>(test_cases.size(), 0);
}

// Return user score for test

int GradingRubric::getTestScore(int test_case) const {
	return _scores[test_case];
}

// Return user score for test

int GradingRubric::getTestScore(const TestCase& test_case) const {
	return _scores[getTestCaseIndex(test_case)];
}

// Return student's total score

int GradingRubric::getTotalScore(bool withHidden = false) const {
	return std::accumulate(_scores.begin(), _scores.end(), 0)
		- _submission_penalty;
}

// Return best possible score for student

int GradingRubric::getPerfectScore() const {
	int point_sum = 0;
	for (unsigned int i = 0; i < _test_cases.size(); i++){
		// TODO make sure test case is not extra credit
		if (true){
			point_sum += _test_cases[i].points();
		}
	}
	return point_sum;
}

// Return the total number of submissions this user has submitted

int GradingRubric::getSubmissionCount() const {
	return _submission_count;
}

// Get the total points this student has lost due to the submission
// penalty

int GradingRubric::getSubmissionPenalty() const {
	return _submission_penalty;
}

// Get total points from non hidden extra credit

int GradingRubric::getTotalExtraCredit() const {
	unsigned int points = 0;
	for (unsigned int i = 0;i < _test_cases.size(); i++){
		if (false){ // TODO check if test_case is extra credit
			points += _test_cases[i].points();
		}
	}
}

// Returns index of test case in the _test_cases variable

int GradingRubric::getTestCaseIndex(const TestCase& test_case) const {
	for (unsigned int i = 0; i < _test_cases.size(); i++){
		if (_test_cases[i].title() == test_case.title()){
			return i;
		}
	}
	std::cerr << "Could not find test case in grading rubric!" << std::endl;
	// TODO exit?
	return -1;
}

// Set the submission penalty and total submissions based on the
// # of submissions, the max submission number and the max
// penalty

void GradingRubric::setSubmissionPenalty(
        int number_of_submissions,
        int max_submissions,
        int penalize_every,
        int max_penalty) {

	// Number of points to lose (if negative, it has no effect)
	// TODO 10 is the number of assignments to deduct 1 point,
	// should this be configurable?
	int penalty = (number_of_submissions - max_submissions
			+ penalize_every)/penalize_every;

    _submission_count = number_of_submissions;
    _submission_penalty =
            std::min(max_penalty, penalty);
}


#endif /* GRADINGRUBRIC_H_ */
