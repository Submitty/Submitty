#include <cstdlib>
#include <iostream>

#include "student.h"

int main() {
  Foo a(1);
  std::cout << std::getenv("FOO_1_PRINT") << a.value() << std::endl;
  Foo b(2);
  std::cout << "Foo(2).value() = " << b.value() << std::endl;
}
