/* FILENAME: metaData.h
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


#ifndef differences_metaData_h
#define differences_metaData_h

#include <string>
#include <vector>
template<class T> class metaData {
	public:
		metaData ();
		std::vector< std::vector< int > > snakes;
		std::vector< std::vector< int > > snapshots;
		T const *a;
		T const *b;
		int m;
		int n;
		int distance;
};
template<class T> metaData< T >::metaData () :
		a( NULL ), b( NULL ), m( 0 ), n( 0 ), distance( 0 ) {
}

#endif
