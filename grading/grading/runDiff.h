/* FILENAME: runDiff.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 * Provides the functionality of opening and running the input files (student files) 
 * and the expected files to get the differences between them.  Relies on the
 * method implemented in differences.h and differences.cpp
 */
 
#ifndef differences_runDiff_h
#define differences_runDiff_h

#define OtherType 0
#define StringType 1
#define VectorStringType 2
#define VectorVectorStringType 3
#define VectorVectorOtherType 4
#define VectorOtherType 5

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <fstream>

/* METHOD: readFileList
 * ARGS: input: list of file names, sample_file: expected file,
 * student_files: vector of strings that contain names of student_files
 * RETURN: void
 * PURPOSE: Compile a list of student files from the sample file
 */
void readFileList ( std::string input, std::string & sample_file,
          std::vector< std::string >& student_files ) {
  std::ifstream in_file( input.c_str() );
  if ( !in_file.good() ) {
    std::cerr << "Can't open " << input << " to read.\n";
    in_file.close();
    return;
  }
  std::string line;
  if ( getline( in_file, line ) ) {
    sample_file = line;
  }
  while ( getline( in_file, line ) ) {
    student_files.push_back( line );
  }
  in_file.close();
}

/* METHOD: getFileInput
 * ARGS: file: string containing file name of file to be opened
 * RETURN: output: string containing output of file
 * PURPOSE: Get all output of a file
 */
std::string getFileInput ( std::string file ) {
  std::ifstream input( file.c_str(), std::ifstream::in );
  if ( !input ) {
    std::cout << "ERROR: File " << file << " does not exist" << std::endl;
    return "";
  }
  const std::string output = std::string(
      std::istreambuf_iterator< char >( input ),
      std::istreambuf_iterator< char >() );
  return output;
}

/* METHOD: runFiles
 * ARGS: input: list of files names, where the first is the expected file
 * RETURN: void
 * PURPOSE: Run both the expected and student file to get the differences
 * between them and make the resulting JSON
 */
void runFiles ( std::string input ) {
  std::string sample_file;
  std::vector< std::string > student_files;
  readFileList( input, sample_file, student_files ); //read all the file names from the input file

  vectorOfWords contents, sample_text;
  std::string text;
  text = getFileInput( sample_file ); // get the text from the expected file
  sample_text = stringToWords( text );
  for ( int a = 0; a < student_files.size(); a++ ) {
    contents.clear();
    text = getFileInput( student_files[a] ); //get the text from the student file
    contents = stringToWords( text );
    //get the differences
    Difference text_diff = ses( &contents, &sample_text, true );
    std::string file_name( student_files[a] );
    file_name.erase( student_files[a].size() - 4, student_files[a].size() );
    std::ofstream file_out;
    file_out.open( ( file_name + "_out.json" ).c_str() ); //edit the name to add _out
    if ( !file_out.good() ) {
      std::cerr << "Can't open " << student_files[a] + "_out"
          << " to write.\n";
      file_out.close();
      continue;
    }

    text_diff.printJSON( file_out, VectorVectorStringType ); //print the diffrences as JSON files
  }
}

/* METHOD: runFilesDiff
 * ARGS: input: list of files names, where first is the expected file
 * RETURN: void
 * PURPOSE: Another implementation of runFiles (look above for implementation)
 */
void runFilesDiff ( std::string input ) { 
  std::string sample_file;
  std::vector< std::string > student_files;
  readFileList( input, sample_file, student_files ); //read all the file names from the input file

  std::string contents, sample_text;
  std::string text;
  text = getFileInput( sample_file ); // get the text from the expected file
  sample_text = ( text );
  for ( int a = 0; a < student_files.size(); a++ ) {
    contents.clear();
    text = getFileInput( student_files[a] ); //get the text from the student file
    contents = ( text );
    //get the diffrences
    Difference text_diff = diffLine( contents, sample_text );
    std::string file_name( student_files[a] );
    file_name.erase( student_files[a].size() - 4, student_files[a].size() );
    std::ofstream file_out;
    file_out.open( ( file_name + "_out.json" ).c_str() ); //edit the name to add _out
    if ( !file_out.good() ) {
      std::cerr << "Can't open " << student_files[a] + "_out"
          << " to write.\n";
      file_out.close();
      continue;
    }

    text_diff.printJSON( file_out, VectorVectorStringType ); //print the diffrences as JSON files
  }
}

#endif
