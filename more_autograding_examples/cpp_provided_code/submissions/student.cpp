#include "student.h"

Foo::Foo(int x) {
  data = x;
}

int Foo::value() const {
  return data * 10;
}
