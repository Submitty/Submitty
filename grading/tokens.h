/* FILENAME: tokens.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

#ifndef __differences__tokens__
#define __differences__tokens__
#include <string>
#include <vector>
#include "testResults.h"

class Tokens: public TestResults{
public:
  Tokens();


    std::vector<int> tokens_found;
    int num_tokens;
    bool partial;
    int tokensfound;
    bool harsh;
    void printJSON(std::ostream & file_out);
  //    float grade();
};

#endif
