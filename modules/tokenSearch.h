/* FILENAME: tokenSearch.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

#ifndef __TOKEN__
#define __TOKEN__

#include <stdlib.h>
#include <string>
#include <algorithm>
#include "modules/STRutil.h"
#include "modules/difference.h"
#include "modules/clean.h"
#include "modules/tokens.h"

int RabinKarpSingle ( std::string token, std::string searchstring );
std::vector< std::string > splitTokens ( const std::string& tokens );

TestResults* searchToken ( const std::string& student,
			   const std::vector<std::string>& tokens );
TestResults* searchTokens ( const std::string& student,
			    const std::vector<std::string>& tokens );
TestResults* searchAnyTokens ( const std::string& student,
			       const std::vector<std::string>& tokens );
TestResults* searchAllTokens ( const std::string& student,
			       const std::vector<std::string>& tokens );

void buildTable ( int* V, const std::string& keyword );

#endif //__TOKEN__
