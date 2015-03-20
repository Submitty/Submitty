/* FILENAME: tokens.h
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   	Chris Berger
 *   	Jesse Freitas
 *   	Severin Ibarluzea
 *   	Kiana McNellis
 *   	Kienan Knight-Boehm
 *   	Sam Seng
 *   	Robert Berman
 *   	Victor Zhu
 *   	Melissa Lindquist
 *   	Beverly Sihsobhon
 *   	Stanley Cheung
 *   	Tyler Shepard
 *   	Seema Bhaskar
 *   	Andrea Wong
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
