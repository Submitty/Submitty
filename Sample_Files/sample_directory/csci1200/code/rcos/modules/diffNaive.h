/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __DIFFNAIVE__
#define __DIFFNAIVE__

#include <stdlib.h>
#include <string>
#include <sstream>
#include <algorithm>
#include "STRutil.h"
#include "difference.h"

/*diffNaive does a per character comparison including white space and new lines.
It returns a number between 0 and 100 (inclusive) indicating the number of
characters the student string was off by. The strings are not changed in this
comparison. Runs in linear time with respect to the longer string.*/
Change diffNaive(const std::string& student, const std::string& instructor){
	Change differences;
	differences.a_start = 0;
	differences.b_start = 0;

	unsigned int len = (unsigned int)(student.size() < instructor.size()) ? (unsigned int)student.length() : (unsigned int)instructor.length();

	unsigned int i = 0;
	for(i = 0; i < len; i++){
		if(student[i] != instructor[i]){
			differences.a_changes.push_back(i);
			differences.b_changes.push_back(i);
		}
	}
	while(i < instructor.length()){
		differences.b_changes.push_back(i);
		i++;
	}
	while(i < student.length()){
		differences.a_changes.push_back(i);
		i++;
	}
	return differences;
}

/*diffNoSpace does a per character comparison not including white space but
including new lines. It returns a Change object that indicates the indicies of
characters the student string was off by. The strings are not changed in this 
comparison. Runs in linear time with respect to the longer string. */
Change diffNoSpace(const std::string& _student, const std::string& _instructor){
	Change differences;
	differences.a_start = 0;
	differences.b_start = 0;

	std::string student = string_trim_right(_student);
	std::string instructor = string_trim_right(_instructor);

	unsigned int i = 0;
	unsigned int j = 0;
	while( i != student.size() && j != instructor.size() ){
		if(student[i] == ' '){
			i++; continue;
		}
		if(instructor[j] == ' '){
			j++; continue;
		}
		if(student[i] != instructor[j]){
			differences.a_changes.push_back(i);
			differences.b_changes.push_back(j);
		}
		i++; j++;
	} 
	while( i != student.size() ){
		if(student[i] != ' ')
			differences.a_changes.push_back(i);
		i++;
	}
	while( j != instructor.size()){
		if(instructor[j] != ' ')
			differences.b_changes.push_back(j);
		j++;
	}
	return differences;
}

/*diffLine does a per character comparison including white space but
including new lines. Comparison is done per line and returns a Difference object
that indicates the indicies of characters the student string was off by. The 
strings are not changed in this comparison. Runs in linear time with respect to
the longer string.*/
TestResults* diffLine(const std::string& _student, const std::string& _instructor){
	Difference* diffs = new Difference();
	diffs->type = 1;

	diffs->output_length_a = 0;
	diffs->output_length_b = 0;
	Change file;
	file.a_start = file.b_start = 0;
	std::stringstream student;
	student.str(_student);
	std::stringstream instructor;
	instructor.str(_instructor);
	std::string s_line;
	std::string i_line;
	unsigned int i = 0;
	bool i_eof = false;
	bool s_eof = false;
	while(!i_eof || !s_eof){
		std::getline(student, s_line);
		std::getline(instructor, i_line);
		if(!i_eof)
			diffs->output_length_b++;
		if(!s_eof)
			diffs->output_length_a++;
		if(!student){
			s_eof = true;
			s_line = "";
		}
		if(!instructor){
			i_eof = true;
			i_line = "";
		}
		Change changes = diffNaive(s_line, i_line);
		if(changes.a_changes.size() || changes.b_changes.size()){
			if(!s_eof)
				file.a_changes.push_back(i);
			if(!i_eof)
				file.b_changes.push_back(i);
			file.a_characters.push_back(changes.a_changes);
			file.b_characters.push_back(changes.b_changes);
		}
		i++;
	}
	diffs->distance = (int)std::max(file.a_changes.size(), file.b_changes.size());
	diffs->changes.push_back(file);
	return diffs;
}

/*diffLineNoSpace does a per character comparison not including white space but
including new lines. Comparison is done per line and returns a Difference object
that indicates the indicies of characters the student string was off by. The 
strings are not changed in this comparison. Runs in linear time with respect to
the longer string.*/
TestResults* diffLineNoSpace(const std::string& _student, const std::string& _instructor){
	Difference* diffs = new Difference();
	diffs->type = 1;

	Change file;
	file.a_start = file.b_start = 0;
	std::stringstream student;
	student.str(_student);
	std::stringstream instructor;
	instructor.str(_instructor);
	std::string s_line;
	std::string i_line;
	int i = 0;
	bool i_eof = false;
	bool s_eof = false;
	while(!i_eof || !s_eof){
		std::getline(student, s_line);
		std::getline(instructor, i_line);
		if(!student){
			s_eof = true;
			s_line = "";
		}
		if(!instructor){
			i_eof = true;
			i_line = "";
		}
		Change changes = diffNoSpace(s_line, i_line);
		if(changes.a_changes.size() || changes.b_changes.size()){
			if(!s_eof)
				file.a_changes.push_back(i);
			if(!i_eof)
				file.b_changes.push_back(i);
			file.a_characters.push_back(changes.a_changes);
			file.b_characters.push_back(changes.b_changes);
		}
		i++;
	}
	diffs->distance = (int)std::max(file.a_changes.size(), file.b_changes.size());
	diffs->changes.push_back(file);
	return diffs;
}

#endif //__DIFFNAIVE__
