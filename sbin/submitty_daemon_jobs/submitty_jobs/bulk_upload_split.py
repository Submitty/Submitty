#!/usr/bin/env python3

"""Splits a PDF every num pages and moves images and pdfs to split folder."""

import os
import PyPDF2
import sys
import traceback
from PyPDF2 import PdfFileWriter
from . import write_to_log as logger

try:
    from pdf2image import convert_from_bytes
except ImportError:
    print("One or more required python modules not installed correctly")
    traceback.print_exc()
    sys.exit(1)


def main(args):
    """Split filename pdf by num pages and save an image of each pdf page."""
    filename = args[0]
    split_path = args[1]
    num = int(args[2])
    log_file_path = args[3]

    log_msg = "Process " + str(os.getpid()) + ": "
    try:
        # check that all pages are divisible
        pdfFileObj = open(filename, 'rb')
        pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
        total_pages = pdfReader.numPages
        if (total_pages % num != 0):
            msg = filename + " not divisible by " + str(num)
            print(msg)
            logger.write_to_log(log_file_path, log_msg + msg)
            sys.exit(1)

        # recalculate the total # of pages for each file
        pdfFileObj = open(filename, 'rb')
        pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
        total_pages = pdfReader.numPages
        max_length = len(str(total_pages - num))

        i = 0
        os.chdir(split_path)
        buff = log_msg
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
                i += 1
            with open(output_filename, 'wb') as out:
                pdf_writer.write(out)

            # save pdfs as images
            pdf_images = convert_from_bytes(open(output_filename, 'rb').read())
            for k in range(len(pdf_images)):
                pdf_images[k].save('{}.jpg'.format(output_filename[:-4]),
                                   "JPEG", quality=100)

            with open(cover_filename, 'wb') as out:
                cover_writer.write(out)

            buff += "Splitting PDF at page " + str(i) + ", "

            # save cover as image
            pdf_images = convert_from_bytes(open(cover_filename, 'rb').read())
            pdf_images[0].save('{}.jpg'.format(cover_filename[:-4]),
                               "JPEG", quality=100)

        buff += "Finished splitting into " + str(int(total_pages/num)) + " files"
        logger.write_to_log(log_file_path, buff)
    except Exception as err:
        print(err)
        traceback.print_exc()
        logger.write_to_log(log_file_path, buff)
        logger.write_to_log(log_file_path, traceback.format_exc())


if __name__ == "__main__":
    main()
