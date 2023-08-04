#include <stdlib.h>

int main() {
    void* this_is_malloc[1] = { malloc(1) };
    // and this is a comment containing malloc(), calloc() and alloca()
    free (this_is_malloc[0]);

    return 0;
}
