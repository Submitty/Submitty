/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/
#include <stdlib.h>
#include <string>

int diffNaive(const std::string& _student, const std::string& _instructor){
	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);
	int len = (student.size() < instructor.size()) ? student.length() : instructor.length();
	int extra = std::abs(instructor.size() - student.size()); 
	int diff = 0;

	for(int i = 0; i < len; i++){
		if(student[i] != instructor[i])
			diff++;
	}
	diff += extra;
	return double(instructor.length() - diff) / instructor.length() * 100.0;
}
int diffNoWhiteSpace(std::string student, std::string instuctor){
	return 0;
}
