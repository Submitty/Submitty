#!/usr/bin/env python3

import sys
from PyPDF2 import PdfWriter, PdfReader
from shutil import copyfile

inputpdf = PdfReader(open("student_file.pdf", "rb"), strict=False)

# separate the pages of the student input file
for i in range(len(inputpdf.pages)):
    output = PdfWriter()
    output.add_page(inputpdf.pages[i])
    with open("student_file_page_%s.pdf" % i, "wb") as outputStream:
        output.write(outputStream)

# the max number of allowed notes pages is passed in as a command line argument pages
max = int(sys.argv[1])

# if the student submitted fewer than the max number of pages, create
# a copy of the blank page as a substitute
for i in range(len(inputpdf.pages),max):
    copyfile ("blank_page.pdf","student_file_page_%s.pdf" % i)
