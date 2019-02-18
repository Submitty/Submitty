#!/usr/bin/env python3

"""
Given a semester, course, gradeable id, version, and qr_code prefix per pdf,
splits by QR code. The split pdf items are placed
in the split pdf directory. 
"""
import cgi
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
except ImportError as e:
    print("Content-type: application/json")
    print()
    message = "Error from pdf_check_qr.cgi:\n"
    message += "One or more required python modules not installed correctly\n"
    message += traceback.format_exc() 
    print(json.dumps({"valid" : False, "message" : message}))
    sys.exit(1)
start_time = time.time()
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

message = "Error from pdf_check_qr.cgi:\n"

try:
    arguments = cgi.FieldStorage()

    sem = os.path.basename(arguments['sem'].value)
    course = os.path.basename(arguments['course'].value)
    g_id = os.path.basename(arguments['g_id'].value)
    ver = os.path.basename(arguments['ver'].value)
    qr_prefix = qr_suffix = ''
    #check inputs for URL spoofing
    for key in ['sem', 'course', 'g_id', 'ver']:
        if os.path.basename(arguments[key].value) in ['.', '..']:
            message += '. Invalid value for ' + key + '.'
            print(json.dumps({"valid" : False, "message" : message}))
            sys.exit(1)
    #check if qr_prefix or qr_suffix is passed in, an empty string since is not considered a valid CGI arg
    for arg in cgi.parse(arguments):
        if arg == 'qr_prefix':
            qr_prefix = urllib.parse.unquote(os.path.basename(arguments['qr_prefix'].value))
            break

    for arg in cgi.parse(arguments):
        if arg == 'qr_suffix':
            qr_suffix = urllib.parse.unquote(os.path.basename(arguments['qr_suffix'].value))
            break

    with open("/usr/local/submitty/config/submitty.json", encoding='utf-8') as data_file:
        data = json.loads(data_file.read())

    current_path = os.path.dirname(os.path.realpath(__file__))
    uploads_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads")
    bulk_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads/bulk_pdf",g_id,ver)
    split_path = os.path.join(data["submitty_data_dir"],"courses",sem,course,"uploads/split_pdf",g_id,ver)

except Exception as e:
    message = "Failed after parsing args and creating paths\n"
    message += traceback.format_exc()
    print(json.dumps({"valid" : False, "message" : message}))
    sys.exit(1)

try:
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
except Exception as e:
    message = "Failed after adding permissions and copying files\n"
    message += traceback.format_exc()
    #cleanup
    if os.path.exists(split_path):
        shutil.rmtree(split_path)
    print(json.dumps({"valid" : False, "message" : message}))
    sys.exit(1)
try:
    valid = True
    json_data = {}
    #split pdfs
    for filename in os.listdir(bulk_path):
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

        if len(output) == 0:
            message = "Warning Could not find any QR codes in : " + str(filename) + " !\n"
            #This doesn't actually invalidate the process but forces an error message to show
            #to let the user know one of their files had no qr codes
            valid = False
            continue
        else:
             #save whatever is left
            output_filename = '{}_{}.pdf'.format(filename[:-4], cover_index)
            output[id_index-1]['id'] = data
            output[id_index-1]['page_count'] = page_count
            output[id_index-1]['pdf_name'] = output_filename
            with open(output_filename,'wb') as out:
                pdf_writer.write(out)

        #combine all json data
        json_data[filename] = output
    #write json to file for parsing page counts and decoded ids later
    with open('decoded.json', 'w') as out:
        json.dump(json_data, out)

    #done going over all files in uploads directory, cleanup
    for filename in os.listdir(bulk_path):
        os.remove(filename)

    os.chdir(current_path)
    message += "finished splitting qr codes in : " + str("{0:.3f}".format(time.time() - start_time) ) + " seconds\n"
except Exception as e:
    if os.path.exists(split_path):
        shutil.rmtree(split_path)
    message = "Failed when splitting PDFs\n"
    message += traceback.format_exc()
    print(json.dumps({"valid" : False, "message" : message}))
    sys.exit(1)

print(json.dumps({"valid" : valid, "message" : message}))
