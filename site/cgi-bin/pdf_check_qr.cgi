#!/usr/bin/env python3

"""
Given a semester, course, gradeable id, version, and qr_code prefix per pdf,
splits by QR code. The split pdf items are placed
in the split pdf directory. 
"""
import cgi
# If things are not working, then this should be enabled for better troubleshooting
# import cgitb; cgitb.enable()
import json
import os
import shutil
import stat
from PyPDF2 import PdfFileReader, PdfFileWriter
from pdf2image import convert_from_bytes
import pyzbar.pyzbar as pyzbar
#from grade_item.py

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

print("Content-type: application/json")
print()

valid = True
message = "Something went wrong."

try:
    arguments = cgi.FieldStorage()

    sem = os.path.basename(arguments['sem'].value)
    course = os.path.basename(arguments['course'].value)
    g_id = os.path.basename(arguments['g_id'].value)
    ver = os.path.basename(arguments['ver'].value)
    qr_prefix = ''
    #check if qr_prefix is passed in, an empty string since is not considered a valid CGI arg
    for arg in cgi.parse(arguments):
        if arg == 'qr_prefix':
            qr_prefix = os.path.basename(arguments['qr_prefix'].value)
            break
        qr_prefix = ""

    with open("/usr/local/submitty/config/submitty.json", encoding='utf-8') as data_file:
        data = json.loads(data_file.read())

    #print("making paths")
    current_path = os.path.dirname(os.path.realpath(__file__))
    uploads_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads")
    bulk_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads/bulk_pdf",g_id,ver)
    split_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads/split_pdf",g_id,ver)

    # copy folder
    if not os.path.exists(split_path):
        os.makedirs(split_path)

    # adding write permissions for the PHP
    add_permissions_recursive(uploads_path, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP | stat.S_IXGRP, stat.S_IWGRP)
    # message = "Something went wrong:  preparing split folder"

    # copy over files to new directory
    for filename in os.listdir(bulk_path):
        shutil.copyfile(os.path.join(bulk_path, filename), os.path.join(split_path, filename))

    # move to copy folder
    os.chdir(split_path)
    #message = "Something went wrong: preparing bulk folder"

    # # split pdfs
    for filename in os.listdir(bulk_path):
        output = {}
        pdfPages = PdfFileReader(filename)
        #convert pdf to series of images for scanning
        pages = convert_from_bytes(open(filename, 'rb').read())
        pdf_writer = PdfFileWriter()
    
        i = 0
        cover_index = 0
        #start student id index at 1 to match up with displaying files in BulkUploadBox.twig
        id_index = 1
        page_count = 1
        first_file = ''
        for page in pages:
            val = pyzbar.decode(page)
            if val != []:
                cover_index = i
                #found a new qr code, split here
                #convert byte literal to string
                data = val[0][0].decode("utf-8")
                if qr_prefix != "" and data[0:len(qr_prefix)] == qr_prefix:
                    data = data[len(qr_prefix):]

                cover_filename = '{}_{}_cover.pdf'.format(filename[:-4], i)
                output_filename = '{}_{}.pdf'.format(filename[:-4], cover_index)

                output[id_index] = {}
                output[id_index]['id'] = data
                output[id_index]['page_count'] = page_count
                output[id_index]['pdf_name'] = output_filename
                #save pdf
                if i != 0:
                    with open(output_filename, 'wb') as out:
                        pdf_writer.write(out)
                else:
                    first_file = output_filename
                if id_index == 2:
                    #correct first pdf's page count and print file
                    output[1]['page_count'] = page_count
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

    #save json to parse student names later
    with open('decoded.json', 'w') as out:
        json.dump(output, out)

    #cleanup
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

print(json.dumps({"valid" : valid, "message" : message}))
