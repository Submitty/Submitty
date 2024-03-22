/* php_count: given a list of C/C++/Java files on the command line,
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
     php_count                      # As filter
     php_count list_of_files        # Counts for each file.
     php_count -f fl                # Counts the files listed in "fl".

*/

#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <stdlib.h>


/* If ALLOW_SHORT_TAGS is true, then <? all by itself begins PHP code. */
#define ALLOW_SHORT_TAGS 1

/* If ALLOW_ASP_TAGS is true, then <% begins PHP code. */
#define ALLOW_ASP_TAGS 1



/* Modes: PHP starts in "NONE", and <?php etc change mode to "NORMAL". */
enum mode_t { NONE, NORMAL, INSTRING, INCOMMENT, INSINGLESTRING, HEREDOC };

enum comment_t {ANSIC_STYLE, CPP_STYLE, SH_STYLE}; /* Types of comments */
enum end_t {NORMAL_END, SCRIPT_END, ASP_END}; /* Type of ending to expect. */


/* Globals */
long total_sloc;

long line_number;

/* Handle input */

/* Number of characters in one line, maximum. */
/* The code uses fgets() so that longer lines are truncated & not a
   buffer overflow hazard. */
#define LONGEST_LINE 20000

static char current_line[LONGEST_LINE];
static char *clocation; /* points into current_line */
static long sloc = 0;
static int sawchar = 0; /* Did you see a character on this line? */
static int beginning_of_line = 0;
static int is_input_eof;

void read_input_line(FILE *stream) {
 /* Read in a new line - increment sloc if sawchar, & reset sawchar. */
 if (feof(stream)) {
   is_input_eof = 1;
   return;
 }
 line_number++;
 fgets(current_line, sizeof(current_line)-2, stream);
 clocation = &(current_line[0]);
 beginning_of_line = 1;
 if (current_line[0] == '\0') is_input_eof = 1;
 if (sawchar) {
   /* printf("DEBUG: INCREMENTING SLOC\n"); */
   sawchar = 0;
   sloc++;
 }
}

void init_input(FILE *stream) {
 current_line[0] = '\0';
 is_input_eof = 0;
 sawchar = 0;
 read_input_line(stream);
}

void consume_char(FILE *stream) {
 /* returns TRUE if there are more characters in the input. */
 beginning_of_line = 0;
 if (!*clocation) read_input_line(stream);
 else             clocation++;
}

int match_consume(const char *m, FILE *stream) {
 /* returns TRUE & most forward if matches, and consumes */
 if (!*clocation) read_input_line(stream);
 if (strncasecmp(m, clocation, strlen(m)) == 0) {
   /* printf("MATCH: %s, %s\n", m, clocation); */
   clocation += strlen(m);
   beginning_of_line = 0;
   return 1;
 } else {
   return 0;
 }
}

int current_char(FILE *stream) {
 if (!*clocation) read_input_line(stream);
 return *clocation;
}

char *rest_of_line(FILE *stream) {
 /* returns rest of the line in a malloc'ed entry (caller must free()),
    consuming it. */
 char *result;

 result = strdup(clocation);
 read_input_line(stream);
 return result;
}


void strstrip(char *s) {
 /* Strip whitespace off the end of s. */
 char *p;
 
 /* Remove whitespace from the end by walking backwards. */
 for (p= s + strlen(s) - 1; p >= s && isspace(*p); p--) {
   *p = '\0';
 }
 return;
}


long sloc_count(char *filename, FILE *stream) {
 /* Count the sloc in the program in stdin. */

 enum mode_t mode = NONE;   /* State machine state - NORMAL == PHP code */
 enum comment_t comment_type;   /* ANSIC_STYLE, CPP_STYLE, SH_STYLE */
 enum end_t expected_end;   /* The kind of ending expected, e.g. ?> */

 char *heredoc_end;

 sloc = 0;
 

 /* The following implements a state machine with transitions; the
    main state is "mode"; the transitions are triggered by character input. */

 while (!is_input_eof) {
    /* printf("mode=%d, current_char=%c\n", mode, current_char()); */
    if (mode == NONE) {
       /* Note: PHP will raise errors if something starts with
          <?php and isn't followed by whitespace, e.g., <?phphello
          is illegal.  We won't look for this case, under the assumption
          that someone won't bother to count malformed code.  It's just
          as well, anyway - it's few would think of doing it!
          Note that simple <? followed by arbitrary characters is okay,
          and is handled by the <? processing, so <?echo("hello")?> works. */
       if (match_consume("<?php", stream)) {
               expected_end = NORMAL_END;
               mode = NORMAL;
       } else if (ALLOW_SHORT_TAGS && match_consume("<?", stream)) {
               expected_end = NORMAL_END;
               mode = NORMAL;
       /* FIXME: <script...> should be more flexible, allowing for
          other attributes etc. I haven't seen this as a real problem. */
       } else if (match_consume("<script language=\"php\">", stream)) {
               expected_end = SCRIPT_END;
               mode = NORMAL;
       } else if (ALLOW_ASP_TAGS && match_consume("<%", stream)) {
               expected_end = ASP_END;
               mode = NORMAL;
       } else consume_char(stream);
    } else if (mode == NORMAL) {
       if ((expected_end==NORMAL_END) && match_consume("?>", stream)) {
           mode = NONE;
       } else if ((expected_end==ASP_END) && match_consume("%>", stream)) {
           mode = NONE;
       } else if ((expected_end==SCRIPT_END) && match_consume("</script>", stream)) {
           mode = NONE;
       } else if (match_consume("\"", stream)) {
           sawchar = 1;
           mode = INSTRING;
       } else if (match_consume("\'", stream)) {
           sawchar = 1;
           mode = INSINGLESTRING;
       } else if (match_consume("/*", stream)) {
          mode = INCOMMENT;
          comment_type = ANSIC_STYLE;
       } else if (match_consume("//", stream)) {
          mode = INCOMMENT;
          comment_type = CPP_STYLE;
       } else if (match_consume("#", stream)) {
          mode = INCOMMENT;
          comment_type = SH_STYLE;
       } else if (match_consume("<<<", stream)) {
          mode = HEREDOC;
          while (isspace(current_char(stream)) && !is_input_eof) {consume_char(stream);}
          heredoc_end = rest_of_line(stream);
          strstrip(heredoc_end);
       } else {
         if (!isspace(current_char(stream))) sawchar = 1;
         consume_char(stream);
       }
    } else if (mode == INSTRING) {
      /* We only count string lines with non-whitespace -- this is to
         gracefully handle syntactically invalid programs.
         You could argue that multiline strings with whitespace are
         still executable and should be counted. */
      if (!isspace(current_char(stream))) sawchar = 1;
      if (match_consume("\"", stream)) {mode = NORMAL;}
      else if (match_consume("\\\"", stream) || match_consume("\\\\", stream) ||
               match_consume("\\\'", stream)) {}
      else consume_char(stream);
    } else if (mode == INSINGLESTRING) {
      /* We only count string lines with non-whitespace; see above. */
      if (!isspace(current_char(stream))) sawchar = 1;
      if (current_char(stream) == '\'') {}
      if (match_consume("'", stream)) {mode = NORMAL; }
      else if (match_consume("\\\\", stream) || match_consume("\\\'", stream)) { }
      else { consume_char(stream); }
    } else if (mode == INCOMMENT) {
      if ((comment_type == ANSIC_STYLE) && match_consume("*/", stream)) {
          mode = NORMAL; }
      /* Note: in PHP, must accept ending markers, even in a comment: */
      else if ((expected_end==NORMAL_END) && match_consume("?>", stream))
          { mode = NONE; }
      else if ((expected_end==ASP_END) && match_consume("%>", stream)) { mode = NONE; }
      else if ((expected_end==SCRIPT_END) && match_consume("</script>", stream))
                                     { mode = NONE; }
      else if ( ((comment_type == CPP_STYLE) || (comment_type == SH_STYLE)) &&
           match_consume("\n", stream)) { mode = NORMAL; }
      else consume_char(stream);
    } else if (mode == HEREDOC) {
      if (!isspace(current_char(stream))) sawchar = 1;
      if (beginning_of_line && match_consume(heredoc_end, stream)) {
        mode=NORMAL;
      } else {
        consume_char(stream);
      }
    } else {
       fprintf(stderr, "Warning! Unknown mode in PHP file %s, mode=%d\n",
               filename, mode);
       consume_char(stream);
    }
 }
 if (mode != NONE) {
   fprintf(stderr, "Warning! Unclosed PHP file %s, mode=%d\n", filename, mode);
 }

 return sloc;
}


void count_file(char *filename) {
  long sloc;
  FILE *stream;

  stream = fopen(filename, "r");
  line_number = 0;
  init_input(stream);
  sloc = sloc_count(filename, stream);
  fclose (stream);
  total_sloc += sloc;
  printf("%ld %s\n", sloc, filename);
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
 line_number = 0;

 if (argc <= 1) {
   init_input(stdin);
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
 exit(0);
}

