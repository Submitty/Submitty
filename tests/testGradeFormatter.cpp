#include <iostream>
#include <vector>
#include "../grading/GradingRubric.h"
#include "../grading/GradingFormatter.h"

void create_test_cases(std::vector<TestCase> &);

int main(){


	std::vector<TestCase> tests;
	create_test_cases(tests);

	GradingRubric rubric(tests);

	// Test accessor rubric methods

	std::cout << rubric.getTestScore(1) << std::endl;
	std::cout << rubric.getTestScore(tests[2]) << std::endl;
	std::cout << rubric.getTAScore() << std::endl;
	std::cout << rubric.getTotalScore() << std::endl;
	std::cout << rubric.getPerfectScore() << std::endl;

	// Test modifier rubric methods

	rubric.setTestScore(0, 2);
	rubric.setTestScore(1, 5);
	rubric.setTestScore(2, 8);
	rubric.setTestScore(3, 5);
	rubric.setTestScore(4, 5);

	// Test if values changed

	std::cout << rubric.getTotalScore() << '/' << rubric.getPerfectScore()
			<< std::endl;

}

void create_test_cases(std::vector<TestCase>& tests){
	TestCase t1,t2,t3,t4,t5;

	t1.setTitle("README");
	t1.setPoints(2);
	t1.setHidden(false);

	t2.setTitle("Compilation");
	t2.setPoints(5);
	t2.setHidden(false);

	t3.setTitle("Test 1");
	t3.setPoints(8);
	t3.setDescription("Put jelly on cat");
	t3.setDetails("g++ Jelly.cpp -o output");
	t3.setHidden(false);

	t4.setTitle("Test 2");
	t4.setPoints(5);
	t4.setDescription("Throw cat on floor");
	t4.setDetails("g++ Jelly.cpp Cat.cpp throw.cpp -o throw");
	t4.setHidden(false);

	t5.setTitle("Test 3");
	t5.setPoints(5);
	t5.setDescription("Does cat levitate?");
	t5.setDetails("g++ Jelly.cpp Cat.cpp throw.cpp check_levitate.cpp -o lev");
	t5.setHidden(true);

	tests.push_back(t1);
	tests.push_back(t2);
	tests.push_back(t3);
	tests.push_back(t4);
	tests.push_back(t5);

}
