#include <stdlib.h>
#define THIS_IS_NOT_MEMORY_ALLOCATION calloc

int main() {
    void* this_is_calloc = THIS_IS_NOT_MEMORY_ALLOCATION (1, 1);
    // and this is a comment containing malloc(), calloc() and alloca()
    free (this_is_calloc);

    return 0;
}
