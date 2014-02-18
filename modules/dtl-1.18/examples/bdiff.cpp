
#include <dtl/dtl.hpp>
#include "common.hpp"

#include <iostream>
#include <vector>
#include <string>
#include <cstdio>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

using namespace std;

using dtl::Diff;

typedef unsigned char  elem;
typedef vector< elem > sequence;

static int create_byte_seq(const char *fs, sequence& seq);
static int create_byte_seq(const char *fs, sequence& seq)
{
    int  fd;
    int  siz;
    elem buf[BUFSIZ];
    if ((fd = open(fs, O_RDONLY)) == -1) {
        cout << "Opening failed." << endl;
        return -1;
    }
    while ((siz = read(fd, buf, sizeof(buf))) > 0) {
        for (int i=0;i<siz;++i) {
            seq.push_back(buf[i]);
        }
    }
    if (siz < 0) {
        close(fd);
        cout << "Read error." << endl;
        return -1;
    }
    close(fd);
    return 0;
}

int main(int argc, char *argv[])
{
    
    if (isFewArgs(argc)) {
        cerr << "Too few arguments." << endl;
        return -1;
    }
    
    string   fs1(argv[1]);
    string   fs2(argv[2]);
    sequence seq1;
    sequence seq2;
    
    create_byte_seq(fs1.c_str(), seq1);
    create_byte_seq(fs2.c_str(), seq2);
    
    Diff< elem, sequence > d(seq1, seq2);
    d.compose();

    if (d.getEditDistance() == 0) {
        cout << fs1 << " is the same as "    << fs2 << endl;
    } else {
        cout << fs1 << " is different from " << fs2 << endl;
    }
    
    return 0;
}
