/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __PARTIAL__
#define __PARTIAL__

#include <stdlib.h>
#include <string>
#include <algorithm>
#include "STRutil.h"

int diffBegin(const std::string& _student, const std::string& _instructor){
	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);
	int len = (int)(student.size() < (int)instructor.size()) ? (int)student.length() : (int)instructor.length();
	int extra = std::abs((int)(instructor.size() - (int)student.size()));
	int max = std::max((int)instructor.length(), (int)student.length());
	int diff = 0;

	for(int i = 0; i < len; i++){
		if(student[i] != instructor[i])
			diff++;
	}
	diff += extra;
	return double(max - diff) / max * 100.0;
}
int diffEnd(const std::string& _student, const std::string& _instructor){
	return 100;
}
int diffBeginNoWhiteSpace(const std::string& _student, const std::string& _instructor){
	return 100;
}
int diffEndNoWhiteSpace(const std::string& _student, const std::string& _instructor){
	return 100;
}
#endif //__PARTIAL__
