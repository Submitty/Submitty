#include "../GradingRubric.h"
#include "../Autograder.h"


int main(){

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


}

