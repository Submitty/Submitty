/* FILENAME: myersDiff.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

#include <unistd.h>
#include <algorithm>

#include "myersDiff.h"
#include "json.hpp"
#include "tokens.h"
#include "clean.h"

#include "execute.h"
#include "window_utils.h"


// ==============================================================================
// ==============================================================================


TestResults* fileExists_doit (const TestCase &tc, const nlohmann::json& j) {

  // grab the required files
  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"actual_file");
  if (filenames.size() == 0) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: no required files specified")});
  }
  for (int f = 0; f < filenames.size(); f++) {
    if (!tc.isCompilation()) {
      //filenames[f] = tc.getPrefix() + "_" + filenames[f];
      //filenames[f] = tc.getPrefix() + "_" + filenames[f];
      //filenames[f] = replace_slash_with_double_underscore(filenames[f]);
    }
  }

  // is it required to have all of these files or just one of these files?
  bool one_of = j.value("one_of",false);

  // loop over all of the listed files
  int found_count = 0;
  std::string files_not_found;
  for (int f = 0; f < filenames.size(); f++) {
    std::cout << "  file exists check: '" << filenames[f] << "' : ";
    std::vector<std::string> files;
    wildcard_expansion(files, filenames[f], std::cout);
    wildcard_expansion(files, tc.getPrefix() + "_" + filenames[f], std::cout);
    bool found = false;
    // loop over the available files
    for (int i = 0; i < files.size(); i++) {
      std::cout << "FILE CANDIDATE: " << files[i] << std::endl;
      if (access( files[i].c_str(), F_OK|R_OK ) != -1) { // file exists
        std::cout << "FOUND '" << files[i] << "'" << std::endl;
        found = true;
      } else {
        std::cout << "OOPS, does not exist: " << files[i] << std::endl;
      }
    }
    if (found) {
      found_count++;
    } else {
      files_not_found += " " + filenames[f];
    }
  }

  // the answer
  if (one_of) {
    if (found_count > 0) {
      return new TestResults(1.0);
    } else {
      std::cout << "FILE NOT FOUND " + files_not_found << std::endl;
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: required file not found: " + files_not_found)});
    }
  } else {
    if (found_count == filenames.size()) {
      return new TestResults(1.0);
    } else {
      std::cout << "FILES NOT FOUND " + files_not_found << std::endl;
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: required files not found: " + files_not_found)});
    }
  }
}


TestResults* warnIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::cout << "WARNING IF NOT EMPTY DO IT" << std::endl;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) { 
    return new TestResults(1.0,messages);
  }
  if (student_file_contents != "") {
    return new TestResults(1.0,{std::make_pair(MESSAGE_WARNING,"WARNING: This file should be empty")});
  }
  return new TestResults(1.0);
}


TestResults* errorIfNotEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) { 
    return new TestResults(0.0,messages);
  }
  if (student_file_contents != "") {
    if (student_file_contents.find("error") != std::string::npos)
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
    else if (student_file_contents.find("warning") != std::string::npos)
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
    else
      return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should be empty!")});
  }
  return new TestResults(1.0);
}


TestResults* warnIfEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) { 
    return new TestResults(1.0,messages);
  }
  if (student_file_contents == "") {
    return new TestResults(1.0,{std::make_pair(MESSAGE_WARNING,"WARNING: This file should not be empty")});
  }
  return new TestResults(1.0);
}


TestResults* errorIfEmpty_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) { 
    return new TestResults(0.0,messages);
  }
  if (student_file_contents == "") {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: This file should not be empty!")});
  }
  return new TestResults(1.0);
}

// ==============================================================================
// ==============================================================================

TestResults* ImageDiff_doit(const TestCase &tc, const nlohmann::json& j, int autocheck_number) {
  std::string actual_file = j.value("actual_file","");
  std::string expected_file = j.value("expected_file","");
  std::string acceptable_threshold_str = j.value("acceptable_threshold","");

  if(actual_file == "" || expected_file == "" || acceptable_threshold_str == ""){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Error in configuration. Please speak to your instructor.")});
  }

  if (access(expected_file.c_str(), F_OK|R_OK ) == -1)
  {
        return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: The instructor's image was not found. Please notify your instructor")});
  }

  float acceptable_threshold = stringToFloat(acceptable_threshold_str,6); //window_utils function.


  actual_file = tc.getPrefix() + "_" + actual_file;
  std::cout << "About to compare " << actual_file << " and " << expected_file << std::endl;


  std::string command = "compare -metric RMSE " + actual_file + " " + expected_file + " NULL: 2>&1";
  std::string output = output_of_system_command(command.c_str()); //get the string
  std::cout << "captured the following:\n" << output << "\n" <<std::endl;

  std::vector<float> values = extractFloatsFromString(output); //window_utils function.
  if(values.size() < 2){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "Image comparison failed; Images are incomparable.")});
  }

  float difference = values[1];
  float similarity = 1 - difference;

  std::string diff_file_name = tc.getPrefix() + "_" + std::to_string(autocheck_number) + "_difference.png";

  std::cout << "About to compose the images." << std::endl;
  std::string command2 = "compare " + actual_file + " " + expected_file + " -fuzz 10% -highlight-color red -lowlight-color none -compose src " + diff_file_name;
  system(command2.c_str());
  std::cout << "Composed." <<std::endl;

  if(difference >= acceptable_threshold){
    return new TestResults(0.0, {std::make_pair(MESSAGE_FAILURE, "ERROR: Your image does not match the instructor's.")});
  }
   else{
    // MESSAGE_NONE, MESSAGE_FAILURE, MESSAGE_WARNING, MESSAGE_SUCCESS, MESSAGE_INFORMATION
         return new TestResults(1.0, {std::make_pair(MESSAGE_INFORMATION, "SUCCESS: Your image was close enough to your instructor's!")});
  }


  //   return new TestResults(0.0, {"ERROR: File comparison failed."});

}


// ==============================================================================
// ==============================================================================

void LineHighlight(std::stringstream &swap_difference, bool &first_diff, int student_line, 
                   int expected_line, bool only_student, bool only_expected) {
  if (!first_diff) {
    swap_difference << "  ,\n";
  }
  using json = nlohmann::json;

  json j;
  j["actual"]["start"] = student_line;

  if (!only_expected) {
    json i;
    i["line_number"] = student_line;
    j["actual"]["line"] = { i };
  }

  std::cout << "LINE HIGHLIGHT " << expected_line << std::endl;
  j["expected"]["start"] = expected_line;

  if (!only_student) {
    json i;
    i["line_number"] = expected_line;
    j["expected"]["line"] = { i };
  }
  swap_difference << j.dump(4) << std::endl;
  first_diff = false;
}


// FIXME: might be nice to highlight small errors on a line
//
TestResults* diffLineSwapOk_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  std::string expected_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) { 
    return new TestResults(0.0,messages);
  }
  if (!openExpectedFile(tc,j,expected_file_contents,messages)) { 
    return new TestResults(0.0,messages);
  }


  // break each file (at the newlines) into vectors of strings
  vectorOfLines student = stringToLines( student_file_contents, j );
  vectorOfLines expected = stringToLines( expected_file_contents, j );

  // check for an empty solution file
  if (expected.size() < 1) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR!  expected file is empty")});
  }
  assert (expected.size() > 0);

  // where we will temporarily write the line highlighting json file.
  // FIXME: currently coloring all problems the same color... could
  // extend to specify a difference color for incorrect vs. missing
  // vs. duplicate
  std::stringstream swap_difference;
  swap_difference << "{\n";
  swap_difference << "\"differences\":[\n";
  bool first_diff = true;
  ;
  // counts of problems between the student & expected file
  int incorrect = 0;
  int duplicates = 0;
  int missing = 0;

  // for each line of the expected file, count the number of lines in
  // the student file that match it.
  std::vector<int> matches(expected.size(),0);

  // walk through the student file, trying to find a unique match in
  // the expected file.
  // FIXME: Currently assuming all lines in the expected file are
  // unique...  could make this more sophisticated.
  for (unsigned int i = 0; i < student.size(); i++) {
    bool match = false;
    bool duplicate = false;
    for (unsigned int j = 0; j < expected.size(); j++) {
      if (student[i] == expected[j]) {
        if (matches[j] > 0) duplicate = true;
        matches[j]++;
        match = true;
        break;
      }
    }
    if (!match) {
      incorrect++;
    }
    if (!match || duplicate) {
      std::cout << "!match or duplicate" <<std::endl;
      LineHighlight(swap_difference,first_diff,i,expected.size()+10,true,false);
      //LineHighlight(swap_difference,first_diff,i,0,true,false);
    }
  }

  // count the number of missing lines
  for (unsigned int j = 0; j < expected.size(); j++) {
    if (matches[j] == 0) {
      missing++;
      std::cout << "missing" <<std::endl;
      LineHighlight(swap_difference,first_diff,student.size()+10,j,false,true);
      //LineHighlight(swap_difference,first_diff,0,j,false,true);
    }
    if (matches[j] > 1) duplicates+= (matches[j]-1);
  }
  swap_difference << "]\n";
  swap_difference << "}\n";

  // calculate the score
  int wrong = std::max(missing,duplicates+incorrect);
  double score = double(int(expected.size())-wrong)/double(expected.size());
  score = std::max(0.0,score);

  // prepare the graded message for the student
  std::stringstream ss;
  if (incorrect > 0) {
    ss << "ERROR: " << incorrect << " incorrect line(s)";
  }
  if (duplicates > 0) {
    if (ss.str().size() > 0) {
      ss << ", ";
    }
    ss << "ERROR: " << duplicates << " duplicate line(s)";
  }
  if (missing > 0) {
    if (ss.str().size() > 0) {
      ss << ", ";
    }
    ss << "ERROR: " << missing << " missing line(s)";
  }

  return new TestResults(score,{std::make_pair(MESSAGE_FAILURE,ss.str())},swap_difference.str());
}

// ===============================================================================
// ===============================================================================

// Runs all the ses functions
/*
@param T* student_output - a pointer to a vector<vector<string> > that is the student output file
@param T* inst_output - a pointer to a vector<vector<stirng> > that is the expected output file
@param bool secondary 
@param bool extraStudentOutputOk - boolean that tells if it is okay to have extra student
       output at the end of the student output file 
*/
template<class T> Difference* ses (const nlohmann::json& j, T* student_output, T* inst_output, bool secondary, bool extraStudentOutputOk  ) {

  metaData< T > meta_diff = sesSnapshots( ( T* ) student_output, ( T* ) inst_output, extraStudentOutputOk );
  sesSnakes( meta_diff,  extraStudentOutputOk  );

  Difference* diff = sesChanges( meta_diff, extraStudentOutputOk );
  if ( secondary ) {
    if (j != nlohmann::json()) { /*std::cout << "do a secondary" << std::endl; */ }
    sesSecondary( diff, meta_diff, extraStudentOutputOk );
  } else {
    if (j != nlohmann::json()) { /*std::cout << "no secondary" << std::endl; */ }
  }

  diff->only_whitespace_changes = true;

  
  diff->line_added = 0;
  diff->line_deleted = 0; 
  diff->total_line = 0;
  diff->char_added = 0;
  diff->char_deleted = 0; 
  diff->total_char = 0;

  diff->total_line += (*inst_output).size();
  for (int i = 0; i < (*inst_output).size(); i++) {
    diff->total_char+=(*inst_output)[i].size();
  }

  for (int x = 0; x < diff->changes.size(); x++) {
    INSPECT_IMPROVE_CHANGES(std::cout,
        diff->changes[x],
        *student_output,
        *inst_output,
        j,
        diff->only_whitespace_changes,extraStudentOutputOk,
        diff->line_added, diff->line_deleted, 
        diff->char_added, diff->char_deleted);
  }

  if (j != nlohmann::json()) {
    if (diff->only_whitespace_changes) {
      std::cout << "ONLY WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
    } else {
      std::cout << "FILE HAS NON WHITESPACE CHANGES!!!!!!!!!!!!!" << std::endl;
    }
    //std::cout << "INSPECT CHANGES   lines  added=" << diff->line_added << "  deleted=" << diff->line_deleted << "  total=" << diff->total_line;
    //std::cout << "   chars  added=" << diff->char_added << "  deleted=" << diff->char_deleted << "  total=" << diff->total_char << std::endl;
  }

  if (j != nlohmann::json()) {
    diff->PrepareGrade(j);
  }
  return diff;
}

// runs shortest edit script. Saves traces in snapshots,
// the edit distance in distance and pointers to objects a and b
/*
@param T* student_output - a pointer to a vector<vector<string> > that is the student output file
@param T* inst_output - a pointer to a vector<vector<stirng> > that is the expected output file
@param bool extraStudentOutputOk - boolean that tells if it is okay to have extra student
       output at the end of the student output file 
*/
template<class T> metaData< T > sesSnapshots ( T* student_output, T* inst_output, bool extraStudentOutputOk ) {
  //takes 2 strings or vectors of values and finds the shortest edit script
  //to convert a into b
        int student_output_size = ( int ) student_output->size();
  int inst_output_size = ( int ) inst_output->size();
  metaData< T > text_diff;
  if ( student_output_size == 0 && inst_output_size == 0 ) {
    return text_diff;
  }
  text_diff.m = inst_output_size;
  text_diff.n = student_output_size;
    // DISTANCE -1 MEANS WHAT?
    text_diff.distance = -1;
    text_diff.a = student_output;
    text_diff.b = inst_output;

  // WHAT IS V?
  //std::vector< int > v( ( a_size + b_size ) * 2, 0 );
  // TODO: BOUNDS ERROR, is this the appropriate fix?
  std::vector< int > v( ( student_output_size + inst_output_size ) * 2 + 1, 0 );

  //loop until the correct diff (d) value is reached, or until end is reached
  for ( int d = 0; d <= ( student_output_size + inst_output_size ); d++ ) {
    // find all the possibile k lines represented by  y = x-k from the max
    // negative diff value to the max positive diff value
    // represents the possibilities for additions and deletions at diffrent
    // points in the file
    for ( int k = -d; k <= d; k += 2 ) {
      //which is the farthest path reached in the previous iteration?
      bool down = ( k == -d ||
                  ( k != d && v[ ( k - 1 ) + ( student_output_size + inst_output_size )]
              < v[ ( k + 1 ) + ( student_output_size + inst_output_size )] ) );
      int k_prev, a_start, a_end, b_end;
      if ( down ) {
        k_prev = k + 1;
        a_start = v[k_prev + ( student_output_size + inst_output_size )];
        a_end = a_start;
      } else {
        k_prev = k - 1;
        a_start = v[k_prev + ( student_output_size + inst_output_size )];
        a_end = a_start + 1;
      }

      b_end = a_end - k;
      // follow diagonal
      while ( a_end < student_output_size && b_end < inst_output_size && ( *student_output )[a_end] == ( *inst_output )[b_end] ) {
        a_end++;
        b_end++;
      }

      // save end point
      if (k+(student_output_size+inst_output_size) < 0 || k+(student_output_size+inst_output_size) >= v.size()) {
        std::cerr << "ERROR VALUE " << k+(student_output_size+inst_output_size) << " OUT OF RANGE " << v.size() << " k=" << k 
                  << " student_output_size=" << student_output_size << " inst_output_size=" << inst_output_size << std::endl;
      }
      v[k + ( student_output_size + inst_output_size )] = a_end;
      // check for solution
      if ( a_end >= student_output_size && b_end >= inst_output_size ) { /* solution has been found */
        text_diff.distance = d;
        text_diff.snapshots.push_back( v );
        return text_diff;
      }
    }
    text_diff.snapshots.push_back( v );


    //std::cout << "TEXTDIFF " << std::endl;

    //std::cout << "SNAPSHOTS\n" << text_diff.snapshots << std::endl;

  }
  return text_diff;
}

// takes a metaData object with snapshots and parses to find the "snake"
// - a path that leads from the start to the end of both of a and b
/*
@param metaData<T> & meta_diff - container that has the two file sizes, pointers to the file,
       and a vector of the snapshots found
@param bool extraStudentOutputOk - boolean that tells if it is okay to have extra student
       output at the end of the student output file 
*/
template<class T> metaData< T > sesSnakes ( metaData< T > & meta_diff, bool extraStudentOutputOk  ) {
  int n = meta_diff.n;
  int m = meta_diff.m;

  meta_diff.snakes.clear();

  int point[2] = { n, m };
  // loop through the snapshots until all differences have been recorded
  for ( int d = int( meta_diff.snapshots.size() - 1 );
      ( point[0] > 0 || point[1] > 0 ) && d >= 0; d-- ) {

    std::vector< int > v( meta_diff.snapshots[d] );
    int k = point[0] - point[1]; // find the k value from y = x-k
    int a_end = v[k + ( n + m )];
    int b_end = a_end - k;

    //which is the farthest path reached in the previous iteration?
    bool down = ( k == -d
        || ( k != d && v[k - 1 + ( n + m )] < v[k + 1 + ( n + m )] ) );

    int k_prev;

    if ( down ) {
      k_prev = k + 1;
    } else {
      k_prev = k - 1;
    }
    // follow diagonal
    int a_start = v[k_prev + ( n + m )];
    int b_start = a_start - k_prev;

    int a_mid;

    if ( down ) {
      a_mid = a_start;
    } else {
      a_mid = a_start + 1;
    }

    int b_mid = a_mid - k;

    // FIXME: a snake is always 6 integers?  This is a
    // terribly confusing representation, why a
    // vector<int>?  should be its own data type perhaps?

    std::vector< int > snake;
    // add beginning, middle, and end points
    snake.push_back( a_start );
    snake.push_back( b_start );
    snake.push_back( a_mid );
    snake.push_back( b_mid );
    snake.push_back( a_end );
    snake.push_back( b_end );

    // is this just a push_front wanna be?
    // should this be switched to a list?
    // is the order important?
    meta_diff.snakes.insert( meta_diff.snakes.begin(), snake );

    point[0] = a_start;
    point[1] = b_start;
  }

  //std::cout << "META DIFF LENGTH " << meta_diff.snakes.size() << std::endl;

  //std::cout << "SNAKES\n" << meta_diff.snakes << std::endl;


  // free up memory by deleting the snapshots
  meta_diff.snapshots.clear();


  return meta_diff;
}

// Takes a metaData object and parses the snake to constuct a vector of
// Change objects, which each hold the diffrences between a and b, lumped
// by if they are neighboring. Also fills diff_a and diff_b with the diffrences
// All differences are stored by element number
template<class T> Difference* sesChanges ( metaData< T > & meta_diff, bool extraStudentOutputOk ) {
       Difference* diff = new Difference();
       diff->extraStudentOutputOk = extraStudentOutputOk;
       diff->edit_distance = meta_diff.distance;
    if (meta_diff.a != NULL){
        diff->output_length_a = ( int ) meta_diff.a->size();
    }
    else{
        diff->output_length_a = 0;
    }
    if (meta_diff.b != NULL){
        diff->output_length_b = ( int ) meta_diff.b->size();
    }
    else{
        diff->output_length_b = 0;
    }
  int added = abs( diff->output_length_a - diff->output_length_b );
  diff->setDistance( ( meta_diff.distance - added ) / 2 );
  diff->setDistance( diff->getDistance() + added );

  if ( meta_diff.snakes.size() == 0 ) {
    diff->setGrade(1);
    return diff;
  }
  Change change_var;
  change_var.clear();
  std::vector< std::vector< int > > change_groups;
  int a = 1;
  if ( meta_diff.snakes[0][0] != -1 && meta_diff.snakes[0][1] != -1 ) {
    a = 0;
  }
  for ( ; a < meta_diff.snakes.size(); a++ ) {
    int * a_start = &meta_diff.snakes[a][0];
    int * b_start = &meta_diff.snakes[a][1];
    int * a_mid = &meta_diff.snakes[a][2];
    int * b_mid = &meta_diff.snakes[a][3];
    int * a_end = &meta_diff.snakes[a][4];
    int * b_end = &meta_diff.snakes[a][5];

    if ( *a_start != *a_mid ) { //if "a" was changed, add the line/char number
      change_var.a_changes.push_back( *a_mid - 1 );
      if ( change_var.a_start == -1
          || change_var.a_changes.size() == 1 ) {
        change_var.a_start = *a_mid - 1;
        if ( change_var.b_start == -1 && *b_start == *b_mid ) {
          change_var.b_start = *b_mid - 1;
        }
      }
    }

    if ( *b_start != *b_mid ) { //if "b" was changed, add the line/char number
      change_var.b_changes.push_back( *b_mid - 1 );
      if ( change_var.b_start == -1
          || change_var.b_changes.size() == 1 ) {
        change_var.b_start = *b_mid - 1;
        if ( change_var.a_start == -1 && *a_start == *a_mid ) {
          change_var.a_start = *a_mid - 1;
        }
      }
    }
    if ( *a_mid != *a_end || *b_mid != *b_end ) {
      //if a section of identical text is reached, push back the change
      diff->changes.push_back( change_var );
      for ( int b = 0; b < change_var.a_changes.size(); b++ ) {
        diff->diff_a.push_back( change_var.a_changes[b] );
      }
      for ( int b = 0; b < change_var.b_changes.size(); b++ ) {
        diff->diff_b.push_back( change_var.b_changes[b] );
      }
      //start again
      change_var.clear();
    }
  }
  if ( change_var.a_changes.size() != 0
      || change_var.b_changes.size() != 0 ) {
    diff->changes.push_back( change_var );
    for ( int b = 0; b < change_var.a_changes.size(); b++ ) {
      diff->diff_a.push_back( change_var.a_changes[b] );
    }
    for ( int b = 0; b < change_var.b_changes.size(); b++ ) {
      diff->diff_b.push_back( change_var.b_changes[b] );
    }
    change_var.clear();
  }

  return diff;

}


// =================================================================
// =================================================================

void Difference::PrepareGrade(const nlohmann::json& j) {
  std::cout << "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%" << std::endl;
  std::cout << "PREPARE GRADE" << std::endl;


  // --------------------------------------------------------
  //std::cout << "json " << j.dump(4) << std::endl;
  if (j.find("max_char_changes") != j.end()) {
    std::cout << "MAX CHAR CHANGES" << std::endl;

    int max_char_changes = j.value("max_char_changes", -1);
    assert (max_char_changes > 0);
    int min_char_changes = j.value("min_char_changes",0);
    assert (min_char_changes >= 0);
    assert (min_char_changes < max_char_changes);

    assert (total_char > 0);
    if (max_char_changes > total_char) {
      std::cout << "WARNING!  max_char_changes > total_char)" << std::endl;
      max_char_changes = total_char;
      if (min_char_changes > max_char_changes) {
        min_char_changes = max_char_changes-1;
      }
      assert (min_char_changes >= 0);
    }
    assert (max_char_changes <= total_char);

    int char_changes = char_added + char_deleted;

    std::cout << "char_changes=" << char_changes << " min=" << min_char_changes << " max=" << max_char_changes << std::endl;

    int min_max_diff = max_char_changes-min_char_changes;
    int lower_bar = std::max(0,min_char_changes-min_max_diff);
    int upper_bar = max_char_changes + min_max_diff;
    
    assert (0 <= lower_bar &&
      lower_bar <= min_char_changes &&
      min_char_changes <= max_char_changes &&
      max_char_changes <= upper_bar);

    float grade;
    if (char_changes < lower_bar) {
      std::cout << "too few char changes (zero credit)" << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Significantly fewer character changes than allowed."));
    } else if (char_changes < min_char_changes) {
      std::cout << "less than min char changes (partial credit)" << std::endl;
      float numer = min_char_changes - char_changes;
      float denom = min_max_diff;
      std::cout << "numer " << numer << " denom= " << denom << std::endl;
      assert (denom > 0);
      grade = 1 - numer/denom;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Fewer character changes than allowed."));
    } else if (char_changes < max_char_changes) {
      messages.push_back(std::make_pair(MESSAGE_SUCCESS,"Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Character changes within allowed range."));
      std::cout << "between min and max char changes (full credit)" << std::endl;
      grade = 1.0;
    } else if (char_changes < upper_bar) {
      std::cout << "more than max char changes (partial credit)" << std::endl;
      float numer = char_changes - max_char_changes;
      float denom = min_max_diff;
      assert (denom > 0);
      grade = 1 - numer/denom;
      std::cout << "numer " << numer << " denom= " << denom << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  More character changes than allowed."));
    } else {
      std::cout << "too many char changes (zero credit)" << std::endl;
      messages.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR!  Approx " + std::to_string(char_changes) + " characters added and/or deleted.  Significantly more character changes than allowed."));
      grade = 0.0;
    }
    std::cout << "grade " << grade << std::endl;
    assert (grade >= -0.00001 & grade <= 1.00001);
    this->setGrade(grade);
  }

  // --------------------------------------------------------
  else if (this->extraStudentOutputOk) {
    // only missing lines (deletions) are a problem
    int count_of_missing_lines = 0;
    for (int x = 0; x < this->changes.size(); x++) {
      int num_b_lines = this->changes[x].b_changes.size();
      if (num_b_lines > 0) {
        count_of_missing_lines += num_b_lines;
      }
    }
    int output_length = this->output_length_b;
    std::cout << "COMPARE outputlength=" << output_length << " missinglines=" << count_of_missing_lines << std::endl;
    assert (count_of_missing_lines <= output_length);
    float grade = 1.0;
    if (output_length > 0) {
      //std::cout << "SES [ESOO] calculating grade " << this->distance << "/" << output_length << std::endl;
      //grade -= (this->distance / (float) output_length );
      grade -= count_of_missing_lines / float(output_length);
      std::cout <<
        "grade:  missing_lines [ " << count_of_missing_lines <<
        "] / output_length " << output_length << "]\n";
      //std::cout << "SES [ESOO] calculated grade = " << std::setprecision(1) << std::fixed << std::setw(5) << grade << " " << std::setw(5) << (int)floor(5*grade) << std::endl;
      if (grade < 1.0 && this->only_whitespace_changes) {
        std::cout << "ONLY WHITESPACE DIFFERENCES! adjusting grade: " << grade << " -> ";
        // FIXME:  Ugly, but with rounding, this will be only a -1 point grade for this test case
        grade = std::max(grade,0.99f);
        std::cout << grade << std::endl;
      } else {
        std::cout << "MORE THAN JUST WHITESPACE DIFFERENCES! " << std::endl;
      }
    } else {
      assert (output_length == 0);
      std::cout << "NO OUTPUT, GRADE IS ZERO" << std::endl;
      grade = 0;
    }

    std::cout << "this test grade = " << grade << std::endl;
    this->setGrade(grade);
  }

  // --------------------------------------------------------
  else {
    // both missing lines (deletions) and extra lines are a deduction
    int max_output_length = std::max(this->output_length_a, this->output_length_b);
    float grade = 1.0;
    if (max_output_length == 0) {
      grade = 0;
    } else {
      //std::cout << "SES  calculating grade " << this->distance << "/" << max_output_length << std::endl;
      grade -= (this->distance / (float) max_output_length );
      std::cout <<
        "grade:  this->distance [ " << this->distance <<
        "] / max_output_length " << max_output_length << "]\n";
      //std::cout << "SES calculated grade = " << grade << std::endl;
    }
    this->setGrade(grade);
  }
  // ===================================================
  std::cout << "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%" << std::endl;
}


// =================================================================
// =================================================================


// Takes a Difference object that has it's changes vector filled and parses to
// find substitution chunks. It then runs a secondary diff to find diffrences
// between the elements of each version of the line
template<class T> Difference* sesSecondary ( Difference* text_diff,
               metaData< T > & meta_diff, bool extraStudentOutputOk  ) {
  for ( int a = 0; a < text_diff->changes.size(); a++ ) {
    Change* current = &text_diff->changes[a];
    if ( current->a_changes.size() == 0
        || current->b_changes.size() == 0 ) {
      continue;
    } else if ( current->a_changes.size() == current->b_changes.size() ) {
      for ( int b = 0; b < current->a_changes.size(); b++ ) {

// FIXME: This code is not sufficiently commented to allow reader
// understanding and long term  maintenance

              // FIXME: does not compile with clang -std=c++11
              //metaData< typeof(*meta_diff.a)[current->a_changes[b]] > meta_second_diff;

        Difference* second_diff;

        // FIXME: so added auto instead
        // code is fragile to change in compiler options
        auto meta_second_diff = sesSnapshots(
            &( *meta_diff.a )[current->a_changes[b]],
            &( *meta_diff.b )[current->b_changes[b]], extraStudentOutputOk );

        sesSnakes( meta_second_diff,  extraStudentOutputOk  );
        second_diff = sesChanges( meta_second_diff, extraStudentOutputOk );
        current->a_characters.push_back( second_diff->diff_a );
        current->b_characters.push_back( second_diff->diff_b );
        delete second_diff;
      }
    }
//        else{
//            current->a_characters.push_back(std::vector<int>());
//            current->b_characters.push_back(std::vector<int>());
//        }
  }
  return text_diff;
}
// formats and outputs a Difference object to the ofstream


// ===================================================================
// ===================================================================

TestResults* diff_doit (const TestCase &tc, const nlohmann::json& j) {
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  std::string student_file_contents;
  std::string expected_file_contents;
  if (!openStudentFile(tc,j,student_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }
  if (!openExpectedFile(tc,j,expected_file_contents,messages)) {
    return new TestResults(0.0,messages);
  }
  if (student_file_contents.size() > MYERS_DIFF_MAX_FILE_SIZE_MODERATE &&
      student_file_contents.size() > 10* expected_file_contents.size()) {
    return new TestResults(0.0,{std::make_pair(MESSAGE_FAILURE,"ERROR: Student file too large for grader")});
  }

  TestResults* answer = NULL;
  std::string comparison = j.value("comparison","byLinebyChar");
  bool ignoreWhitespace = j.value("ignoreWhitespace",false);
  if (comparison == std::string("byLinebyChar")) {
    bool extraStudentOutputOk = j.value("extra_student_output",false);
    vectorOfLines text_a = stringToLines( student_file_contents, j );
    vectorOfLines text_b = stringToLines( expected_file_contents, j );
    answer = ses(j, &text_a, &text_b, true, extraStudentOutputOk );
    ((Difference*)answer)->type = ByLineByChar;
  } else if (comparison == std::string("byLinebyWord")) {
    vectorOfWords text_a = stringToWords( student_file_contents );
    vectorOfWords text_b = stringToWords( expected_file_contents );
    answer = ses(j, &text_a, &text_b, true );
    ((Difference*)answer)->type = ByLineByWord;
  } else if (comparison == std::string("byLine")) {
    if (ignoreWhitespace) {
      vectorOfWords text_a = stringToWordsLimitLineLength( student_file_contents );
      vectorOfWords text_b = stringToWordsLimitLineLength( expected_file_contents );
      answer = ses(j, &text_a, &text_b, false );
      ((Difference*)answer)->type = ByLineByWord;
    } else {
      vectorOfLines text_a = stringToLines( student_file_contents, j );
      vectorOfLines text_b = stringToLines( expected_file_contents, j );
      bool extraStudentOutputOk = j.value("extra_student_output",false);
      answer = ses(j, &text_a, &text_b, false,extraStudentOutputOk);
      ((Difference*)answer)->type = ByLineByChar;
    }
  } else {
    std::cout << "ERROR!  UNKNOWN COMPARISON" << comparison << std::endl;
    std::cerr << "ERROR!  UNKNOWN COMPARISON" << comparison << std::endl;
    answer = new TestResults(0.0);
  }
  assert (answer != NULL);
  return answer;
}

// ===================================================================
