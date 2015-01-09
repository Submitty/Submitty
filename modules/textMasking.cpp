#include "textMasking.h"
#include "tokenSearch.h"

std::vector< std::string > includelines ( const std::string &token,
		const std::vector< std::string >& text, bool allMatch ) {
	std::vector< std::string > output;
	// One token
	if ( token.find( '\n' ) == std::string::npos ) {
		//change to use searchToken to find token
		for ( int a = 0; a < text.size(); a++ ) {
			if ( text[a].find( token ) != std::string::npos ) {
				output.push_back( text[a] );
			}
		}
	} else { //Multiple tokens
		std::vector< std::string > multTokens = splitTokens( token );
		// Match all the tokens
		if ( allMatch ) {
			for ( int a = 0; a < text.size(); a++ ) {
				bool match = true;
				for ( int b = 0; b < multTokens.size(); b++ ) {
					//change to use searchMultipleTokens to find token
					if ( text[a].find( multTokens[b] ) == std::string::npos ) {
						match = false;
						break;
					}
				}
				if ( match ) {
					output.push_back( text[a] );
				}
			}
		}
		//match any of the tokens
		else {
			for ( int a = 0; a < text.size(); a++ ) {
				for ( int b = 0; b < multTokens.size(); b++ ) {
					//change to use searchMultipleTokens to find token
					if ( text[a].find( multTokens[b] ) != std::string::npos ) {
						output.push_back( text[a] );
						break;
					}
				}
			}
		}
	}
	return output;
}

// Returns the lines specified (in the order of the lines vector)
std::vector< std::string > includelines (
		const std::vector< unsigned int > &lines,
		const std::vector< std::string >&text ) {
	std::vector< std::string > output;
	for ( int a = 0; a < lines.size(); a++ ) {
		if ( lines[a] > text.size() ) {
			continue;
		}
		output.push_back( text[lines[a]] );
	}
	return output;
}
//returns only the lines that do not contain any token (or all of the tokens
// if allMatch is true (defaults to false))
std::vector< std::string > excludelines ( const std::string &token,
		const std::vector< std::string >& text, bool allMatch ) {

	std::vector< std::string > output;
	//one token
	if ( token.find( '\n' ) == std::string::npos ) {
		//change to use searchToken to find token
		for ( int a = 0; a < text.size(); a++ ) {
			if ( text[a].find( token ) == std::string::npos ) {
				output.push_back( text[a] );
			}
		}
	} else {
		//multiple tokens
		std::vector< std::string > multTokens = splitTokens( token );
		// Match all of the tokens
		if ( allMatch ) {
			for ( int a = 0; a < text.size(); a++ ) {
				bool match = true;
				for ( int b = 0; b < multTokens.size(); b++ ) {
					//change to use searchMultipleTokens to find token
					if ( text[a].find( multTokens[b] ) == std::string::npos ) {
						match = false;
						break;
					}
				}
				if ( !match ) {
					output.push_back( text[a] );
				}
			}
		} else {
			//Match any of the tokens
			for ( int a = 0; a < text.size(); a++ ) {
				bool match = true;
				for ( int b = 0; b < multTokens.size(); b++ ) {
					//change to use searchMultipleTokens to find token
					if ( text[a].find( multTokens[b] ) != std::string::npos ) {
						match = false;
						break;
					}
				}
				if ( match ) {
					output.push_back( text[a] );
				}
			}
		}
	}
	return output;
}
// Returns the input, minus the lines specified in the lines vector
std::vector< std::string > excludelines (
		const std::vector< unsigned int > &lines,
		const std::vector< std::string >&text ) {
	std::vector< std::string > output = text;
	for ( int a = 0; a < lines.size(); a++ ) {
		if ( lines[a] > text.size() ) {
			continue;
		}

		output.erase( output.begin() + lines[a] );
	}
	return output;
}

// Returns only the lines that are between begin and end, (including begin and
// ending the element before end)
std::vector< std::string > linesBetween ( unsigned int begin, unsigned int end,
		const std::vector< std::string >&text ) {
	if ( end > text.size() ) {
		end = ( unsigned int ) ( text.size() );
	}
	if ( begin > text.size() ) {
		begin = ( unsigned int ) ( text.size() );
	}
	std::vector< std::string > output;

	for ( int a = begin; a < end; a++ ) {
		output.push_back( text[a] );
	}
	return output;
}

// Returns only the lines that are not between begin and end, (does not return
// begin but does return end)
std::vector< std::string > linesOutside ( unsigned int begin, unsigned int end,
		const std::vector< std::string >&text ) {
	if ( end > text.size() ) {
		end = ( unsigned int ) ( text.size() );
	}
	if ( begin > text.size() ) {
		begin = ( unsigned int ) ( text.size() );
	}
	std::vector< std::string > output;
	for ( int a = 0; a < begin; a++ ) {
		output.push_back( text[a] );
	}
	for ( int a = end; a < text.size(); a++ ) {
		output.push_back( text[a] );
	}
	return output;

}
