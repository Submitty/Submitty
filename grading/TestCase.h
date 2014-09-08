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
#include <cassert>
#include "modules/modules.h"

/* cout_cerr_check is an enumerated type used to specify whether to
 check and what to do with cout.txt and cerr.txt */
/*
enum cout_cerr_check {
	DONT_CHECK = 0, WARN_IF_NOT_EMPTY = 1, CHECK = 2
};
*/


class TestCasePoints {
public:
  TestCasePoints(int p=0, bool h=false, bool ec=false) : points(p),hidden(h),extra_credit(ec) {}
  int points;
  bool hidden;
  bool extra_credit;
};



class TestCaseComparison {
public:
  TestCaseComparison(TestResults* (*cmp) ( const std::string&, const std::string& ) = NULL,
		     const std::string file = "",
		     const std::string desc = "",
		     const std::string expect = "")
    : cmp_output(cmp), filename(file),description(desc),expected_file(expect) {}
  TestResults* (*cmp_output) ( const std::string&, const std::string& );  
  std::string filename;
  std::string description;
  std::string expected_file;
};


/* TestCase is used to define individual test cases for homeworks. These
 will be checked by the validator and graded by the grader. */
class TestCase {


private:
  // This constructor only used by the static Make functions
  TestCase () { 
    test_case_id = next_test_case_id;
    next_test_case_id++;

    //    _points = 0;
    //_hidden = false;
    //_extracredit = false;
    //_coutcheck = DONT_CHECK;
    //_cerrcheck = DONT_CHECK;
    //cmp_output = NULL;

    FILE_EXISTS = false;
    COMPILATION = false;
  }

public:


  static TestCase MakeFileExists ( const std::string &title,
				   const std::string &filename,
				   const TestCasePoints &tcp) {

    TestCase answer;
    answer._title = title;
    //answer._command = "FILE_EXISTS";
    answer._details = filename;
    answer._test_case_points = tcp;
    //_points = points;
    //answer._hidden = hidden;
    //answer._extracredit = extracredit;

    answer.FILE_EXISTS = true;

    return answer;
  }
  
  static TestCase MakeCompilation( const std::string &title,
				   const std::string &filename,
				   const TestCasePoints &tcp) {

    TestCase answer;
    answer._title = title;
    //answer._command = "FILE_EXISTS";
    answer._details = filename;
    answer._test_case_points = tcp;
    //answer._points = points;
    //answer._hidden = hidden;
    //answer._extracredit = extracredit;

    answer.COMPILATION = true;

    return answer;
  }

  static TestCase MakeTestCase   ( const std::string &title, const std::string &details,
				   const std::string &command,
				   const TestCasePoints &tcp,
				   const TestCaseComparison &tcc0,
				   const TestCaseComparison &tcc1=TestCaseComparison(),
				   const TestCaseComparison &tcc2=TestCaseComparison()) {
    /*,
				   
				   const std::string &filename,
				   const std::string &description, const std::string &expected,

				   const cout_cerr_check coutcheck,
				   const cout_cerr_check cerrcheck, 
				   TestResults* (*cmp) ( const std::string&, const std::string& ) ) {
    */

    TestCase answer;
    answer._title = title;
    answer._details = details;
    answer._command = command;
    answer._test_case_points = tcp;
    answer.test_case_comparison[0] = tcc0;
    answer.test_case_comparison[1] = tcc1;
    answer.test_case_comparison[2] = tcc2;
    //answer._filename = filename;
    //answer._description = description;
    //answer._expected = expected;
    
    //    answer._points = points;
    //answer._hidden = hidden;
    //answer._extracredit = extracredit;
    //    answer._coutcheck = coutcheck;
    //answer._cerrcheck = cerrcheck;
    //answer.cmp_output = cmp;
    return answer;
  }



		// Accessors
		std::string title() const {
		  std::stringstream ss;
		  ss << "Test " << test_case_id << " " << _title;
		  return ss.str();
		  //return _title;
		}

		std::string just_title() const {
		  return _title;
		}

		std::string details () const {
			return _details;
		}
		std::string command () const {
			return _command;
		}
		std::string filename (int i) const {
		  return prefix()+"_"+raw_filename(i);
		}

  int numFileComparisons() const {
    int answer = 0;
    if (test_case_comparison[0].cmp_output == NULL) return answer;
    answer++;
    if (test_case_comparison[1].cmp_output == NULL) return answer;
    answer++;
    if (test_case_comparison[2].cmp_output == NULL) return answer;
    answer++;
    return answer;
  }


  std::string raw_filename (int i) const {
    assert (i >= 0 && i < numFileComparisons());
    return test_case_comparison[i].filename;
  }

  std::string prefix() const {
    std::stringstream ss;
    ss << "test" << std::setw(2) << std::setfill('0') << test_case_id;
    return ss.str();
  }

  std::string description (int i) const {
    assert (i >= 0 && i < numFileComparisons());
    return test_case_comparison[i].description;
  }	
  std::string expected (int i) const {
    //std::cout << "EXPECTED " << i << numFileComparisons() << std::endl;
    assert (i >= 0 && i < numFileComparisons());
    return test_case_comparison[i].expected_file;
  }		
  int points () const {
    return _test_case_points.points;
  }
  bool hidden () const {
    return _test_case_points.hidden;
  }
  bool extracredit () const {
    return _test_case_points.extra_credit;
  }
  
  /* Calls the function designated by the function pointer; if the function pointer
     is NULL, defaults to returning the result of diffLine(). */
  TestResults* compare (int j) {
    assert (j >= 0 && j < numFileComparisons());
    //const std::string &student_out,
    //				const std::string &expected_out ) {
    //if ( test_case_comparison[i].cmp_output != NULL )


    std::ifstream student_instr(filename(j).c_str());
    std::ifstream expected_instr(expected(j).c_str());

    std::string s = "";
    std::string e = "";

    if (student_instr) {
      std::cout << "STUDENT FILE EXISTS" << std::endl;
      s = std::string(std::istreambuf_iterator<char>(student_instr),
		      std::istreambuf_iterator<char>());
      std::cout << "student file size = " << s.size() << std::endl;
    }
    if (expected_instr) {
      std::cout << "EXPECTED FILE EXISTS" << std::endl;
      e = std::string(std::istreambuf_iterator<char>(expected_instr),
		      std::istreambuf_iterator<char>());
      std::cout << "expected file size = " << e.size() << std::endl;
    }


    // FIXME should be configurable within the homework, but should not exceed what is reasonable to myers diff
    #define MAX_FILE_SIZE 200 * 50 // in characters  (approx 200 lines with 50 characters per line)


    if (s.size() > MAX_FILE_SIZE) {
      std::cout << "ERROR: student file size too big " << s.size() << " > " << MAX_FILE_SIZE << std::endl;
      return new TestResults(0,"ERROR: student file too large for comparison");
    }
    if (e.size() > MAX_FILE_SIZE) {
      std::cout << "ERROR: expected file size too big " << e.size() << " > " << MAX_FILE_SIZE << std::endl;
      return new TestResults(0,"ERROR: expected file too large for comparison");
    }


    std::cout << "GOING TO COMPARE studentsize=" << s.size() << "  expectedsize="<< e.size() << std::endl;

    return test_case_comparison[j].cmp_output (s,e);
  }

  int seconds_to_run() { return 5; }

  bool isFileExistsTest() { return FILE_EXISTS; }
  bool isCompilationTest() { return COMPILATION; }

private:

		std::string _title;
		std::string _details;
		std::string _command;


  TestCasePoints _test_case_points;

  TestCaseComparison test_case_comparison[3];

  bool FILE_EXISTS;
  bool COMPILATION;

  int test_case_id;
  static int next_test_case_id; 
  

};





#endif
