
/* lexcount1 - ignore C comments, count all lines with non-whitespace. */
/* Read from stdin */
/* Basically, this is enough machinery to count the physical SLOC for
   a single file using C comments, e.g., lex. */
/*
This is part of SLOCCount, a toolsuite that counts source lines of code (SLOC).
Copyright (C) 2001-2004 David A. Wheeler.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

To contact David A. Wheeler, see his website at:
 http://www.dwheeler.com.

*/

#include <stdio.h>
#include <ctype.h>

int peek() {
 int c = getchar();
 ungetc(c, stdin);
 return c;
}

int main() {
 int c;
 int incomment = 0;
 long sloc = 0;
 int nonspace = 0;

 while ( (c = getchar()) != EOF) {
    if (!incomment) {
      if ((c == '/') && (peek() == '*')) {incomment=1;}
      else if (!isspace(c)) {nonspace = 1;}
    } else {
      if ((c == '*') && (peek() == '/')) {
           c= getchar(); c=getchar(); incomment=0;
      }
    }
    if ((c == '\n') && nonspace) {sloc++;}
 }
 printf("%ld\n", sloc);
 return 0; /* Report success. */
}

