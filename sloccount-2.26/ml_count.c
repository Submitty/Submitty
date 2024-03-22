/* ml_count: given a list of ML files on the command line,
   count the SLOC in each one.  SLOC = physical, non-comment lines.

This is part of SLOCCount, a toolsuite that counts source lines of code (SLOC).
Copyright (C) 2001-2004 David A. Wheeler and Michal Moskal

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
Michal Moskal may be contacted at malekith at pld-linux.org.

   Based on c_count.c by:
   (C) Copyright 2000 David A. Wheeler
   Michal Moskal rewrote sloc_count() function, to support ML.

   Usage: Use in one of the following ways:
     ml_count                      # As filter
     ml_count [-f file] [list_of_files]
       file: file with a list of files to count (if "-", read list from stdin)
       list_of_files: list of files to count

   Michal Moskal states "It was easier to get string escaping and comment
   nesting right in C then in Perl. It would be even easier in OCaml... ;-)"
*/

#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <stdlib.h>

/* Globals */
long total_sloc;

int peek(FILE *stream) {
 int c = getc(stream);
 ungetc(c, stream);
 return c;
}

int ispeek(int c, FILE *stream) {
 if (c == peek(stream)) {return 1;}
 return 0;
}

long line_number;

int getachar(FILE *stream) {
/* Like getchar(), but keep track of line number. */
 static int last_char_was_newline = 0;
 int c;

 c = getc(stream); 
 if (last_char_was_newline) line_number++;
 if (c == '\n') last_char_was_newline=1;
 else           last_char_was_newline=0;
 return c;
}


long sloc_count(char *filename, FILE *stream) {
 /* Count the sloc in the program in stdin. */
 long sloc = 0;

 int sawchar = 0;                /* Did you see a character on this line? */
 int c;
 
 int comment_lev = 0;		/* Level of comment nesting. */
 int in_string = 0;		/* 0 or 1 */
 

 while ((c = getachar(stream)) != EOF) {
   switch (c) {
   case '"':
     in_string = !in_string;
     break;
     
   case '(':
     if (!in_string && ispeek('*', stream)) {
       comment_lev++;
       getachar(stream);	/* skip '*' */
     }
     break;
     
   case '*':
     if (comment_lev && !in_string && ispeek(')', stream)) {
       comment_lev--;
       getachar(stream);	/* skip ')' */
       continue /* while */;
     }
     break;
	 
   case '\\':
     /* Ignore next character if in string.  But don't ignore newlines. */
     if (in_string && !ispeek('\n', stream))
       getachar(stream);
     break;
   
   case ' ':
   case '\t':
     /* just ignore blanks */
     continue /* while */;
   
   case '\n':
     if (sawchar) {
       sloc++;
       sawchar = 0;
     }
     continue /* while */;
     
   default:
     break;
   }

   if (comment_lev == 0)
     sawchar = 1;
 }

 /* We're done with the file.  Handle EOF-without-EOL. */
 if (sawchar) sloc++;

 if (comment_lev) {
     fprintf(stderr, "ml_count ERROR - terminated in comment in %s\n", filename);
 } else if (in_string) {
     fprintf(stderr, "ml_count ERROR - terminated in string in %s\n", filename);
 }

 return sloc;
}


void count_file(char *filename) {
  long sloc;
  FILE *stream;

  stream = fopen(filename, "r");
  line_number = 1;
  sloc = sloc_count(filename, stream);
  total_sloc += sloc;
  printf("%ld %s\n", sloc, filename);
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
 long sloc;
 int i;
 FILE *file_list;
 char *s;

 total_sloc = 0;
 line_number = 1;

 if (argc <= 1) {
   sloc = sloc_count("-", stdin);
   printf("%ld %s\n", sloc, "-");
   total_sloc += sloc;
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

