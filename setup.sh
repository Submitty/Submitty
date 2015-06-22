#!/bin/bash
setup_dir=$(pwd)
repodir="gradingcode"
repodepth="../"

echo $setup_dir
# Setup a basic project (DEVELOP or MASTER)

read -p "Clone develop? (n = master, o = other) (y|n|o) " dev
dev="${dev:-y}"

if [[ $dev = "o" || $dev = "other"  || $dev = "O"  || $dev = "Other" ]] ; then
    read -p "What is your branch? " branch
fi
read -p "Linux (0) or Mac(1)? " comp
comp="${comp:-1}"

read -p "What is your class name? " class
class="${class:-csci1200}"

read -p "What is the semester? " semester
semester="${semester:-s15}"

read -p "Move (m), Copy (c), or Link (l) site? (or do (n)othing) " site
site="${site:-l}"

course_dir="courses/$semester/$class"

read -p "Move previous directories? (y|n|r) " yn
yn="${yn:-y}"

echo "Clone develop: $dev"
echo "Branch : $branch"
echo "Linux (0) or Mac(1)? $comp"
echo "Class name: $class"
echo "Semester: $semester"
echo "Move (m), Copy (c), or Link (l) site? (or do (n)othing): $site"
echo "Move previous directories? (y|n|r): $yn"
echo "********************************************************************************"
if [ $yn = "y" ]; then
    old="prev/"$(date +%s)"_save"
    mkdir -p $old
    echo "moving courses"
    sudo mv -f courses $old/courses
    echo "moving public"
    sudo mv -f public $old/public
    echo "moving to be graded"
    sudo mv -f to_be_graded $old/to_be_graded
    echo "moving bin"
    sudo mv -f bin $old/bin
    echo "moving repo"
    sudo mv -f $repodir $old/$repodir
elif [ $yn = "r" ]; then
    echo "removing courses"
    sudo rm -fr courses
    echo "removing public"
    sudo rm -fr public
    echo "removing to be graded"
    sudo rm -fr to_be_graded
    echo "removing bin"
    sudo rm -fr bin
    echo "removing repo"
    sudo rm -fr $repodir
fi
echo "********************************************************************************"

echo "Creating new directory structure"

# Setup $class "data and code" directory

test -d courses || mkdir courses
test -d courses/$semester || mkdir courses/$semester
test -d courses/$semester/$class || mkdir courses/$semester/$class

test -d $course_dir/bin || mkdir $course_dir/bin
test -d $course_dir/config || mkdir $course_dir/config
test -d $course_dir/hwconfig || mkdir $course_dir/hwconfig
test -d $course_dir/test_input || mkdir $course_dir/test_input
test -d $course_dir/test_output || mkdir $course_dir/test_output
test -d $course_dir/submissions || mkdir $course_dir/submissions
test -d to_be_graded || mkdir to_be_graded
test -d bin || mkdir bin

if [[ $comp = "1" ]] ; then
    sudo chown _www $course_dir/submissions
else
    sudo chown www-data $course_dir/submissions
fi
    sudo chgrp everyone $course_dir/submissions
    sudo chmod 750 $course_dir/submissions
    sudo chmod g+s $course_dir/submissions

sudo chmod -R g+w to_be_graded
echo "********************************************************************************"

# Clone Repo
if ! $(test -d $repodir) ; then
    if [[ $dev = "y" || $dev = "yes"  || $dev = "Y"  || $dev = "Yes" ]] ; then
        echo "cloning develop"
        if ! git clone git@github.com:RCOS-Grading-Server/HWserver.git $repodir ; then
            echo "Repository or branch is invalid.  Check at https://github.com/RCOS-Grading-Server/HWserver"
            exit
        fi
        cd $setup_dir/$repodir
        git fetch
        git checkout develop
        # git checkout demo
        echo "Adding development php auth"

        replace_with='if (!isset($_SERVER['"'PHP_AUTH_USER'"'])) {header('"'WWW-Authenticate: Basic realm=HWServer'"'); header('"'HTTP/1.0 401 Unauthorized'"'); exit;} else { $user = $_SERVER['"'PHP_AUTH_USER'"'];}'
        sed -i.orig  s~"echo 'Internal Error - Not Authenticated'; exit();//here"~"$replace_with"~ public/index.php

    elif [[ $dev = "o" || $dev = "other"  || $dev = "O"  || $dev = "Other" ]] ; then

        echo "cloning $branch"
        if ! git clone git@github.com:RCOS-Grading-Server/HWserver.git $repodir ; then
            echo "Repository or branch is invalid.  Check at https://github.com/RCOS-Grading-Server/HWserver"
            exit
        fi
        cd $repodir
        git fetch
        git checkout $branch
        # git checkout demo
        echo "Adding development php auth"

        replace_with='if (!isset($_SERVER['"'PHP_AUTH_USER'"'])) {header('"'WWW-Authenticate: Basic realm=HWServer'"'); header('"'HTTP/1.0 401 Unauthorized'"'); exit;} else { $user = $_SERVER['"'PHP_AUTH_USER'"'];}'
        sed -i.orig  s~"echo 'Internal Error - Not Authenticated'; exit();//here"~"$replace_with"~ public/index.php

    else
        echo "cloning master"
        if ! git clone https://github.com/RCOS-Grading-Server/HWserver.git $repodir ; then
            echo "Repository or branch is invalid.  Check at https://github.com/RCOS-Grading-Server/HWserver"
            exit
        fi
        cd $repodir
        git fetch
        git checkout master

    fi
fi
ls
git status


echo "********************************************************************************"

# Replace default with class name
echo "replace default with $class"

replace='//if ($course == "default") {return true;}'
replace_with='if ($course == "'$class'") {return true;}'
sed -i.orig  s~"$replace"~"$replace_with"~ public/controller/data_functions.php

replace='//if ($semester == "default") {return true;}'
replace_with='if ($semester == "'$semester'") {return true;}'
sed -i.o  s~"$replace"~"$replace_with"~ public/controller/data_functions.php

replace='/*,"default" => [default]*/'
replace_with=', "'.$semester.'" => ['.$class.']'
sed -i.orig  s~"$replace"~"$replace_with"~ public/controller/defaults.php

replace='pgrep_results=$(pgrep -u hwcron grade_students)'
replace_with='pgrep_results=$(pgrep grade_students)'
sed -i.orig  s~"$replace"~"$replace_with"~ bashScript/grade_students.sh


replace='numprocesses=$(ps -u untrusted | wc -l)'
replace_with='numprocesses=1 #$(ps -u untrusted | wc -l)'
sed -i.orig  s~"$replace"~"$replace_with"~ bashScript/grade_students.sh
# replace='$course != "default"'
# replace_with='$course != "'$class'"'
# sed -i.orig  s~"$replace"~"$replace_with"~ public/controller/upload.php
if [[ $comp = "1" ]] ; then
    echo "remove flock and valgrind check"
    sed -i.o  s~"flock"~"# flock"~ bashScript/grade_students.sh

    replace='valgrind "$bin_path/$assignment/validate.out"'
    replace_with='"$bin_path/$assignment/validate.out"'
    sed -i.orig2  s~"$replace"~"$replace_with"~ bashScript/grade_students.sh

    echo "remove seccomp"

    sed -i.orig  s~"#include <seccomp.h>"~"// #include <seccomp.h>"~ grading/execute.cpp
    sed -i.o       s~"// SECCOMP:"~"/* // SECCOMP:"~ grading/execute.cpp
    sed -i.o       s~"/\*blacklist\*/"~"  "~ grading/execute.cpp

    sed -i.o       s~"// END SECCOMP"~"*/ // END SECCOMP"~ grading/execute.cpp
    sed -i.o       s~"#include <elf.h>"~"// #include <elf.h>"~ grading/execute.cpp
    sed -i.o       s~"int install_syscall_filter(bool is_32, bool blacklist);"~"// int install_syscall_filter(bool is_32, bool blacklist);"~ grading/execute.cpp
    sed -i.o       s~'std::cout << "seccomp filter enabled" << std::endl;'~'std::cout << "seccomp filter DISABLED (not compatible)" << std::endl;'~ grading/execute.cpp

    sed -i.orig  s~'${GRADINGCODE}/grading/seccomp_functions.cpp'~'    '~ grading/Sample_CMakeLists.txt
    sed -i.o       s~'target_link_libraries(compile.out seccomp)'~'    '~ grading/Sample_CMakeLists.txt
    sed -i.o       s~'target_link_libraries(run.out seccomp)'~'    '~ grading/Sample_CMakeLists.txt
    sed -i.o       s~'target_link_libraries(validate.out seccomp)'~'    '~ grading/Sample_CMakeLists.txt
fi
cd $setup_dir

echo "********************************************************************************"

# Setup Site
if [[ $site = "l"  || $site = "link"  || $site = "Link"  || $site = "L" ]]; then
    echo "Linking site..."
    #Create site_path
    echo "$repodepth.." > $repodir/public/site_path.txt

    ln -s -f $repodir/public public
else
    if [[ $site = "c"  || $site = "copy"  || $site = "Copy"  ||  $site = "C" ]]; then
        echo "Copying site..."
        #Create site_path
        echo ".." > $repodir/public/site_path.txt

        cp -r $repodir/public public
    else
        if [[ $site = "m"  || $site = "move"  || $site = "Move"  ||  $site = "M" ]]; then
            echo "Moving site..."
            #Create site_path
            echo ".." > $repodir/public/site_path.txt

            mv  $repodir/public public
        else
            #Create site_path
            echo "$repodepth.." > $repodir/public/site_path.txt

        fi
    fi
fi
echo "********************************************************************************"

# Create default class.json
echo "linking grade_students script"
ln  $repodir/bashScript/grade_students.sh bin/grade_students.sh

echo "creating class.json and site_path.txt"
cp $repodir/Sample_Files/sample_class/class.json $course_dir/config/class.json

cp $repodir/Sample_Files/sample_class/sample_container.php public/view/"$semester"_"$class"_container.php

cp $repodir/Sample_Files/sample_class/sample_main.css public/resources/"$semester"_"$class"_main.css

cp $repodir/Sample_Files/sample_class/sample_upload_message.php public/view/"$semester"_"$class"_upload_message.php

cp $repodir/Sample_Files/sample_class/untrusted_runscript bin/untrusted_runscript
chmod +x bin/untrusted_runscript

echo "********************************************************************************"

echo "Creating sample assignment"
hwID="hw1"
# hwName="csci1100_hw01part1"
hwName="csci1200_hw1_text_justification"


sed -i.orig  s~"const int max_clocktime"~"const int max_clocktime = 200; //"~ $repodir/Sample_Files/sample_assignment_config/$hwName/config.h
sed -i.o     s~"const int max_cputime"~"const int max_cputime = 200; //"~ $repodir/Sample_Files/sample_assignment_config/$hwName/config.h

export BASEDIR=$setup_dir

source $repodir/bashScript/install_homework_function.sh
install_homework $BASEDIR $repodir/Sample_Files/sample_assignment_config/$hwName/   $semester   $class   $hwID

echo "source $repodir/bashScript/install_homework_function.sh"
echo "install_homework $BASEDIR $repodir/Sample_Files/sample_assignment_config/$hwName/   $semester   $class   $hwID"
echo "*****OR*******"
echo "cd $setup_dir/$course_dir/hwconfig/$hwID"
echo "CXX=/usr/bin/clang++ cmake . "
echo "make -j 2"
echo "********************************************************************************"

echo "Creating sample assignment"
hwID="lab1"
# hwName="csci1100_hw01part1"
hwName="csci1200_lab01_getting_started"

export BASEDIR=$setup_dir

sed -i.orig  s~"const int max_clocktime"~"const int max_clocktime = 200; //"~ $repodir/Sample_Files/sample_assignment_config/$hwName/config.h
sed -i.o     s~"const int max_cputime"~"const int max_cputime = 200; //"~ $repodir/Sample_Files/sample_assignment_config/$hwName/config.h

source $repodir/bashScript/install_homework_function.sh
install_homework $BASEDIR $repodir/Sample_Files/sample_assignment_config/$hwName/   $semester   $class   $hwID

echo "source $repodir/bashScript/install_homework_function.sh"
echo "install_homework $BASEDIR $repodir/Sample_Files/sample_assignment_config/$hwName/   $semester   $class   $hwID"
echo "*****OR*******"
echo "cd $setup_dir/$course_dir/hwconfig/$hwID"
echo "CXX=/usr/bin/clang++ cmake . "
echo "make -j 2"
echo "********************************************************************************"

# if [[ ($dev = "y" || $dev = "yes"  || $dev = "Y"  || $dev = "Yes" || $dev = "o" || $dev = "other"  || $dev = "O"  || $dev = "Other") && $comp = "0" ]]; then
#     # read -p "Add symlink to /var/www? (for apache) " yn
#     # if [ $yn = "y" ]; then
#     #     sudo rm -f "/var/www/html/hws"
#     #     sudo ln "$(pwd)/site/public" "/var/www/html/hws"
#     #     echo "You can now access the site at 127.0.0.1/hws"
#     # fi
# fi

echo "********************************************************************************"

echo "Done setting up $class"

# Start grader
echo $setup_dir
read -p "Would you like to start the bash grader script? " bash
echo "for bash grader, run:  sudo $setup_dir/$repodir/bashScript/grade_students.sh $setup_dir to_be_graded"
echo "sudo $setup_dir/bin/grade_students.sh $setup_dir to_be_graded
" > "run_grading.sh"
if [[ $bash = "y" ]]; then

    sudo $setup_dir/bin/grade_students.sh $setup_dir to_be_graded
fi
