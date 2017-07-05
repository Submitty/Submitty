#include <iostream>

#include "student.h"

int main() {
  Foo a(1);
  std::cout << "Foo(1).value() = " << a.value() << std::endl;
  Foo b(2);
  std::cout << "Foo(2).value() = " << b.value() << std::endl;
}
