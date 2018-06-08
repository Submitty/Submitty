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
import cgi
# If things are not working, then this should be enabled for better troubleshooting
import cgitb; cgitb.enable()
import json
import os
import subprocess
import shutil
import stat
import PyPDF2
from PyPDF2 import PdfFileReader, PdfFileWriter
# from grade_item.py
def add_permissions(item,perms):
    if os.getuid() == os.stat(item).st_uid:
        os.chmod(item,os.stat(item).st_mode | perms)

def add_permissions_recursive(top_dir,root_perms,dir_perms,file_perms):
    for root, dirs, files in os.walk(top_dir):
        add_permissions(root,root_perms)
        for d in dirs:
            add_permissions(os.path.join(root, d),dir_perms)
        for f in files:
            add_permissions(os.path.join(root, f),file_perms)

print("Content-type: text/html")
print()

valid = True
message = "Something went wrong."

try:
    arguments = cgi.FieldStorage()
    num = int(os.path.basename(arguments['num'].value))
    sem = os.path.basename(arguments['sem'].value)
    course = os.path.basename(arguments['course'].value)
    g_id = os.path.basename(arguments['g_id'].value)
    ver = os.path.basename(arguments['ver'].value)
    message = "Something went wrong:  just defined variables"
    current_path = os.path.dirname(os.path.realpath(__file__))
    uploads_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads")
    bulk_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads/bulk_pdf",g_id,ver)
    split_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads/split_pdf",g_id,ver)
    message = "Something went wrong:  just defined more paths"

    # copy folder
    if not os.path.exists(split_path):
        os.makedirs(split_path)

    # adding write permissions for the PHP
    add_permissions_recursive(uploads_path, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP)
    message = "Something went wrong:  preparing split folder"

    # copy over files to new directory
    for filename in os.listdir(bulk_path):
        shutil.copyfile(os.path.join(bulk_path, filename), os.path.join(split_path, filename))

    # move to copy folder
    os.chdir(split_path)
    message = "Something went wrong: preparing bulk folder"

    # check that all pages are divisible
    for filename in os.listdir(bulk_path):

        # dump pdf info from pdftk, then parse for the total # of pages
        pdfFileObj = open(filename, 'rb')
        pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
        total_pages = pdfReader.numPages
        if (total_pages % num != 0):
            valid = False
            message = "For file '{f}' the total # of pages: {t} is not divisible by the # of page(s) per exam: {n}".format(f=filename,t=total_pages,n=num)
            shutil.rmtree(split_path)
            break

    # split pdfs
    for filename in os.listdir(bulk_path):

        # recalculate the total # of pages for each file
        pdfFileObj = open(filename, 'rb')
        pdfReader = PyPDF2.PdfFileReader(pdfFileObj)
        total_pages = pdfReader.numPages

        div = total_pages // num
        
        # pdf = PdfFileReader(path)
        counter = 0
        testmsg = ""
        for i in range(0, total_pages, num):
            cover_writer = PdfFileWriter()
            cover_writer.addPage(pdfReader.getPage(counter)) 
            cover_filename = '{}_{}_cover.pdf'.format(filename, int(i/2))
            pdf_writer = PdfFileWriter()
            start = counter
            for j in range(start, start+num):
                pdf_writer.addPage(pdfReader.getPage(j)) 
                output_filename = '{}_{}.pdf'.format(filename, int(i/2))
                counter+=1
            testmsg += str(pdf_writer.getNumPages()) + "\n"
            with open(output_filename, 'wb') as out:
                pdf_writer.write(out)
            with open(cover_filename, 'wb') as out:
                cover_writer.write(out)
    os.system("chown -R instructor:{}_tas_www {}".format(course, split_path))
    message += "=> finished PyPDF2"
    file = open("blabla.txt", "w")
    file.write(testmsg)
    # get rid of unnecessary copies
    for filename in os.listdir(bulk_path):
        os.remove(filename)

    os.chdir(current_path) #make sure this is in right place
    message += ",and finished"
except Exception as e:
    valid = False
    # if copy exists, delete it... but relies on the fact that copy_path exists :(
    if os.path.exists(split_path):
        shutil.rmtree(split_path)
    message += str(e)
        

print(json.dumps({"valid": valid, "message": message}))
