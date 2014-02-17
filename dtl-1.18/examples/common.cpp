
#include "common.hpp"

bool isFileExist (string& fs) {
    FILE *fp;
    if ((fp = fopen(fs.c_str(), "r")) == NULL) {
        return false;
    }
    fclose(fp);
    return true;
}

bool isFewArgs (int argc, int limit) {
    bool ret = false;
    if (argc < limit) {
        ret = true;
    }
    return ret;
}

