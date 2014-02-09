/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license
*/

#include <algorithm>

// Constructor

GradingRubric::GradingRubric() {

	// Initialize all scores to 0

	_nonhidden_readme = 0;
	_nonhidden_compilation = 0;
	_nonhidden_testing = 0;
	_hidden_readme = 0;
	_hidden_compilation = 0;
	_hidden_testing = 0;
	_ta_points = 0;
	_submission_penalty = 0;
	_hidden_extra_credit = 0;
	_nonhidden_extra_credit = 0;


	// TODO initialize these variables
	//	_test_case_hidden;
	//	_test_case_full_messages;
	//	_test_case_hidden_messages;
}

// ACCESSORS

int GradingRubric::getCompilation() const {
	return  _nonhidden_compilation +
			_hidden_compilation;
}

int GradingRubric::getNonHiddenTotal() const {
	return  _submission_penalty +
			_nonhidden_readme +
			_nonhidden_compilation +
			_nonhidden_testing +
			_nonhidden_extra_credit;
}

int GradingRubric::getHiddenTotal() const {
	return  _hidden_readme +
			_hidden_compilation +
			_hidden_testing +
			_hidden_extra_credit;
}

int GradingRubric::getNonHiddenExtraCredit() const {
	return _nonhidden_extra_credit;
}

int GradingRubric::getExtraCredit() const {
	return  _hidden_extra_credit +
			_nonhidden_extra_credit;
}

int GradingRubric::getTotal() const {
	return  getNonHiddenTotal() +
			getHiddenTotal();
}

int GradingRubric::getTotalAfterTA() const {
	return  getTotal() +
			_ta_points;
}

// MODIFIERS

void GradingRubric::setSubmissionPenalty(
		int number_of_submissions,
		int max_submissions,
		int max_penalty) {

		// Number of points to lose (if negative, it has no effect)
		// TODO 10 is the number of assignments to deduct 1 point,
		// should this be configurable?
		int penalty = (number_of_submissions - max_submissions)/10;

	_submission_penalty =
			-std::max(0, std::min(max_penalty, penalty));
}
