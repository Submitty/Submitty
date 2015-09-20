/* FILENAME: difference.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION: 
 * Extends the printJSON method to format results into JSON format
 */

#include "difference.h"

#include "json.hpp"

using json = nlohmann::json;

/* METHOD: printJSON
 * ARGS: ostream
 * RETURN: void
 * PURPOSE: print out the data in JSON format for student's result and expected result
 */
void Difference::printJSON(std::ostream & file_out) {
	std::string diff1_name;
	std::string diff2_name;
	//file_out << "{" << std::endl << "\"differences\":[" << std::endl << tab;

	json j;
	j["differences"] = json::array();

	switch (type) {
		case ByLineByChar:
			diff1_name = "line";
			diff2_name = "char";
			break;
		case ByWordByChar:
			diff1_name = "word";
			diff2_name = "char";
			break;
		case ByLineByWord:
			diff1_name = "line";
			diff2_name = "word";
			break;
		default:
			diff1_name = "line";
			diff2_name = "char";
			break;
	}

	for (unsigned int a = 0; a < changes.size(); a++) {
		//if (a > 0) {
		//	file_out << ", ";
		//}
		//file_out << "{" << std::endl;
		//file_out << tab<<tab<<"\"student\":"<<std::endl;
		//file_out << tab<<tab<<"{"<<std::endl;
		//file_out << tab<<tab<<tab<<"\"start\": "
		//<<changes[a].a_start;
		
		json student_json;

		j["student"]["start"] = changes[a].a_start;

		if (changes[a].a_changes.size() > 0) {
			//file_out << "," << std::endl;
			//file_out << tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
			//<<tab<<tab<<tab<<tab;
			
			json student_diff1_name_array = json::array();

			for (unsigned int b = 0; b < changes[a].a_changes.size(); b++) {
				//if (b > 0) {
				//	file_out << ", ";
				//}
				//file_out << "{" << std::endl;
				//file_out << tab<<tab<<tab<<tab<<tab
				//<<"\""+diff1_name+"_number\": "
				//<<changes[a].a_changes[b];
				
				student_diff1_name_array.push_back(json({diff1_name + "_number", changes[a].a_changes[b]}));
				
				//insert code to display word changes here
				if (changes[a].a_characters.size() >= b
						&& changes[a].a_characters.size() > 0) {
					if (changes[a].a_characters[b].size() > 0) {
						//file_out << ", " << std::endl;
						//file_out << tab<<tab<<tab<<tab<<tab
						//<<"\""+diff2_name+"_number\":[ ";
					} else {
						//file_out<<std::endl;
					}
						
					json student_diff2_name_array = json::array();

					for (unsigned int c=0; c< changes[a].a_characters[b].size(); c++) {
                        //if (c>0) {
                        //	file_out<<", ";
                        //}
                        //file_out<<changes[a].a_characters[b][c];
						
						student_diff2_name_array.push_back(changes[a].a_characters[b][c]);
                    }

					student_diff1_name_array.push_back(json({diff2_name + "_number", student_diff2_name_array}));
                    //if (changes[a].a_characters[b].size()>0){
                    //	file_out<<" ]"<<std::endl;
                    //}
                } else {
					//file_out<<std::endl;
				}
				//file_out << tab<<tab<<tab<<tab<<"}";
			}
			//file_out << std::endl << tab<<tab<<tab<<"]"<<std::endl;
			
			student_json["student"][diff1_name] = student_diff1_name_array;
		} else {
			//file_out<<std::endl;
		}

		j["differences"].push_back(student_json);

		//file_out << tab<<tab<<"},"<<std::endl;
		//file_out << tab<<tab<<"\"instructor\":"<<std::endl   // want to replace instructor with expected
		//<<tab<<tab<<"{"<<std::endl;
		//file_out << tab<<tab<<tab<<"\"start\":"
		//<<changes[a].b_start;

		json instructor_json = {
			{"instructor",
				{"start", changes[a].b_start}
			}
		};

		if (changes[a].b_changes.size() > 0) {
			//file_out << "," << std::endl;
			//file_out << tab<<tab<<tab<<"\""+diff1_name+"\": ["<<std::endl
			//<<tab<<tab<<tab<<tab;
			
			json instructor_diff1_name_array = json::array();

			for (unsigned int b = 0; b < changes[a].b_changes.size(); b++) {
				//if (b > 0) {
				//	file_out << ", ";
				//}
				//file_out << "{" << std::endl;
				//file_out << tab<<tab<<tab<<tab<<tab
				//<<"\""+diff1_name+"_number\": " <<changes[a].b_changes[b];

				instructor_diff1_name_array.push_back(json({diff1_name + "_number", changes[a].b_changes[b]}));

				//insert code to display word changes here
				if (changes[a].b_characters.size() >= b
						&& changes[a].b_characters.size() > 0) {
					if (changes[a].b_characters[b].size() > 0) {
						//file_out << ", " << std::endl;
						//file_out << tab<<tab<<tab<<tab<<tab
						//<<"\""+diff2_name+"_number\":[ ";
					} else {
						//file_out<<std::endl;
					}

					json instructor_diff2_name_array = json::array();

					for (unsigned int c=0; c< changes[a].b_characters[b].size(); c++) {
                        //if (c>0) {
                        //	file_out<<", ";
                        //}
                        //file_out<<changes[a].b_characters[b][c];
						
						instructor_diff2_name_array.push_back(changes[a].b_characters[b][c]);
                    }

					instructor_diff1_name_array.push_back(json({diff2_name + "_number", instructor_diff2_name_array}));
                    //if (changes[a].b_characters[b].size()>0){
                    //	file_out<<" ]"<<std::endl;
                    //}
				} else {
					//file_out<<std::endl;
				}
				//file_out << tab<<tab<<tab<<tab<<"}";
			}
			//file_out << std::endl << tab<<tab<<tab<<"]"<<std::endl;
			instructor_json["student"][diff1_name] = instructor_diff1_name_array;
		} else {
			//file_out<<std::endl;
		}

		j["differences"].push_back(instructor_json);
		//file_out << tab<<tab<<"}"<<std::endl;
		//file_out << tab<<"}";
	}
	//file_out << std::endl << "]" << std::endl;
	//file_out << "}" << std::endl;

	file_out << j;
	std::cerr << j << std::endl;

	return;
}
