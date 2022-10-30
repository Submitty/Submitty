/* FILENAME: metaData.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */


#ifndef differences_metaData_h
#define differences_metaData_h

#include <ostream>

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





// for printing snakes & snapshots
inline std::ostream& operator<<(std::ostream &ostr, const std::vector< std::vector< int > > &data) {
  for (int i = 0; i < data.size(); i++) {
    ostr << i << "  :  ";
    for (int j = 0; j < data[i].size(); j++) {
      ostr << " " << data[i][j];
    }
    ostr << std::endl;
  }
  return ostr;
}



//inline std::ostream& operator<<(std::ostream &ostr, const metaData<char> md) {
//return ostr;
//}


#endif
