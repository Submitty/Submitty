
#include <iostream>
#include <vector>
#include <dtl/dtl.hpp>

using namespace std;

using dtl::Diff;

int main(int, char**){
    
    int a[] = {1, 2, 3, 4, 5, 6, 7, 8, 9, 10};
    int b[] = {3, 5, 1, 4, 5, 1, 7, 9, 6, 10};
    int asiz = sizeof(a) / sizeof(int);
    int bsiz = sizeof(b) / sizeof(int);
    for (int i=0;i<asiz;++i) {
        cout << a[i] << " ";
    }
    cout << endl;
    for (int i=0;i<bsiz;++i) {
        cout << b[i] << " ";
    }
    cout << endl;
    
    typedef  int elem;
    typedef  vector< int > sequence;

    sequence A(&a[0], &a[asiz]);
    sequence B(&b[0], &b[bsiz]);
    Diff< elem > d(A, B);
    d.compose();

    // editDistance
    cout << "editDistance:" << d.getEditDistance() << endl;

    // Longest Common Subsequence
    sequence lcs_v = d.getLcsVec();
    cout << "LCS: ";
    for (sequence::iterator vit=lcs_v.begin();vit!=lcs_v.end();++vit) {
        cout << *vit << " ";
    }
    cout << endl;
    
    // Shortest Edit Script
    cout << "SES" << endl;
    d.printSES();
    
    return 0;
}
