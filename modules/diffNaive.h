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
#include "STRutil.h"

//TODO: Change the return type for these functions.

/*diffNaive does a per character comparison including white space and new lines.
It returns a number between 0 and 100 (inclusive) indicating the number of
characters the student string was off by. The strings are not changed in this
comparison. Runs in linear time with respect to the longer string.*/
int diffNaive(const std::string& _student, const std::string& _instructor){
	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);
	int len = (student.size() < instructor.size()) ? student.length() : instructor.length();
	int extra = std::abs((int)(instructor.size() - student.size()));
	int max = std::max(instructor.length(), student.length());
	int diff = 0;

	for(int i = 0; i < len; i++){
		if(student[i] != instructor[i])
			diff++;
	}
	diff += extra;
	return double(max - diff) / max * 100.0;
}
/*diffNoWhiteSpace does a per character comparison not including white space but
including new lines. It returns a number between 0 and 100 (inclusive) 
indicating the number of characters the student string was off by. The strings 
are not changed in this comparison. Runs in linear time with respect to the 
longer string.*/
int diffNoWhiteSpace(const std::string& _student, const std::string& _instructor){
	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);
	int max = std::max(instructor.length(), student.length());
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
		diff++;
		i++;
	}
	while( j != instructor.size()){
		diff++;
		j++;
	}
	return double(max - diff) / max * 100.0;
}

#endif //__DIFF__
