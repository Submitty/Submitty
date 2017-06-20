import sys
import os
import tempfile
import shutil
import subprocess

#TODO: Make message/comment about requiring active vagrant box/server and having already generated summary for csci1100
#TODO: Check that we are in fact on a host machine
#TODO: Make a def so we can clean up the temp files when exiting on failure
#TODO: Mention autograding may take a while, advise using grading_done.sh?
#TODO: Put FileIO into try blocks so a missing file doesn't crash the script
#TODO: Test failure-to-rsync-behavior

def remove_extra_rawdata_fields(line):
    if 'grade_released_date' in line:
        return False # ignore it
    if 'last_update' in line:
        return False # ignore it
    return True

def remove_info_last_updated(line):
    # <em>Information last updated: Monday, June 12, 2017</em><br>
    if '<em>Information last updated:' in line:
        return False # ignore it
    return True

if __name__ == '__main__':
    script_path = os.path.dirname(os.path.realpath(__file__))
    print("Currently in {}".format(script_path))

    #iris_path = os.path.join(script_path,["..","..","RainbowGrades"])
    iris_path = os.path.join(script_path,"..","..","RainbowGrades")
    if not os.path.exists(iris_path):
        print("Couldn't find Iris source code")
        sys.exit(-1)

    print("Creating temporary RainbowGrades test directories")
    test_tmp = tempfile.mkdtemp("", "")
    print("Made new directory {}".format(test_tmp))

    if not os.path.exists(test_tmp):
        print("Failed to create temporary directory")
        sys.exit(-1)

    iris_tmp = os.path.join(test_tmp,"iris")
    os.mkdir(iris_tmp)

    summary_tmp = os.path.join(test_tmp,"grade_summaries")
    os.mkdir(summary_tmp)

    grading_tmp = os.path.join(test_tmp,"grading")
    os.mkdir(grading_tmp)

    if not os.path.exists(iris_tmp) or not os.path.exists(summary_tmp):
        print("Failed to create temporary directory")
        sys.exit(-1)

    print("Copying Iris code from Submitty to RainbowGrades")
    for f in os.listdir(iris_path):
        shutil.copy(os.path.join(iris_path,f),iris_tmp)

    shutil.copy(os.path.join(script_path,"MakefileHelperTest"),os.path.join(iris_tmp,"MakefileHelper"))
    shutil.copy(os.path.join(script_path,"Makefile_csci1100"),os.path.join(summary_tmp,"Makefile"))
    shutil.copy(os.path.join(script_path,"customization_csci1100.json"),os.path.join(summary_tmp,"customization.json"))
    shutil.copy(os.path.join(script_path,"..","..","grading","json_syntax_checker.py"),os.path.join(grading_tmp,"json_syntax_checker.py"))

    #Need to update Makefile to use the temporary location of Iris
    make_file = open(os.path.join(summary_tmp,"Makefile"),'r')
    make_file_contents = make_file.readlines()
    make_file.close()
    make_file = open(os.path.join(summary_tmp,"Makefile"),'w')
    for line in make_file_contents:
        if len(line)>=25 and line[:25]=="RAINBOW_GRADES_DIRECTORY=":
            make_file.write("RAINBOW_GRADES_DIRECTORY="+iris_tmp+"\n")
        else:
            make_file.write(line)
    make_file.close()

    print("Attempting to rsync contents")
    os.chdir(summary_tmp)
    subprocess.call(["make","pull"])

    if not os.path.exists(os.path.join(summary_tmp,"raw_data")):
        print("Failed to rsync data")
        sys.exit(-1)

    print("Extracting known raw data")
    known_raw_path = os.path.join(test_tmp,"raw_data")
    summary_raw_path = os.path.join(summary_tmp,"raw_data")
    os.mkdir(known_raw_path)
    subprocess.call(["tar","-xf",os.path.join(script_path,"raw_data_10090542_csci1100.tar"),"-C",known_raw_path])
    print("Comparing known raw data to rsync'd raw data")
    #Verify raw_data files match both in name and content (except for grades_released_date) with test version
    known_files = []
    summary_files = []
    for f in os.listdir(known_raw_path):
        known_files.append(f)
    for f in os.listdir(summary_raw_path):
        summary_files.append(f)

    if len(known_files) != len(summary_files):
        file_diff = len(known_files) - len(summary_files)
        if len(known_files) > len(summary_files):
            print("There are {} more files in the rsync'd raw_data than expected.".format(file_diff))
        else:
            print("There are {} fewer files in the rsync'd raw_data than expected.".format(-1*file_diff))
        sys.exit(-1)

    for f in known_files:
        fname1 = os.path.join(known_raw_path,f)
        fname2 = os.path.join(summary_raw_path, f)
        file1 = open(fname1,'r')
        contents1 = file1.readlines()
        file1.close()

        file2 = open(fname2, 'r')
        contents2 = file2.readlines()
        file2.close()

        filt1 = filter(remove_extra_rawdata_fields,contents1)
        filt2 = filter(remove_extra_rawdata_fields, contents2)
        same_flag = True
        for x,y in zip(filt1,filt2):
            if x != y:
                same_flag = False
        if not same_flag:
            print("{} and {} differ".format(fname1,fname2))
            exit(-1)

    print("All raw files match")

    print("Running Iris on rsync'd data. This could take several minutes.")
    try:
        make_output = subprocess.check_output(["make"])
    except subprocess.CalledProcessError as e:
        print("Make failed with code {}".format(e.returncode))
        sys.exit(-1)

    if not os.path.exists(os.path.join(summary_tmp,"output.html")):
        print("Failed to create output.html")

    print("output.html generated")

    make_output = make_output.split("\n")
    make_output = make_output[-2].strip() #Get the RUN COMMAND LINE
    make_output = make_output.split("/")
    make_output = make_output[-1] #Get the name of the output.html file

    #Verify that a valid copy was sent to all_students_summary_html
    print("Checking summary files against expected summaries.")
    if not os.path.exists(os.path.join(summary_tmp,"all_students_summary_html",make_output)):
        print("Failed to find output file in all_students_summary_html")
        sys.exit(-1)

    output_generated_file = open(os.path.join(summary_tmp,"output.html"),'r')
    output_known_file = open(os.path.join(script_path,"output_10090542_csci1100.html"),'r')
    output_generated_contents = output_generated_file.read()
    output_known_contents = output_known_file.read()

    if output_generated_contents != output_known_contents:
        print("Generated output.html did not match expected output.html")
        sys.exit(-1)


    #Verfiy all of the individual_grade_summary_html files with test versions, and output.html (extract first)

    known_individual_path = os.path.join(test_tmp,"individual_summary_html")
    summary_individual_path = os.path.join(summary_tmp,"individual_summary_html")
    os.mkdir(known_individual_path)
    subprocess.call(["tar","-xf",os.path.join(script_path,"individual_summary_10090542_csci1100.tar"),"-C",known_individual_path])

    known_files = []
    summary_files = []
    for f in os.listdir(known_individual_path):
        known_files.append(f)
    for f in os.listdir(summary_individual_path):
        summary_files.append(f)

    if len(known_files) != len(summary_files):
        file_diff = len(known_files) - len(summary_files)
        if len(known_files) > len(summary_files):
            print("There are {} more files in the generated individual_summary_html than expected.".format(file_diff))
        else:
            print("There are {} fewer files in the generated individual_summary_html than expected.".format(-1*file_diff))
        sys.exit(-1)

    for f in known_files:
        #FIXME: Currently not checking generated personal message files (for seating/materials)
        if f[-12:]!="summary.html":
            continue
        fname1 = os.path.join(known_individual_path,f)
        fname2 = os.path.join(summary_individual_path, f)
        file1 = open(fname1,'r')
        contents1 = file1.readlines()
        file1.close()

        file2 = open(fname2, 'r')
        contents2 = file2.readlines()
        file2.close()

        filt1 = filter(remove_info_last_updated,contents1)
        filt2 = filter(remove_info_last_updated,contents2)
        same_flag = True
        for x,y in zip(filt1,filt2):
            if x != y:
                same_flag = False
        if not same_flag:
            print("{} and {} differ".format(fname1,fname2))
            exit(-1)

    print("All generated files match")

    #Cleanup code
    print("Removing temporary directory")
    shutil.rmtree(test_tmp)
