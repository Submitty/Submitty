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

#include <cstdlib>
#include <iostream>
#include <string>
#include <vector>

#include "change.h"
#include "difference.h"
#include "metaData.h"

#include <nlohmann/json.hpp>


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

// Runs all the ses functions
/*
@param T* student_output - a pointer to a vector<vector<string> > that is the student output file
@param T* inst_output - a pointer to a vector<vector<stirng> > that is the expected output file
@param bool secondary
@param bool extraStudentOutputOk - boolean that tells if it is okay to have extra student
       output at the end of the student output file
*/
template<class T> Difference* ses (const nlohmann::json& j, T* student_output, T* inst_output, bool secondary = false, bool extraStudentOutputOk = false) {

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

// ===============================================================================
// ===============================================================================

// template<class T> Difference* printJSON ( Difference & text_diff, std::ofstream & file_out, int type = 0 );


#endif
