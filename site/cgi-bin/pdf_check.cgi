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
# import cgitb; cgitb.enable()
import json
import os
import subprocess
import shutil
import stat

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

    current_path = os.path.dirname(os.path.realpath(__file__))
    uploads_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads")
    bulk_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads/bulk_pdf",g_id,ver)
    split_path = os.path.join("/var/local/submitty/courses",sem,course,"uploads/split_pdf",g_id,ver)

    # copy folder
    if not os.path.exists(split_path):
        os.makedirs(split_path)

    # adding write permissions for the PHP
    add_permissions_recursive(uploads_path, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP)

    # copy over files to new directory
    for filename in os.listdir(bulk_path):
        shutil.copyfile(os.path.join(bulk_path, filename), os.path.join(split_path, filename))

    # move to copy folder
    os.chdir(split_path)

    for filename in os.listdir(bulk_path):

        total_pages = subprocess.check_output("pdftk " + filename + " dump_data | awk '/NumberOfPages/{print $2}'", shell=True)
        total_pages = int(total_pages.decode('utf-8').rstrip())

        #make sure # of pages is divisible
        if (total_pages % num != 0):
            valid = False
            message = "Total # of pages: {t} is not divisible by the # of page(s) per exam: {n}".format(t=total_pages,n=num)
            shutil.rmtree(split_path)
            break

        # split the pdf
        div = total_pages // num
        for j in range(0,div):
            out_pdf = filename[:-4] + "_" + str(j) + ".pdf"
            out_cover_pdf = filename[:-4] + "_" + str(j) + "_cover.pdf"
            subprocess.call("pdftk {in_pdf} cat {start}-{stop} output {out_pdf}".format(in_pdf=filename,start=j*num+1,stop=(j+1)*num,out_pdf=out_pdf),shell=True)
            subprocess.call("pdftk {in_pdf} cat {start} output {out_pdf}".format(in_pdf=filename,start=j*num+1,out_pdf=out_cover_pdf),shell=True)

    # get rid of unnecessary copies
    for filename in os.listdir(bulk_path):
        os.remove(filename)

    os.chdir(current_path) #make sure this is in right place
    
except:
    valid = False
    # if copy exists, delete it... but relies on the fact that copy_path exists :(
    if os.path.exists(split_path):
        shutil.rmtree(split_path)
        

print(json.dumps({"valid": valid, "message": message}))
