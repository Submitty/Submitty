/*
 * GradingRubric.h
 *
 *  Created on: Feb 23, 2014
 *      Author: seve
 */

#ifndef GRADINGRUBRIC_H_
#define GRADINGRUBRIC_H_

#include "../validation/TestCase.h"
#include <vector>

class GradingRubric {
public:

	GradingRubric(const std::vector<TestCase>& test_cases,
			const std::vector<int>& perfect_scores);

	GradingRubric(const std::vector<TestCase>& test_cases);

	// ACCESSORS

	int getTestScore(int test_case) const;
	int getTestScore(const TestCase& test_case) const;

	int getPerfectTestScore(int test_case) const;
	int getPerfectTestScore(const TestCase& test_case) const;

	int getTAScore() const { return _ta_score; };

	const std::vector<TestCase>& getTestCases() const { return _test_cases; };

	// MODIFIERS

	// Set the score of a test case
	void setTestScore(int test_case, int score){
		_scores[test_case] = score;
	}
	void setTestScore(const TestCase& test_case, int score);

	// Set the score of a test case
	void setPerfectTestScore(int test_case, int score){
		_perfect_scores[test_case] = score;
	}
	void setPerfectTestScore(const TestCase& test_case, int score);

	void setSubmissionPenalty( int number_of_submissions, int max_submissions,
	            int max_penalty);

	void setTAScore(int score){ _ta_score = score; };

private:

	void initGradingRubric(const std::vector<TestCase> & test_cases);
	int getTestCaseIndex(const TestCase& test_case) const;

	int _submission_penalty, _submission_count, _ta_score;

	std::vector<TestCase> _test_cases;
	std::vector<int> _perfect_scores;
	std::vector<int> _scores;

};

GradingRubric::GradingRubric(const std::vector<TestCase> & test_cases,
		const std::vector<int>& perfect_scores){
	if (perfect_scores.size() != test_cases.size()){
		std::cerr << "Test cases are a different size than perfect scores!"
				<< std::endl;
		// TODO exit?
	}
	_perfect_scores = perfect_scores;

	initGradingRubric(test_cases);

}

GradingRubric::GradingRubric(const std::vector<TestCase>& test_cases) {
	initGradingRubric(test_cases);
}

void GradingRubric::setTestScore(const TestCase& test_case, int score) {
	_scores[getTestCaseIndex(test_case)] = score;
}

void GradingRubric::initGradingRubric(
		const std::vector<TestCase>& test_cases) {

	_test_cases = test_cases;

	// Initialize perfect scores and scores to 0
	_perfect_scores = std::vector<int>(test_cases.size(), 0);
	_scores =  std::vector<int>(test_cases.size(), 0);
}

void GradingRubric::setPerfectTestScore(const TestCase& test_case,
		int score) {
	_perfect_scores[getTestCaseIndex(test_case)] = score;
}

int GradingRubric::getTestScore(int test_case) const {
	return _scores[test_case];
}

int GradingRubric::getTestScore(const TestCase& test_case) const {
	return _scores[getTestCaseIndex(test_case)];
}

int GradingRubric::getPerfectTestScore(int test_case) const {
	return _perfect_scores[test_case];
}

int GradingRubric::getPerfectTestScore(const TestCase& test_case) const {
	return _perfect_scores[getTestCaseIndex(test_case)];
}

int GradingRubric::getTestCaseIndex(const TestCase& test_case) const {
	for (int i = 0; i < _test_cases.size(); i++){
		if (_test_cases[i] == test_case){
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
