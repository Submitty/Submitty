/* FILENAME: clean.h
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   Chris Berger
 *   Jesse Freitas
 *   Severin Ibarluzea
 *   Kiana McNellis
 *   Kienan Knight-Boehm
 *   Sam Seng
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * The clean.h module is used for formatting raw output from students and
 * converting the format for various other modules. This module is a
 * dependency for a majority of the modules in this library and will be
 * required for creating custom grading modules. See the link below:
 * https://github.com/JFrei86/HWserver/wiki/Cleaning-Text
*/

#ifndef __CLEAN__
#define __CLEAN__

#include <string>
#include <sstream>
#include <vector>

typedef std::vector<std::vector<std::string> > vectorOfWords;
typedef std::vector<std::string> vectorOfLines;


/* METHOD: clean
 * ARGS:   content: the body of text that needs cleaning
 * RETURN: void
 * PURPOSE: Removes all instances of \r\n and replaces with \n
*
*/
void clean(std::string & content);

/* METHOD: stringToWords
 * ARGS:   text: the body of text that needs cleaning
 * RETURN: vectorOfWords: a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 */
vectorOfWords stringToWords(std::string text);

/* METHOD: stringToLines
 * ARGS:   text: the body of text that needs cleaning
 * RETURN: vectorOfLines: a vector of strings where each
 * string is a line of text from the input
 */
vectorOfLines stringToLines(std::string text);

/* METHOD: linesToString
 * ARGS:   text: the body of text that needs cleaning
 * in the form of a vector of strings where each string
 * is a line of text
 * RETURN: string: the string body converted from the
 * vector input
 */
std::string linesToString(vectorOfLines text);

/* METHOD: linesToWords
 * ARGS:   text: the body of text that needs cleaning
 * in the form of a vector of strings where each string
 * is a line of text
 * RETURN: vectorOfWords: a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 */
vectorOfWords linesToWords(vectorOfLines text);

/* METHOD: wordsToString
 * ARGS:   text: the body of text that needs cleaning
 * in the form of a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 * RETURN: string: the body of text in raw form
 */
std::string wordsToString(vectorOfWords text);

/* METHOD: wordsToLines
 * ARGS:   text: a vector of vector of strings
 * where the inner vector is a line in a body of text containing
 * the words delimited by spaces and the outer vector is a list of
 * lines in the body of text
 * RETURN: vectorOfLines: the body of text
 * in the form of a vector of strings where each string
 * is a line of text
 */
vectorOfLines wordsToLines(vectorOfWords text);

#endif //__CLEAN__
