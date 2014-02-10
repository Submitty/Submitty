/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license
*/

class GradingRubric{
public:

    GradingRubric();

    // ACCESSORS

    int getCompilation() const;
    int getNonHiddenTotal() const;
    int getHiddenTotal() const;
    int getNonHiddenExtraCredit() const
    int getExtraCredit() const;
    int getTotal() const;
    int getTotalAfterTA() const;

    // MODIFIERS

    void setSubmissionPenalty(
            int number_of_submissions,
            int max_submissions,
            int max_penalty);

    void incrREADME();                      // UNIMPLEMENTED
    void incrCompilation();                 // UNIMPLEMENTED
    void incrTesting();                     // UNIMPLEMENTED
    void setTA();                           // UNIMPLEMENTED
    void VerifyTotalAfterTA();              // UNIMPLEMENTED
    void AddTestCaseResult();               // UNIMPLEMENTED
    void NumTestCases();                    // UNIMPLEMENTED
    void GetTestCase();                     // UNIMPLEMENTED


private:

    // HW Points

    int _nonhidden_readme;
    int _nonhidden_compilation;
    int _nonhidden_testing;
    int _hidden_readme;
    int _hidden_compilation;
    int _hidden_testing;
    int _ta_points;
    int _submission_penalty;
    int _hidden_extra_credit;
    int _nonhidden_extra_credit;

//  UNKNOWN_TYPE _test_case_hidden;
//  UNKNOWN_TYPE _test_case_full_messages;
//  UNKNOWN_TYPE _test_case_hidden_messages;

};
