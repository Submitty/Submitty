#!/usr/bin/env python3

"""
Given a semester, course, gradeable id, version, and number of pages per pdf,
it checks whether the total number of pages in each bulk pdf file is divisible
by the page per pdf, and splits accordingly. The split pdf items are placed
in the split pdf directory. 
If any of the uploaded bulk pdfs are not divisible by the number of pages
per pdf, all created split pdfs for this version are deleted and an error message
is returned.
"""
import os
import PyPDF2
import sys
import traceback
from PyPDF2 import PdfFileReader, PdfFileWriter

try:
    from pdf2image import convert_from_bytes
    from PIL import Image
except ImportError as e:
    print("One or more required python modules not installed correctly")
    traceback.print_exc() 
    sys.exit(1)

filename = sys.argv[1]
split_path = sys.argv[2]
num = sys.argv[3]
try:
    # check that all pages are divisible
    pdfFileObj = open(filename, 'rb')
    pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
    total_pages = pdfReader.numPages

    if (total_pages % num != 0):
        print(filename + " not divisible by " + str(num))
        shutil.rmtree(split_path)
        sys.exit(1)

    # recalculate the total # of pages for each file
    pdfFileObj = open(filename, 'rb')
    pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
    total_pages = pdfReader.numPages

    div = total_pages // num
    max_length = len(str(total_pages - num))

    i = 0
    while i < total_pages:
        cover_writer = PdfFileWriter()
        cover_writer.addPage(pdfReader.getPage(i))
        prepended_index = str(i).zfill(max_length)
        cover_filename = '{}_{}_cover.pdf'.format(filename[:-4], prepended_index)
        output_filename = '{}_{}.pdf'.format(filename[:-4], prepended_index)
        pdf_writer = PdfFileWriter()
        start = i
        for j in range(start, start+num):
            pdf_writer.addPage(pdfReader.getPage(j)) 
            i+=1
        with open(output_filename, 'wb') as out:
            pdf_writer.write(out)

        #save pdfs as images
        pdf_images = convert_from_bytes(open(output_filename, 'rb').read())
        for k in range(len(pdf_images)):
            pdf_images[k].save('{}.jpg'.format(output_filename[:-4]), "JPEG", quality = 100);

        with open(cover_filename, 'wb') as out:
            cover_writer.write(out)

        #save cover as image
        pdf_images = convert_from_bytes(open(cover_filename, 'rb').read())
        pdf_images[0].save('{}.jpg'.format(cover_filename[:-4]), "JPEG", quality = 100);

        os.chdir(bulk_path)
        os.remove(filename)

        os.chdir(current_path) #make sure this is in right place
except Exception as e:
    # if copy exists, delete it
    if os.path.exists(split_path):
        shutil.rmtree(split_path)
    
    traceback.print_exc()
