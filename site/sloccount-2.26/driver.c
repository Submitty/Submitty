/* driver: given a list of files on the command line,
   count the SLOC in each one.

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

/* This is only included so that I can do some kinds of analysis
 * separately on this file; normally this file is itself included: */
#include "driver.h"



void sloc_count(char *current_filename, FILE *stream) {
 /* Count the sloc in the one file named "current_filename" in "stream",
  * and add it to the total_sloc. */

 filename = current_filename;
 sloc = 0;
 line_number = 1;
 yyin = stream;

 yylex();

 total_sloc += sloc;
}


void count_file(char *current_filename) {
  FILE *stream;

  stream = fopen(current_filename, "r");
  if (!stream) {
    sloc = 0;
    fprintf(stderr, "Error: Cannot open %s\n", current_filename);
    return;
  }
  sloc_count(current_filename, stream);
  printf("%ld %s\n", sloc, current_filename);
  fclose(stream);
}

char *read_a_line(FILE *file) {
 /* Read a line in, and return a malloc'ed buffer with the line contents.
    Any newline at the end is stripped.
    If there's nothing left to read, returns NULL. */

 /* We'll create a monstrously long buffer to make life easy for us: */
 char buffer[10000];
 char *returnval;
 char *newlinepos;

 returnval = fgets(buffer, sizeof(buffer), file);
 if (returnval) {
   newlinepos = buffer + strlen(buffer) - 1;
   if (*newlinepos == '\n') {*newlinepos = '\0';};
   return strdup(buffer);
 } else {
   return NULL;
 }
}


int main(int argc, char *argv[]) {
 int i;
 char *s;
 FILE *file_list = NULL;

 total_sloc = 0;

 if (argc <= 1) {
   sloc_count("-", stdin);
   printf("%ld %s\n", sloc, "-");
 } else if ((argc == 3) && (!strcmp(argv[1], "-f"))) {
   if (!strcmp (argv[2], "-")) {
     file_list = stdin;
   } else {
     file_list = fopen(argv[2], "r");
   }
   if (file_list) {
     while ((s = read_a_line(file_list))) {
       count_file(s);
       free(s);
     }
   }
 } else {
   for (i=1; i < argc; i++) { count_file(argv[i]); }
 }
 printf("Total:\n");
 printf("%ld\n", total_sloc);
 return 0; /* Report success */
}
