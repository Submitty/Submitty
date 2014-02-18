
#include <dtl/dtl.hpp>
#include <iostream>
#include <vector>
#include <cassert>

using namespace std;

using dtl::Diff3;

int main(int, char**) {
    
    int a[10]      = {1, 2, 3, 4, 5, 6, 7, 3, 9, 10};
    int b[10]      = {1, 2, 3, 4, 5, 6, 7, 8, 9, 10};
    int c[10]      = {1, 2, 3, 9, 5, 6, 7, 8, 9, 10};
    int answer[10] = {1, 2, 3, 9, 5, 6, 7, 3, 9, 10};
    
    cout << "a:";
    for (int i=0;i<10;++i) {
        cout << a[i] << " ";
    }
    cout << endl;
    cout << "b:";
    for (int i=0;i<10;++i) {
        cout << b[i] << " ";
    }
    cout << endl;
    cout << "c:";
    for (int i=0;i<10;++i) {
        cout << c[i] << " ";
    }
    cout << endl;
    
    typedef int elem;
    typedef vector< int > sequence;
    sequence A(&a[0], &a[10]);
    sequence B(&b[0], &b[10]);
    sequence C(&c[0], &c[10]);
    sequence Answer(&answer[0], &answer[10]);
    Diff3< elem > diff3(A, B, C);
    diff3.compose();
    if (!diff3.merge()) {
        cerr << "conflict." << endl;
        return -1;
    }
    sequence s = diff3.getMergedSequence();
    cout << "s:";
    for (sequence::iterator it=s.begin();it!=s.end();++it) {
        cout << *it << " ";
    }
    cout << endl;
    
    assert(s == Answer);
    cout << "intdiff3 OK" << endl;
    
    return 0;
}
