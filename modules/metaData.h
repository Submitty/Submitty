//
//  metaData.h
//  differences
//
//  Created by Kiana on 3/23/14.
//  Copyright (c) 2014 Kiana. All rights reserved.
//

#ifndef differences_metaData_h
#define differences_metaData_h

#include <string>
#include <vector>
template<class T> class metaData{
public:
    metaData();
    std::vector< std::vector< int > > snakes;
    std::vector< std::vector< int > > snapshots;
    T const *a;
    T const *b;
    int m;
    int n;
    int distance;
};
template<class T> metaData<T>::metaData(): a(NULL), b(NULL), m(0), n(0), distance(0){}

#endif
