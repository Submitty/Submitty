
#ifndef DTL_TEST_COMMON
#define DTL_TEST_COMMON

#include <gtest/gtest.h>
#include <cstdio>
#include <string>
#include <vector>
#include <utility>
#include <iostream>
#include <fstream>
#include <dtl/dtl.hpp>

using std::cerr;
using std::endl;
using std::string;
using std::vector;
using std::pair;
using std::ifstream;
using std::ofstream;

using dtl::Diff;
using dtl::Diff3;
using dtl::Compare;
using dtl::SES_COMMON;
using dtl::SES_ADD;
using dtl::SES_DELETE;
using dtl::elemInfo;
using dtl::uniHunk;

#define dtl_test_typedefs(e_type, seq_type)                         \
    typedef e_type                       elem;                      \
    typedef seq_type                     sequence;                  \
    typedef pair< elem, elemInfo >       sesElem;                   \
    typedef vector< elem >               elemVec;                   \
    typedef vector< sesElem >            sesElemVec;                \
    typedef vector< uniHunk< sesElem > > uniHunkVec;

enum type_diff { TYPE_DIFF_SES, TYPE_DIFF_UNI };

string create_path (const string& test_name, string diff_name, enum type_diff t, bool is_use_suffix = false);
size_t cal_diff_uni (const string& path_l, const string& path_r);
bool is_file_exist (string& fs);
void diff_resultset_exist_check (string &fs);

template <typename T>
class Remover {
public :
    void operator()(const T& v){
        remove(v.path_rses.c_str());
        remove(v.path_rhunks.c_str());
    }
};

template < typename elem, typename sequence,  typename comparator >
void create_file (const string& path, Diff< elem, sequence, comparator >& diff, enum type_diff t) {
    ofstream ofs;
    ofs.open(path.c_str());
    switch (t) {
    case TYPE_DIFF_SES:
        diff.printSES(ofs);
        break;
    case TYPE_DIFF_UNI:
        diff.printUnifiedFormat(ofs);
        break;
    }
    ofs.close();
}

#endif // DTL_TEST_COMMON
