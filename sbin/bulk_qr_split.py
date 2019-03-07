#!/usr/bin/env python3

"""
Given a semester, course, gradeable id, version, and qr_code prefix per pdf,
splits by QR code. The split pdf items are placed
in the split pdf directory. 
"""
import json
import os
import shutil
import stat
import traceback
import sys
import time

#try importing required modules
try:
        from PyPDF2 import PdfFileReader, PdfFileWriter
        from pdf2image import convert_from_bytes
        import pyzbar.pyzbar as pyzbar
        import urllib.parse
except ImportError:
        print("\nbulk_qr_split.py: Error! One or more required python modules not installed correctly\n")
        traceback.print_exc()
        sys.exit(1)

def main():
    filename = sys.argv[1]
    split_path = sys.argv[2]
    qr_prefix = sys.argv[3]
    qr_suffix = sys.argv[4]
    try:
        os.chdir(split_path)
        pdfPages = PdfFileReader(filename)
        #convert pdf to series of images for scanning
        pages = convert_from_bytes(open(filename, 'rb').read())
        pdf_writer = PdfFileWriter()
        i = cover_index = id_index = 0
        page_count = 1
        first_file = ''
        data = []
        output = {}
        for page in pages:
            val = pyzbar.decode(page)
            if val != []:
                #found a new qr code, split here
                #convert byte literal to string
                data = val[0][0].decode("utf-8")
                if data == "none":  # blank exam with 'none' qr code
                        data = "BLANK EXAM"
                else:
                        if qr_prefix != '' and data[0:len(qr_prefix)] == qr_prefix:
                                data = data[len(qr_prefix):]
                        if qr_suffix != '' and data[(len(data)-len(qr_suffix)):len(data)] == qr_suffix :
                                data = data[:-len(qr_suffix)]
                cover_index = i
                cover_filename = '{}_{}_cover.pdf'.format(filename[:-4], i)
                output_filename = '{}_{}.pdf'.format(filename[:-4], cover_index)

                output[id_index] = {}
                output[id_index]['id'] = data
                output[id_index]['pdf_name'] = output_filename
                #save pdf
                if i != 0:
                        output[id_index-1]['page_count'] = page_count
                        with open(output[id_index-1]['pdf_name'], 'wb') as out:
                                pdf_writer.write(out)
                else:
                        first_file = output_filename

                if id_index == 1:
                        #correct first pdf's page count and print file
                        output[0]['page_count'] = page_count
                        with open(first_file, 'wb') as out:
                                pdf_writer.write(out)

                #start a new pdf and grab the cover
                cover_writer = PdfFileWriter()
                pdf_writer = PdfFileWriter()
                cover_writer.addPage(pdfPages.getPage(i)) 
                pdf_writer.addPage(pdfPages.getPage(i))

                #save cover
                with open(cover_filename,'wb') as out:
                        cover_writer.write(out)

                id_index += 1
                page_count = 1
            else:
                    #add pages to current split_pdf
                    page_count += 1
                    pdf_writer.addPage(pdfPages.getPage(i))
            i += 1

        #save whatever is left
        output_filename = '{}_{}.pdf'.format(filename[:-4], cover_index)
        output[id_index-1]['id'] = data
        output[id_index-1]['page_count'] = page_count
        output[id_index-1]['pdf_name'] = output_filename
        with open(output_filename,'wb') as out:
                pdf_writer.write(out)

            #write json to file for parsing page counts and decoded ids later
        with open('decoded.json', 'w') as out:
            json.dump(output, out)

    except Exception:
        print("\nbulk_qr_split.py: Failed when splitting pdf " + str(filename))
        traceback.print_exc()
        sys.exit(1)  

if __name__ == "__main__":
    main()