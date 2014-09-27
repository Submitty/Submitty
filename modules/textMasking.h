/* FILENAME: textMasking.h
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
 *
*/


#ifndef differences_textMasking_h
#define differences_textMasking_h
#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>
std::vector< std::string > includelines ( const std::string &token,
		const std::vector< std::string >& text, bool allMatch = false );
std::vector< std::string > includelines (
		const std::vector< unsigned int > &lines,
		const std::vector< std::string >&text );
std::vector< std::string > excludelines ( const std::string &token,
		const std::vector< std::string >& text, bool allMatch = false );
std::vector< std::string > excludelines (
		const std::vector< unsigned int > &lines,
		const std::vector< std::string >&text );
std::vector< std::string > linesBetween ( unsigned int begin, unsigned int end,
		const std::vector< std::string >&text );
std::vector< std::string > linesOutside ( unsigned int begin, unsigned int end,
		const std::vector< std::string >&text );

//returns only the lines that contain the token (or all of the tokens
// if allMatch is true (defaults to false))


#endif
