/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __DIFF__
#define __DIFF__

#include <stdlib.h>
#include <string>
#include <algorithm>
#include "STRUtil.h"

//TODO: Change the return type for these functions.

/*diffNaive does a per character comparison including white space and new lines.
It returns a number between 0 and 100 (inclusive) indicating the number of
characters the student string was off by. The strings are not changed in this
comparison.*/
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
/*diffNoWhiteSpace does a per character comparison not including white space but
including new lines. It returns a number between 0 and 100 (inclusive) 
indicating the number of characters the student string was off by. The strings 
are not changed in this comparison.*/
int diffNoWhiteSpace(const std::string& _student, const std::string& _instuctor){
	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);
	int diff = 0;
	int i = 0;
	int j = 0;
	while( i != student.size() && j != instructor.size() ){
		if(student[i] == ' '){
			i++; continue;
		}
		if(instructor[j] == ' '){
			j++; continue;
		}
		if(student[i] != instructor[j])
			diff++;
		i++; j++;
	} 
	while( i != student.size() ){
		if(student[i] != ' ')
			diff++;
		i++;
	}
	while( j != instructor.size() ){
		if(instructor[j] != ' ')
			diff++;
		j++;
	}
	return double(instructor.length() - diff) / instructor.length() * 100.0;
}

#endif //__DIFF__
