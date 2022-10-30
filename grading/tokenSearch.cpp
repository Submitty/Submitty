/* FILENAME: tokenSearch.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * Based on the student output, search for tokens within
 * the output. Use for figuring out the final resutls based on the output.
 */

#include "tokenSearch.h"

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
void buildTable ( int* V, const std::string& keyword ) {
  int j = 0;

  //Table initialization
  V[0] = -1;
  V[1] = 0;
  for ( unsigned int i = 2; i < keyword.size(); i++ ) {
    if ( keyword[i - 1] == keyword[j] ) {
      j++;
      V[i] = j;
    } else if ( j > 0 ) {
      j = V[j];
      i--;
    } else {
      V[i] = 0;
    }
  }
}

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
                      const std::vector<std::string>& token_vec ) {
  Tokens* difference = new Tokens();
  difference->partial = false;
  difference->harsh = true;


  difference->setGrade(0);

  //std::vector< std::string > tokenlist;
  std::vector< std::string > tokenlist = token_vec;
  //tokenlist = splitTokens( tokens );

  difference->num_tokens = tokenlist.size();
  for ( unsigned int i = 0; i < tokenlist.size(); i++ ) {
    difference->tokens_found.push_back(
        RabinKarpSingle( tokenlist[i], student ) );
  }
  return difference;
}

/* METHOD: searchAnyTokens
 * ARGS: student: string of student output, token_vec: vector of strings based
 * off of the student output
 * RETURN: TestResults*
 * PURPOSE: Another way of searching for tokens in the student output
 */
TestResults* searchAnyTokens ( const std::string& student,
                      const std::vector<std::string>& token_vec ) {
  Tokens* difference = new Tokens();
  difference->partial = false;
  difference->harsh = false;
  //std::vector< std::string > tokenlist;
  std::vector< std::string > tokenlist = token_vec;
    //tokenlist = splitTokens( tokens );
  difference->num_tokens = tokenlist.size();
  for ( unsigned int i = 0; i < tokenlist.size(); i++ ) {
    difference->tokens_found.push_back(
        RabinKarpSingle( tokenlist[i], student ) );
  }
  difference->setGrade(0);
  return difference;
}

/* METHOD: searchTokens
 * ARGS: student: string of student output, token_vec: vector of strings based
 * off of the student output
 * RETURN: TestResults*
 * PURPOSE: Another way of searching for tokens in the student output
 */
TestResults* searchTokens ( const std::string& student,
                  const std::vector<std::string>& token_vec ) {
  Tokens* difference = new Tokens();
  difference->partial = true;
  //std::vector< std::string > tokenlist;
  std::vector< std::string > tokenlist = token_vec;
  //tokenlist = splitTokens( tokens );
  difference->num_tokens = tokenlist.size();
  for ( unsigned int i = 0; i < tokenlist.size(); i++ ) {
    difference->tokens_found.push_back(
        RabinKarpSingle( tokenlist[i], student ) );
  }
  difference->setGrade(0);
  return difference;
}

/* METHOD: RabinKarpSingle
 * ARGS: token: string with token to search for, searchstring: string of where to search for token
 * RETURN: int
 * PURPOSE: Looks for a single token in a string using the Rabin-Karp rolling hash
 * method.  Returns starting index if found, -1 if not.
 */
int RabinKarpSingle ( std::string token, std::string searchstring ) {
  long hash = 0;
  long goalhash = 0;
  unsigned int tlen = ( unsigned int ) token.size();
  if ( searchstring.size() < token.size() ) {
    return -1;
  }
  for ( int i = 0; i < tlen; i++ ) // Set up goal hash
  {
    goalhash += token[i];
  }
  for ( int i = 0; i < tlen; i++ ) // Set up first hash
  {
    hash += searchstring[i];
  }
  for ( int i = 0; i <= searchstring.size() - tlen; i++ ) {
    // Check if hashes then strings are equal, and if so return index
    if ( hash == goalhash && searchstring.substr( i, tlen ) == token ) {
      return i;
    }
    hash += searchstring[i + tlen];
    hash -= searchstring[i];
  }
  return -1;
}

/* METHOD: splitTokens
 * ARGS: tokens: string of tokens
 * RETURN: vector of strings
 * PURPOSE: split up the tokens within the string into individual tokens stored in the vector
 */
std::vector< std::string > splitTokens ( const std::string& tokens ) {
  std::vector< std::string > tokenlist;
  std::string tmpstr; // Create empty token variable

  // Start at 1 to avoid first double quote
  for ( int i = 1; i < tokens.size(); i++ ) {
    // If we're at a delimiter...
    if ( tokens[i] == '\"' && tokens[i + 1] == '\n' && tokens[i + 2] == '\"' ) {
      if ( tmpstr != "" ) {
        tokenlist.push_back( tmpstr );
      }
      tmpstr.clear();
      i = i + 2; // Skip to end of said delimiter
    }
    else if ( ( tokens.size() - i == 2 ) && tokens[i] == '\"' && tokens[i + 1] == '\n' ) {
      if ( tmpstr != "" ) {
        tokenlist.push_back( tmpstr );
      }
      tmpstr.clear();
      i = i + 1;
    }
    else if ( ( tokens.size() - i == 1 ) && tokens[i] == '\"' ) {
      if ( tmpstr != "" ) {
        tokenlist.push_back( tmpstr );
      }
      tmpstr.clear();
    }
    else {
      tmpstr += tokens[i];
    }
  }
  if ( tmpstr != "" ) {
    tokenlist.push_back( tmpstr );
  }
  return tokenlist;
}
