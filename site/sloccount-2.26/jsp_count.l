%{

/*
This is part of SLOCCount, a toolsuite that counts source lines of code (SLOC).
Copyright (C) 2001-2004 David A. Wheeler and Bob Brown.
This is a tweaked version by Bob Brown, derived from
David A. Wheeler's pascal_count.l.

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
Bob Brown's website is: http://www.openeye.com/rlb
*/

#include "driver.h"

#define YY_NO_UNPUT

/* 1 if we saw a non-comment, non-whitespace char on this line */
int saw_char = 0;
static void count(void);

%}

%option noyywrap

SPACE		[ \t\n\r\f]

%x chtml
%x cjsp
%x string

%%
	line_number = 1;
	saw_char = 0;
	BEGIN(INITIAL);

[ \t\r\f]	/* Do nothing */
"<!--"	{ BEGIN(chtml); }
"<%--"	{ BEGIN(cjsp); }
\n	{ count(); }

\"	{saw_char = 1; BEGIN(string);}

[^ \t\r\f(\n<"][^<\n"]*	{saw_char = 1;}
.	{saw_char = 1;}


<chtml>"-->"     { BEGIN(INITIAL); }
<chtml>\n	 { count(); }
<chtml>.	 /* no-op */

<cjsp>"--%>"	 { BEGIN(INITIAL); }
<cjsp>\n	 { count(); }
<cjsp>.		 /* no-op */

<string>[^\"\n]+ {saw_char = 1;}
<string>\n	 {
		 	fprintf(stderr, "Warning: newline in string - file %s, line %ld\n",
	                 filename, line_number);
			count();
			BEGIN(INITIAL); /* Switch back; this at least limits damage */
		 }
<string>\"		{ BEGIN(INITIAL);}

%%

#include "driver.c"

static void count(void)
{
    if ( saw_char ) {
        sloc++;
	saw_char = 0;
    }
    line_number++;
}
