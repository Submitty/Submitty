/* FILENAME: modules.h
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
 * This file is used to make including grading modules easier.
*/

#ifndef __MODULES__
#define __MODULES__
//#include "modules/diffNaive.h"  /* this is now a .cpp file */

#include "modules/diffNaive.cpp"  /* this is now a .cpp file */

#include "modules/STRutil.h"
#include "modules/tokenSearch.h"

#include "modules/tokenSearch.cpp"  /* should not #include a .cpp file */

#include "modules/myersDiff.h"
#include "modules/textMasking.h"
#include "modules/clean.h"
#include "modules/difference.h"

#endif //__MODULES__
