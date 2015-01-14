#include "myersDiff.h"
#include "tokens.h"

TestResults* warnIfNotEmpty (const std::string & student_file, const std::string & expected_file) {
  std::cout << "in warn if not empty" << std::endl;
  std::cout << "the file '" << student_file << "'" << std::endl;
  // the instructor file should be empty
  assert (expected_file == "");
  Tokens* answer = new Tokens();
  if (student_file != "") {
    answer->setMessage("WARNING: This should be empty");
    std::cout << "in warn if not empty -- student file not empty" << std::endl;
  }
  return answer;
}

TestResults* errorIfNotEmpty ( const std::string & student_file, const std::string & expected_file) {
  std::cout << "in error if not empty" << std::endl;
  std::cout << "the file '" << student_file << "'" << std::endl;
  // the instructor file should be empty
  assert (expected_file == "");
  Tokens* answer = new Tokens();
  if (expected_file != "") {
    answer->setMessage("ERROR: This should be empty");
    std::cout << "in error if not empty -- student file not empty" << std::endl;
    answer->setGrade(0);
  }
  return answer;
}


TestResults* errorIfEmpty ( const std::string & student_file, const std::string & expected_file) {
  std::cout << "in error if empty" << std::endl;
  std::cout << "the file '" << student_file << "'" << std::endl;
  // the instructor file should be empty
  assert (expected_file == "");
  Tokens* answer = new Tokens();
  if (student_file == "") {
    answer->setMessage("ERROR: This should be non empty");
    std::cout << "in error if empty -- student file empty" << std::endl;
    answer->setGrade(0);
  }
  return answer;
}

TestResults* myersDiffbyLinebyWord ( const std::string & student_file, const std::string & expected_file) {
	vectorOfWords text_a = stringToWords( student_file );
	vectorOfWords text_b = stringToWords( expected_file );
	Difference* diff = ses( &text_a, &text_b, true );
	diff->type = ByLineByWord;
	return diff;
}

TestResults* myersDiffbyLineNoWhite ( const std::string & student_file, const std::string & expected_file) {
	vectorOfWords text_a = stringToWords( student_file );
	vectorOfWords text_b = stringToWords( expected_file );
	Difference* diff = ses( &text_a, &text_b, false );
	diff->type = ByLineByWord;
	return diff;
}

TestResults* myersDiffbyLine ( const std::string & student_file, const std::string & expected_file) {
	vectorOfLines text_a = stringToLines( student_file );
	vectorOfLines text_b = stringToLines( expected_file );
	Difference* diff = ses( &text_a, &text_b, false );
	diff->type = ByLineByChar;
	return diff;

}

TestResults* myersDiffbyLinebyChar ( const std::string & student_file, const std::string & expected_file) {
	vectorOfLines text_a = stringToLines( student_file );
	vectorOfLines text_b = stringToLines( expected_file );
	Difference* diff = ses( &text_a, &text_b, true );
	diff->type = ByLineByChar;
	return diff;

}

TestResults* myersDiffbyLinebyCharExtraStudentOutputOk ( const std::string & student_file, const std::string & expected_file) {
	vectorOfLines text_a = stringToLines( student_file );
	vectorOfLines text_b = stringToLines( expected_file );

	bool extraStudentOutputOk = true;
	Difference* diff = ses( &text_a, &text_b, true, extraStudentOutputOk );
	diff->type = ByLineByChar;
	return diff;

}

// Runs all the ses functions
template<class T> Difference* ses ( T* a, T* b, bool secondary, bool extraStudentOutputOk  ) {
  metaData< T > meta_diff = sesSnapshots( ( T* ) a, ( T* ) b, extraStudentOutputOk );
  sesSnakes( meta_diff,  extraStudentOutputOk  );
	Difference* diff = sesChanges( meta_diff, extraStudentOutputOk );
	if ( secondary ) {
	  sesSecondary( diff, meta_diff, extraStudentOutputOk );
	}
	return diff;
}

// runs shortest edit script. Saves traces in snapshots,
// the edit distance in distance and pointers to objects a and b
template<class T> metaData< T > sesSnapshots ( T* a, T* b, bool extraStudentOutputOk ) {
	//takes 2 strings or vectors of values and finds the shortest edit script
	//to convert a into b
	int a_size = ( int ) a->size();
	int b_size = ( int ) b->size();
	metaData< T > text_diff;
	if ( a_size == 0 && b_size == 0 ) {
		return text_diff;
	}
	text_diff.m = b_size;
	text_diff.n = a_size;
    // DISTANCE -1 MEANS WHAT?
    text_diff.distance = -1;
    text_diff.a = a;
    text_diff.b = b;

	// WHAT IS V?
	//std::vector< int > v( ( a_size + b_size ) * 2, 0 );
	// TODO: BOUNDS ERROR, is this the appropriate fix?
	std::vector< int > v( ( a_size + b_size ) * 2 + 1, 0 );


	// INITIALIZATION REDUNDANT, ALREADY DONE BY CONSTRUCTOR ABOVE
	/*
	for ( int i = 0; i < ( a_size + b_size ) + ( a_size + b_size ); i++ ) {
		v[i] = 0;
	}
	*/

	//loop until the correct diff (d) value is reached, or until end is reached
	for ( int d = 0; d <= ( a_size + b_size ); d++ ) {
		// find all the possibile k lines represented by  y = x-k from the max
		// negative diff value to the max positive diff value
		// represents the possibilities for additions and deletions at diffrent
		// points in the file
		for ( int k = -d; k <= d; k += 2 ) {
			//which is the farthest path reached in the previous iteration?
		  bool down = ( k == -d
				|| ( k != d && v[ ( k - 1 ) + ( a_size + b_size )]
				     < v[ ( k + 1 ) + ( a_size + b_size )] ) );
			int k_prev, a_start, b_start, a_end, b_end;
			if ( down ) {
				k_prev = k + 1;
				a_start = v[k_prev + ( a_size + b_size )];
				a_end = a_start;
			} else {
				k_prev = k - 1;
				a_start = v[k_prev + ( a_size + b_size )];
				a_end = a_start + 1;
			}

			b_start = a_start - k_prev;
			b_end = a_end - k;
			// follow diagonal
			int snake = 0;
			while ( a_end < a_size && b_end < b_size && ( *a )[a_end] == ( *b )[b_end] ) {
				a_end++;
				b_end++;
				snake++;
			}

			// save end point
			if (k+(a_size+b_size) < 0 || k+(a_size+b_size) >= v.size()) {
			  std::cerr << "ERROR VALUE " << k+(a_size+b_size) << " OUT OF RANGE " << v.size() << " k=" << k << " a_size=" << a_size << " b_size=" << b_size << std::endl;
			}
			v[k + ( a_size + b_size )] = a_end;
			// check for solution
			if ( a_end >= a_size && b_end >= b_size ) { /* solution has been found */
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
	//return text_diff;
}

// takes a metaData object with snapshots and parses to find the "snake"
// - a path that leads from the start to the end of both of a and b
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
	diff->distance = ( meta_diff.distance - added ) / 2;
	diff->distance += added;

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



	// ===================================================
	// PREPARE GRADE
	if (diff->extraStudentOutputOk) {
	  // only missing lines (deletions) are a problem

	  // go through the changes,
	  int count_of_missing_lines = 0;
	  for (int x = 0; x < diff->changes.size(); x++) {
	    // std::cout << "CHANGE " << x << "\n" << diff->changes[x] << std::endl;
	    int num_b_lines = diff->changes[x].b_changes.size();
	    if (num_b_lines > 0) {
	      //std::cout << "*************************\n";
	      count_of_missing_lines += num_b_lines;
	    }
	  }
	  int output_length = diff->output_length_b;

	  //std::cout << "COMPARE " << output_length << " " << count_of_missing_lines << std::endl;
	  

	  assert (count_of_missing_lines <= output_length);
	  float grade = 1.0;
	  assert (output_length > 0);
	  if (output_length > 0) {
	    //std::cout << "SES [ESOO] calculating grade " << diff->distance << "/" << output_length << std::endl;
	    //grade -= (diff->distance / (float) output_length );
	    grade -= count_of_missing_lines / float(output_length);
	    //std::cout << "SES [ESOO] calculated grade = " << std::setprecision(1) << std::fixed << std::setw(5) << grade << " " << std::setw(5) << (int)floor(5*grade) << std::endl;
	  }
	  diff->setGrade(grade);
	} else {
	  // both missing lines (deletions) and extra lines are a deduction
	  int max_output_length = std::max(diff->output_length_a, diff->output_length_b);
	  float grade = 1.0;
	  if (max_output_length == 0) {
	    grade = 0;
	  } else {
	    //std::cout << "SES  calculating grade " << diff->distance << "/" << max_output_length << std::endl;
	    grade -= (diff->distance / (float) max_output_length );
	    //std::cout << "SES calculated grade = " << grade << std::endl;
	  }
	  diff->setGrade(grade);
	}
	// ===================================================

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
