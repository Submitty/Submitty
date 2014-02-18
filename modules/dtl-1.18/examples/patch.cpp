
#include <dtl/dtl.hpp>
#include "common.hpp"
#include <iostream>
#include <vector>
#include <cassert>

using namespace std;

using dtl::Diff;

int main(int argc, char *argv[]) {
    
    if (isFewArgs(argc)) {
        cerr << "Too few arguments." << endl;
        return -1;
    }
    
    typedef char elem;
    typedef string sequence;

    sequence A(argv[1]);
    sequence B(argv[2]);
    
    Diff< elem, sequence > d(A, B);
    d.compose();
    
    sequence s1(A);
    sequence s2 = d.patch(s1);
    d.composeUnifiedHunks();
    sequence s3 = d.uniPatch(s1);
    
    cout << "before:" << s1 << endl;
    cout << "after :" << s2 << endl;
    assert(B == s2);
    cout << "patch succeeded" << endl;
    
    cout << "before:" << s1 << endl;
    cout << "after :" << s3 << endl;
    assert(B == s3);
    cout << "unipatch succeeded" << endl;
    
    return 0;
}
