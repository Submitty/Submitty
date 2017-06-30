/* FILENAME: tokenSearch.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * Provides token searching within resulting student output
 */

#ifndef __TOKEN__
#define __TOKEN__

#include <stdlib.h>
#include <string>
#include <algorithm>
#include "STRutil.h"
#include "difference.h"
#include "tokens.h"

#include "TestCase.h"
#include "json.hpp"

/* METHOD: RabinKarpSingle
 * ARGS: token: string with token to search for, searchstring: string of where to search for token
 * RETURN: int
 * PURPOSE: Looks for a single token in a string using the Rabin-Karp rolling hash
 * method.  Returns starting index if found, -1 if not.  
 */
int RabinKarpSingle ( std::string token, std::string searchstring );

/* METHOD: splitTokens
 * ARGS: tokens: string of tokens
 * RETURN: vector of strings
 * PURPOSE: split up the tokens within the string into individual tokens stored in the vector
 */
std::vector< std::string > splitTokens ( const std::string& tokens );

/* METHOD: searchToken
 * ARGS: student: string containing student output, token: vector of strings that
 * is based of off the student output
 * RETURN: TestResults*
 * PURPOSE: Looks for a token specified in the second argument in the
 * student output. The algorithm runs in linear time with respect to the
 * length of the student output and preprocessing for the algorithm is
 * linear with respect to the token. Overall, the algorithm runs in O(N + M)
 * time where N is the length of the student and M is the length of the token.
 */
TestResults* searchToken_doit (const TestCase &tc, const nlohmann::json& j);
//( const std::string& student, const std::vector<std::string>& tokens );

/* METHOD: searchTokens
 * ARGS: student: string of student output, token_vec: vector of strings based
 * off of the student output
 * RETURN: TestResults*
 * PURPOSE: Another way of searching for tokens in the student output
 */
TestResults* searchTokens ( const std::string& student,
                            const std::vector<std::string>& tokens );

/* METHOD: searchAnyTokens
 * ARGS: student: string of student output, token_vec: vector of strings based
 * off of the student output
 * RETURN: TestResults*
 * PURPOSE: Another way of searching for tokens in the student output
 */
TestResults* searchAnyTokens ( const std::string& student,
                               const std::vector<std::string>& tokens );

/* METHOD: searchAllTokens
 * ARGS: student: string of student output, token_vec: vector of strings based
 * off of the student output
 * RETURN: TestResults*
 * PURPOSE: Looks for all the tokens delimited by newline characters in the
 * student output. The algorithm runs in linear time with respect to the
 * length of the student output and preprocessing for the algorithm is
 * linear with respect to the token. Overall, the algorithm runs in O(N + M)
 * time where N is the length of the student and M is the length of the token.
 */
TestResults* searchAllTokens ( const std::string& student,
                               const std::vector<std::string>& tokens );

/* METHOD: buildTable
 * ARGS: buffer: integer the same size as the string keyword, keyword: string
 * that accepts any ASCII character
 * RETURN: void
 * PURPOSE: A helper function that is used to construct a table for the keyword
 * in linear time with respect to the keyword given. This helper function
 * is used in the Knuth–Morris–Pratt token searching algorithm for single
 * tokens in order to eliminate redundant comparisons in the student string.
 * The behavior for the function with a buffer less than the size of the keyword is
 * not predictable and should not be used.
 */
void buildTable ( int* V, const std::string& keyword );

#endif //__TOKEN__
