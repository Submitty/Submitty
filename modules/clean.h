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


void clean(std::string & content);
vectorOfWords stringToWords(std::string text);
vectorOfLines stringToLines(std::string text);
std::string linesToString(vectorOfLines text);
vectorOfWords linesToWords(vectorOfLines text);
std::string wordsToString(vectorOfWords text);
vectorOfLines wordsToLines(vectorOfWords text);

#endif //__CLEAN__
