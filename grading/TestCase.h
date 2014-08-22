/* FILENAME: TestCase.h
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


#ifndef __TESTCASE_H__
#define __TESTCASE_H__

#include <string>
#include <sstream> 
#include "modules/modules.h"

/* cout_cerr_check is an enumerated type used to specify whether to
 check and what to do with cout.txt and cerr.txt */
enum cout_cerr_check {
	DONT_CHECK = 0, WARN_IF_NOT_EMPTY = 1, CHECK = 2
};

/* TestCase is used to define individual test cases for homeworks. These
 will be checked by the validator and graded by the grader. */
class TestCase {

	public:


  static TestCase MakeTestCase( const std::string &title, const std::string &details,
				const std::string &command, const std::string &filename,
				const std::string &description, const std::string &expected,
				const int points, const bool hidden, const bool extracredit,
				const cout_cerr_check coutcheck,
				const cout_cerr_check cerrcheck, 
				TestResults* (*cmp) ( const std::string&, const std::string& ) ) {
    TestCase answer;
    answer._title = title;
    answer._details = details;
    answer._command = command;
    answer._filename = filename;
    answer._description = description;
    answer._expected = expected;
    answer._points = points;
    answer._hidden = hidden;
    answer._extracredit = extracredit;
    answer._coutcheck = coutcheck;
    answer._cerrcheck = cerrcheck;
    answer.cmp_output = cmp;
    
    
    /*				_title( title ), _details( details ), _command( command ), _filename(
						filename ), _description( description ), _expected(
						expected ), _points( points ), _hidden( hidden ), _extracredit(
						extracredit ), _coutcheck( cout_check ), _cerrcheck(
						cerr_check ), cmp_output( cmp )  { */

    answer.test_case_id = next_test_case_id;
    next_test_case_id++;
    return answer;
  }

		// Accessors
		std::string title () const {
		  std::stringstream ss;
		  ss << "Test " << test_case_id << " " << _title;
		  return ss.str();
		  //return _title;
		}
		std::string details () const {
			return _details;
		}
		std::string command () const {
			return _command;
		}
		std::string raw_filename () const {
		  return _filename;
		}
		std::string filename () const {
		  //std::stringstream ss;
		  //ss << "test" << std::setw(2) << std::setfill('0') << test_case_id << "_" << _filename;
		  return prefix()+"_"+_filename; //ss.str();
		}
  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }
		std::string description () const {
			return _description;
		}
		std::string expected () const {
			return _expected;
		}
		int points () const {
			return _points;
		}
		bool hidden () const {
			return _hidden;
		}
		bool extracredit () const {
			return _extracredit;
		}
		cout_cerr_check coutCheck () const {
			return _coutcheck;
		}
		cout_cerr_check cerrCheck () const {
			return _cerrcheck;
		}

		/* Calls the function designated by the function pointer; if the function pointer
		 is NULL, defaults to returning the result of diffLine(). */
		TestResults* compare ( const std::string &student_out,
				const std::string &expected_out ) {
			if ( cmp_output != NULL )
				return cmp_output( student_out, expected_out );
			else
				return diffLine( student_out, expected_out );
		}

  int seconds_to_run() { return 5; }

private:

  // This constructor only used by the static Make functions
  		TestCase () { /* THIS CONSTRUCTOR SHOULD NOT BE USED */ }

		std::string _title;
		std::string _details;
		std::string _command;
		std::string _filename;
		std::string _description;
		std::string _expected;
		int _points;
		bool _hidden;
		bool _extracredit;

  int test_case_id;
  static int next_test_case_id; 
  

		cout_cerr_check _coutcheck;
		cout_cerr_check _cerrcheck;
		TestResults* (*cmp_output) ( const std::string&, const std::string& );
};





#endif
