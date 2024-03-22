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

#ifndef DRIVER_H
#define DRIVER_H

#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <stdlib.h>

/* Not all C compilers support a boolean type, so for portability's sake,
   we'll fake it. */
#define BOOLEAN int
#define TRUE 1
#define FALSE 0


/* Globals */
unsigned long sloc;           /* For current file */
unsigned long line_number;    /* Of current file */
char *filename;               /* Name of current file */

unsigned long total_sloc;     /* For all files seen */



#endif
