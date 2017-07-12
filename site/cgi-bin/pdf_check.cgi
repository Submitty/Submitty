#!/usr/bin/env python3

"""
Given a filename, try to open that file, which should contain a JSON object
containing a username and password, then test that against PAM printing out
a JSON object that is authenticated and is true or false
"""
import cgi
# If things are not working, then this should be enabled for better troubleshooting
# import cgitb; cgitb.enable()
import json
import os
import subprocess
import shutil

print("Content-type: text/html")
print()

valid = True
message = "Something went wrong."

try:
    arguments = cgi.FieldStorage()
    directory = os.path.basename(arguments['directory'].value)
    num = int(os.path.basename(arguments['num'].value))

    CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
    original_path = "/tmp/" + directory
    copy_path = "/tmp/" + directory + "_copy"

    # copy folder
    if not os.path.exists(copy_path):
        os.makedirs(copy_path)

    # copy over files to new directory
    for filename in os.listdir(original_path):
        shutil.copyfile(original_path + "/" + filename, copy_path + "/" + filename)

    # move to copy folder
    os.chdir(copy_path)

    for filename in os.listdir(copy_path):

        total_pages = subprocess.check_output("pdftk " + filename + " dump_data | awk '/NumberOfPages/{print $2}'", shell=True)
        total_pages = int(total_pages.decode('utf-8').rstrip())

        # make sure # of pages is divisible
        if (total_pages % num != 0):
            valid = False
            message = "Total # of pages: {t} is not divisible by the # of page(s) per exam: {n}".format(t=total_pages,n=num)
            valid = False
            break

        # split the pdf
        div = total_pages // num
        for j in range(0,div):
            message += str(j)
            out_pdf = filename[:-4] + "_" + str(j) + ".pdf"
            out_cover_pdf = filename[:-4] + "_" + str(j) + "_cover.pdf"
            os.system("pdftk {in_pdf} cat {start}-{stop} output {out_pdf}".format(in_pdf=filename,start=j*num+1,stop=(j+1)*num,out_pdf=out_pdf))
            os.system("pdftk {in_pdf} cat {start} output {out_pdf}".format(in_pdf=filename,start=j*num+1,out_pdf=out_cover_pdf))

    os.chdir(CURRENT_PATH) #make sure this is in right place
    
except:
    valid = False

print(json.dumps({"valid": valid, "message": message}))
