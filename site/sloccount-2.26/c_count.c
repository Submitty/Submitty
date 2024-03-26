/* c_count: given a list of C/C++/Java files on the command line,
   count the SLOC in each one.  SLOC = physical, non-comment lines.
   This program knows about C++ and C comments (and how they interact),
   and correctly ignores comment markers inside strings.

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

   Usage: Use in one of the following ways:
     c_count                      # As filter
     c_count [-f file] [list_of_files]
       file: file with a list of files to count (if "-", read list from stdin)
       list_of_files: list of files to count
*/

#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <stdlib.h>

/* Modes */
#define NORMAL 0
#define INSTRING 1
#define INCOMMENT 2

/* Types of comments: */
#define ANSIC_STYLE 0
#define CPP_STYLE 1

/* Not all C compilers support a boolean type, so for portability's sake,
   we'll fake it. */
#define BOOLEAN int
#define TRUE 1
#define FALSE 0


/* Globals */
long total_sloc;

static BOOLEAN warn_embedded_newlines = FALSE;

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
 static BOOLEAN last_char_was_newline = 0;
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
 int mode = NORMAL;              /* NORMAL, INSTRING, or INCOMMENT */
 int comment_type = ANSIC_STYLE; /* ANSIC_STYLE or CPP_STYLE */
 

 /* The following implements a state machine with transitions; the
    main state is "mode" and "comment_type", the transitions are
    triggered by characters input. */

 while ( (c = getachar(stream)) != EOF) {
   if      (mode == NORMAL) {
     if (c == '"') {sawchar=1; mode = INSTRING;}
     else if (c == '\'') {  /* Consume single-character 'xxxx' values */
       sawchar=1;
       c = getachar(stream);
       if (c == '\\') c = getachar(stream);
       do {
         c = getachar(stream);
       } while ((c != '\'') && (c != '\n') & (c != EOF));
     } else if ((c == '/') && ispeek('*', stream)) {
          c = getachar(stream);
          mode = INCOMMENT;
          comment_type = ANSIC_STYLE;
     } else if ((c == '/') && ispeek('/', stream)) {
          c = getachar(stream);
          mode = INCOMMENT;
          comment_type = CPP_STYLE;
     } else if (!isspace(c)) {sawchar = 1;}
   } else if (mode == INSTRING) {
     /* We only count string lines with non-whitespace -- this is to
        gracefully handle syntactically invalid programs.
        You could argue that multiline strings with whitespace are
        still executable and should be counted. */
     if (!isspace(c)) sawchar = 1;
     if (c == '"') {mode = NORMAL;}
     else if ((c == '\\') && (ispeek('\"', stream) || ispeek('\\', stream))) {c = getachar(stream);}
     else if ((c == '\\') && ispeek('\n', stream)) {c = getachar(stream);}
     else if ((c == '\n') && warn_embedded_newlines) {
       /* We found a bare newline in a string without preceding backslash. */
       fprintf(stderr, "c_count WARNING - newline in string, line %ld, file %s\n", line_number, filename);
       /* We COULD warn & reset mode to "Normal", but lots of code does this,
          so we'll just depend on the warning for ending the program
          in a string to catch syntactically erroneous programs. */
     }
   } else {  /* INCOMMENT mode */
     if ((c == '\n') && (comment_type == CPP_STYLE)) { mode = NORMAL;}
     if ((comment_type == ANSIC_STYLE) && (c == '*') &&
          ispeek('/', stream)) { c= getachar(stream); mode = NORMAL;}
   }
   if (c == '\n') {
     if (sawchar) sloc++;
     sawchar = 0;
   }
 }
 /* We're done with the file.  Handle EOF-without-EOL. */
 if (sawchar) sloc++;
 sawchar = 0;
 if ((mode == INCOMMENT) && (comment_type == CPP_STYLE)) { mode = NORMAL;}

 if (mode == INCOMMENT) {
     fprintf(stderr, "c_count ERROR - terminated in comment in %s\n", filename);
 } else if (mode == INSTRING) {
     fprintf(stderr, "c_count ERROR - terminated in string in %s\n", filename);
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

