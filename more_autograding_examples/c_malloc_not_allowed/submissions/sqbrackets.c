#include <stdlib.h>

int main() {
    void* this_is_calloc[1] = { calloc(1, 1) };
    // and this is a comment containing malloc(), calloc() and alloca()
    free (this_is_calloc[0]);

    return 0;
}
