/* FILENAME: myersDiff.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

/*
   The algorithm for shortest edit script was in derived from
   Eugene W. Myers's paper, "An O(ND) Difference Algorithm and Its Variations",
   avalible here: http://www.xmailserver.org/diff2.pdf

   It was published in the journal "Algorithmica" in November 1986.

   Code similar to:
   		http://simplygenius.net/Article/DiffTutorial1
 		FIXME: if this was the source, should be formally credited
        (or are both just coming from the paper's pseudocode)
 */

#ifndef differences_myersDiff_h
#define differences_myersDiff_h

#include <iostream>
#include <string>
#include <iomanip>
#include <vector>
#include <cstdlib>
#include <fstream>
#include <cmath>
#include "difference.h"
#include "metaData.h"
#include "clean.h"

//template<class T> Difference* ses ( T& a, T& b, bool secondary = false, bool extraStudentOutputOk =false );
template<class T> Difference* ses ( T* a, T* b, bool secondary = false, bool extraStudentOutputOk =false );
//template<class T> metaData< T > sesSnapshots ( T& a, T& b, bool extraStudentOutputOk  );
template<class T> metaData< T > sesSnapshots ( T* a, T* b, bool extraStudentOutputOk  );
template<class T> metaData< T > sesSnakes ( metaData< T > & meta_diff, bool extraStudentOutputOk  );
template<class T> Difference* sesChanges ( metaData< T > & meta_diff, bool extraStudentOutputOk);
template<class T> Difference* sesSecondary ( Difference & text_diff, metaData< T > & meta_diff, bool extraStudentOutputOk  );
template<class T> Difference* sesSecondary ( Difference & text_diff, bool extraStudentOutputOk  );
template<class T> Difference* printJSON ( Difference & text_diff, std::ofstream & file_out, int type = 0 );



TestResults* warnIfNotEmpty (const std::string & student_file, const std::string & expected_file);
TestResults* errorIfNotEmpty ( const std::string & student_file, const std::string & expected_file);
TestResults* errorIfEmpty ( const std::string & student_file, const std::string & expected_file);

TestResults* myersDiffbyLinebyWord ( const std::string & student_file, const std::string & expected_file);
TestResults* myersDiffbyLineNoWhite ( const std::string & student_file, const std::string & expected_file);
TestResults* myersDiffbyLine ( const std::string & student_file, const std::string & expected_file);
TestResults* myersDiffbyLinebyChar ( const std::string & student_file, const std::string & expected_file);
TestResults* myersDiffbyLinebyCharExtraStudentOutputOk ( const std::string & student_file, const std::string & expected_file);

#endif
