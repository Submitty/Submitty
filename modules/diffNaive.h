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
#include <sstream>
#include <algorithm>
#include "STRutil.h"
#include "difference.h"

//TODO: Change the return type for these functions.

/*diffNaive does a per character comparison including white space and new lines.
It returns a number between 0 and 100 (inclusive) indicating the number of
characters the student string was off by. The strings are not changed in this
comparison. Runs in linear time with respect to the longer string.*/
Change diffNaive(const std::string& student, const std::string& instructor){
	Change differences;
	differences.a_start = 0;
	differences.b_start = 0;

	int len = (student.size() < instructor.size()) ? student.length() : instructor.length();

	int i;
	for(i = 0; i < len; i++){
		if(student[i] != instructor[i]){
			differences.a_changes.push_back(i);
			differences.b_changes.push_back(i);
		}
	}
	while(i < instructor.length()){
		differences.a_changes.push_back(i);
		i++;
	}
	while(i < student.length()){
		differences.b_changes.push_back(i);
	}
	return differences;
}
/*diffNoWhiteSpace does a per character comparison not including white space but
including new lines. It returns a number between 0 and 100 (inclusive) 
indicating the number of characters the student string was off by. The strings 
are not changed in this comparison. Runs in linear time with respect to the 
longer string.*/
/*Difference diffNoSpace(const std::string& _student, const std::string& _instructor){
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
}*/
/**/
Difference diffLine(const std::string& _student, const std::string& _instructor){
	Difference diffs;
	std::stringstream student;
	student.str(_student);
	std::stringstream instructor;
	instructor.str(_instructor);
	std::string s_line;
	std::string i_line;
	int i = 0;
	while(std::getline(student, s_line) && getline(instructor, i_line)){
		Change changes = diffNaive(s_line, i_line);
		if(changes.a_changes.size() || changes.b_changes.size()){
			diffs.diff_a.push_back(i);
			diffs.diff_b.push_back(i);
			diffs.changes.push_back(changes);
			i++;
		}
	}
	diffs.distance = i;
	return diffs;
}
/**/
/*Difference diffLineNoSpace(const std::string& _student, const std::string& _instructor){

}*/

#endif //__DIFF__
