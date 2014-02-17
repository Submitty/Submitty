
#include <dtl/dtl.hpp>
#include "common.hpp"
#include <iostream>
#include <fstream>
#include <sstream>
#include <vector>
#include <string>

using namespace std;
using namespace dtl;

int main(int argc, char *argv[]){
    
    if (isFewArgs(argc, 2)) {
        cerr << "Too few arguments." << endl;
        return -1;
    }

    typedef string elem;
    typedef vector< string > sequence;

    string s(argv[1]);

    if (!isFileExist(s)) {
        cerr << s << " is invalid." << endl;
        return -1;
    }

    ifstream fs(s.c_str());
    const Ses< elem > ses = Diff< elem, sequence >::composeSesFromStream< ifstream >(fs);
    dtl::Diff< elem, sequence >::printSES(ses);
    
    return 0;
}
