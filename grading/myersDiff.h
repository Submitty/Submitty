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

#include "TestCase.h"

#include "json.hpp"

template<class T> Difference* ses (const nlohmann::json& j, T* a, T* b, bool secondary = false, bool extraStudentOutputOk =false );
template<class T> metaData< T > sesSnapshots ( T* a, T* b, bool extraStudentOutputOk  );
template<class T> metaData< T > sesSnakes ( metaData< T > & meta_diff, bool extraStudentOutputOk  );
template<class T> Difference* sesChanges ( metaData< T > & meta_diff, bool extraStudentOutputOk);
template<class T> Difference* sesSecondary ( Difference & text_diff, metaData< T > & meta_diff, bool extraStudentOutputOk  );
template<class T> Difference* sesSecondary ( Difference & text_diff, bool extraStudentOutputOk  );
template<class T> Difference* printJSON ( Difference & text_diff, std::ofstream & file_out, int type = 0 );



TestResults* fileExists_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* warnIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* errorIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* warnIfEmpty_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* errorIfEmpty_doit (const TestCase &tc, const nlohmann::json& j);

TestResults* ImageDiff_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* myersDiffbyLinebyWord_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* myersDiffbyLineNoWhite_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* myersDiffbyLine_doit (const TestCase &tc, const nlohmann::json& j);
TestResults* myersDiffbyLinebyChar_doit (const TestCase &tc, const nlohmann::json& j);

TestResults* diffLineSwapOk_doit (const TestCase &tc, const nlohmann::json& j);


#endif
