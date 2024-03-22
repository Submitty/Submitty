%{

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

#include "driver.h"

#define YY_NO_UNPUT

/* 1 if we saw a non-comment, non-whitespace char on this line */
int saw_char = 0;

%}

%option noyywrap

SPACE		[ \t\n\r\f]

%x comment
%x bcomment
%x string

%%
	line_number = 1;
	saw_char = 0;
	BEGIN(INITIAL);

[ \t\r\f]	/* Do nothing */
"(*"	{BEGIN(comment);}
\n	{if (saw_char) {sloc++; saw_char=0;}; line_number++;}
"{"	{BEGIN(bcomment);}
"'"	{saw_char = 1; BEGIN(string);}
[^ \t\r\f(\n{'][^(\n{']*	{saw_char = 1;}
.	{saw_char = 1;}

<comment>[^*\n]+	/* Do nothing */
<comment>[^*\n]*\n	{if (saw_char) {sloc++; saw_char=0;}; line_number++;}
<comment>"*"+[^*)\n]*	/* Do nothing */
<comment>"*"+[^*)\n]*\n {if (saw_char) {sloc++; saw_char=0;}; line_number++;}
<comment>"*"+")"	{BEGIN(INITIAL);}

<bcomment>[^}\n]+	/* Do nothing */
<bcomment>[^}\n]*\n	{if (saw_char) {sloc++; saw_char=0;}; line_number++;}
<bcomment>"}"		{BEGIN(INITIAL);}

<string>[^'\n]+		{saw_char = 1;}
<string>\n		{
	fprintf(stderr, "Warning: newline in string - file %s, line %ld\n",
	                 filename, line_number);
	if (saw_char) {sloc++; saw_char=0;};
	BEGIN(INITIAL); /* Switch back; this at least limits damage */
	line_number++;
	}
<string>''		{saw_char = 1;}
<string>'		{saw_char = 1; BEGIN(INITIAL);}

%%

#include "driver.c"


