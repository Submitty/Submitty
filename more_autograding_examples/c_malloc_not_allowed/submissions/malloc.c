#include <stdlib.h>
#define THIS_IS_NOT_MEMORY_ALLOCATION malloc

int main() {
    void* this_is_malloc = THIS_IS_NOT_MEMORY_ALLOCATION (1);
    // and this is a comment containing malloc(), calloc() and alloca()
    free(this_is_malloc);

    return 0;
}
