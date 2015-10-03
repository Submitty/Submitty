/* FILENAME: tokens.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

#include "tokens.h"
/*
float Tokens::grade() {
  return my_grade;
  // FIXME
  / *
	for (unsigned int i = 0; i < tokens_found.size(); i++) {
		if (tokens_found[i] != -1)
			tokensfound++;
	}
	if (partial)
		return (float) tokensfound / (float) num_tokens;
	else if (tokensfound == num_tokens || (!harsh && tokensfound != 0)) {
		return 1;
	}
	return 0;
  * /
}
*/

#include "json.hpp"

using json = nlohmann::json;

Tokens::Tokens() :
  TestResults(1,""), num_tokens(0), tokensfound(0), partial(true), harsh(false) {
}

void Tokens::printJSON(std::ostream & file_out){
    std::string partial_str = (partial) ? "true" : "false";

	json j;
	j["tokens"] = num_tokens;

    for(unsigned int i = 0; i < tokens_found.size(); i++){
		j["found"].push_back(tokens_found[i]);
    } 

	j["num_found"] = tokensfound;
	j["partial"] = partial_str;

	file_out << j;
    return;
}
