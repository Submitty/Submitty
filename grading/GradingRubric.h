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

	int getPerfectTestScore(int test_case) const;
	int getPerfectTestScore(const TestCase& test_case) const;

	int getTAScore() const { return _ta_score; };

	int getTotalScore() const;
	int getPerfectScore() const;

	const std::vector<TestCase>& getTestCases() const { return _test_cases; };

	// MODIFIERS

	// Set the score of a test case
	void setTestScore(int test_case, int score){
		_scores[test_case] = score;
	}
	void setTestScore(const TestCase& test_case, int score);

	// Set the submission count and penalty
	void setSubmissionPenalty( int number_of_submissions, int max_submissions,
	            int max_penalty);

	// Set the TA's score
	void setTAScore(int score){ _ta_score = score; };

private:

	void initGradingRubric(const std::vector<TestCase> & test_cases);
	int getTestCaseIndex(const TestCase& test_case) const;

	int _submission_penalty, _submission_count, _ta_score;

	std::vector<TestCase> _test_cases;
	std::vector<int> _scores;

};

GradingRubric::GradingRubric(const std::vector<TestCase>& test_cases) {
	initGradingRubric(test_cases);
}

void GradingRubric::setTestScore(const TestCase& test_case, int score) {
	_scores[getTestCaseIndex(test_case)] = score;
}

void GradingRubric::initGradingRubric(
		const std::vector<TestCase>& test_cases) {

	_test_cases = test_cases;

	// Initialize scores to 0
	_scores =  std::vector<int>(test_cases.size(), 0);
}


int GradingRubric::getTestScore(int test_case) const {
	return _scores[test_case];
}

int GradingRubric::getTestScore(const TestCase& test_case) const {
	return _scores[getTestCaseIndex(test_case)];
}

int GradingRubric::getTotalScore() const {
	return std::accumulate(_scores.begin(), _scores.end(), 0);
}

int GradingRubric::getPerfectScore() const {
	int point_sum = 0;
	for (int i = 0; i < _test_cases.size(); i++){
		point_sum += _test_cases[i].points();
	}
	return point_sum;
}

int GradingRubric::getTestCaseIndex(const TestCase& test_case) const {
	for (int i = 0; i < _test_cases.size(); i++){
		if (_test_cases[i].title() == test_case.title()){
			return i;
		}
	}
	std::cerr << "Could not find test case in grading rubric!" << std::endl;
	// TODO exit?
	return -1;
}

void GradingRubric::setSubmissionPenalty(
        int number_of_submissions,
        int max_submissions,
        int max_penalty) {

	// Number of points to lose (if negative, it has no effect)
	// TODO 10 is the number of assignments to deduct 1 point,
	// should this be configurable?
	int penalty = (number_of_submissions - max_submissions)/10;

    _submission_count = number_of_submissions;
    _submission_penalty =
            -std::max(0, std::min(max_penalty, penalty));
}


#endif /* GRADINGRUBRIC_H_ */
