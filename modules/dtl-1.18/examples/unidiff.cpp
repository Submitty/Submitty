
#include <dtl/dtl.hpp>
#include "common.hpp"
#include <iostream>
#include <fstream>
#include <sstream>
#include <vector>

#include <time.h>
#include <sys/stat.h>

using namespace std;

using dtl::Diff;
using dtl::elemInfo;
using dtl::uniHunk;

static void showStats (string fp1, string fp2);
static void unifiedDiff (string fp1, string fp2); 

static void showStats (string fp1, string fp2) 
{
    const int    MAX_LENGTH    = 255;
    char         time_format[] = "%Y-%m-%d %H:%M:%S %z";
    time_t       rawtime[2];
    struct tm   *timeinfo[2];
    struct stat  st[2];
    
    if (stat(fp1.c_str(), &st[0]) == -1) {
        cerr << "argv1 is invalid." << endl;
        exit(-1);
    }
    if (stat(fp2.c_str(), &st[1]) == -1) {
        cerr << "argv2 is invalid" << endl;
        exit(-1);
    }
    
    char buf[2][MAX_LENGTH + 1];
    rawtime[0] = st[0].st_mtime;
    timeinfo[0] = localtime(&rawtime[0]);
    strftime(buf[0], MAX_LENGTH, time_format, timeinfo[0]);
    cout << "--- " << fp1 << '\t' << buf[0] << endl;
    rawtime[1] = st[1].st_mtime;
    timeinfo[1] = localtime(&rawtime[1]);
    strftime(buf[1], MAX_LENGTH, time_format, timeinfo[1]);
    cout << "+++ " << fp2 << '\t' << buf[1] << endl;
}

static void unifiedDiff (string fp1, string fp2) 
{
    typedef string                 elem;
    typedef vector< elem >         sequence;
    typedef pair< elem, elemInfo > sesElem;

    ifstream      Aifs(fp1.c_str());
    ifstream      Bifs(fp2.c_str());
    elem          buf;
    sequence      ALines, BLines;
    
    while(getline(Aifs, buf)){
        ALines.push_back(buf);
    }
    while(getline(Bifs, buf)){
        BLines.push_back(buf);
    }
    
    Diff< elem > diff(ALines, BLines);
    diff.onHuge();
    //diff.onUnserious();
    diff.compose();
    
    // type unihunk definition test
    uniHunk< sesElem > hunk;
    
    if (diff.getEditDistance() > 0) {
        showStats(fp1, fp2);             // show file info
    }
    
    diff.composeUnifiedHunks();
    diff.printUnifiedFormat();
}


int main(int argc, char *argv[])
{
    if (isFewArgs(argc)) {
        cerr << "Too few arguments." << endl;
        return -1;
    }
    
    string s1(argv[1]);
    string s2(argv[2]);
    bool   fileExist = true;
    
    if (!isFileExist(s1)) {
        cerr << s1 << " is invalid." << endl;
        fileExist = false;
    }
    
    if (!isFileExist(s2)) {
        cerr << s2 << " is invalid." << endl;
        fileExist = false;
    }
    
    if (!fileExist) {
        return -1;
    }
    
    unifiedDiff(s1, s2);
    return 0;
}
