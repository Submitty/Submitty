#!/usr/bin/env bash
set -ve

source "$( dirname "${BASH_SOURCE[0]}" )/get_globals.sh"

cat << EOF
########################################################################################################################
########################################################################################################################
# INSTALLING TOOLS
EOF


################################################################################################################
################################################################################################################
# INSTALL PYTHON SUBMITTY UTILS AND SET PYTHON PACKAGE PERMISSIONS

echo -e "Install python_submitty_utils"

rsync -rtz "${SUBMITTY_REPOSITORY}/python_submitty_utils" "${SUBMITTY_INSTALL_DIR}"
pushd "${SUBMITTY_INSTALL_DIR}/python_submitty_utils"

pip3 install .
# Setting the permissions are necessary as pip uses the umask of the user/system, which
# affects the other permissions (which ideally should be o+rx, but Submitty sets it to o-rwx).
# This gets run here in case we make any python package changes.
find /usr/local/lib/python*/dist-packages -type d -exec chmod 755 {} +
find /usr/local/lib/python*/dist-packages -type f -exec chmod 755 {} +
find /usr/local/lib/python*/dist-packages -type f -name '*.py*' -exec chmod 644 {} +
find /usr/local/lib/python*/dist-packages -type f -name '*.pth' -exec chmod 644 {} +

popd > /dev/null


########################################################################################################################
########################################################################################################################
# RSYNC NOTES
#  a = archive, recurse through directories, preserves file permissions, owner  [ NOT USED, DON'T WANT TO MESS W/ PERMISSIONS ]
#  r = recursive
#  v = verbose, what was actually copied
#  t = preserve modification times
#  u = only copy things that have changed
#  z = compresses (faster for text, maybe not for binary)
#  (--delete, but probably dont want)
#  / trailing slash, copies contents into target
#  no slash, copies the directory & contents to target

########################################################################################################################
########################################################################################################################
# CHECKOUT & INSTALL THE NLOHMANN C++ JSON LIBRARY

nlohmann_dir="${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/vendor/nlohmann/json"

# If we don't already have a copy of this repository, check it out
if [ ! -d "${nlohmann_dir}" ]; then
    git clone --depth 1 "https://github.com/nlohmann/json.git" "${nlohmann_dir}"
fi

# TODO: We aren't checking / enforcing a specific/minimum version of this library...

# Add read & traverse permissions for RainbowGrades and vendor repos
find "${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/vendor" -type d -exec chmod o+rx {} \;
find "${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/vendor" -type f -exec chmod o+r {} \;

# "install" the nlohmann json library
mkdir -p "${SUBMITTY_INSTALL_DIR}/vendor"
sudo chown -R root:submitty_course_builders "${SUBMITTY_INSTALL_DIR}/vendor"
sudo chown -R root:submitty_course_builders "${SUBMITTY_INSTALL_DIR}/vendor"
rsync -rtz "${SUBMITTY_REPOSITORY}/../vendor/nlohmann/json/include" "${SUBMITTY_INSTALL_DIR}/vendor/"
chown -R  root:root "${SUBMITTY_INSTALL_DIR}/vendor"
find "${SUBMITTY_INSTALL_DIR}/vendor" -type d -exec chmod 555 {} \;
find "${SUBMITTY_INSTALL_DIR}/vendor" -type f -exec chmod 444 {} \;


########################################################################################################################
########################################################################################################################
# COPY THE CORE GRADING CODE (C++ files) & BUILD THE SUBMITTY GRADING LIBRARY

echo -e "Copy the grading code"

# copy the files from the repo
rsync -rtz "${SUBMITTY_REPOSITORY}/grading" "${SUBMITTY_INSTALL_DIR}/src"

# copy the allowed_autograding_commands_default.json to config
rsync -tz "${SUBMITTY_REPOSITORY}/grading/allowed_autograding_commands_default.json" "${SUBMITTY_INSTALL_DIR}/config"

# replace filling variables
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"

# # change permissions of allowed_autograding_commands_default.json
chown "root":"root" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"
chmod 644 "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"

# create allowed_autograding_commands_custom.json if doesnt exist
if [[ ! -e "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json" ]]; then
    rsync -tz "${SUBMITTY_REPOSITORY}/grading/allowed_autograding_commands_custom.json" "${SUBMITTY_INSTALL_DIR}/config"
fi

# replace filling variables
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"

# # change permissions of allowed_autograding_commands_custom.json
chown "root":"root" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"
chmod 644 "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"

#replace necessary variables
array=( Sample_CMakeLists.txt CMakeLists.txt system_call_check.cpp seccomp_functions.cpp execute.cpp )
for i in "${array[@]}"; do
    replace_fillin_variables "${SUBMITTY_INSTALL_DIR}/src/grading/${i}"
done

# building the autograding library
mkdir -p "${SUBMITTY_INSTALL_DIR}/src/grading/lib"
pushd "${SUBMITTY_INSTALL_DIR}/src/grading/lib"
cmake ..
set +e
make
if [ "$?" -ne 0 ] ; then
    echo "ERROR BUILDING AUTOGRADING LIBRARY"
    exit 1
fi
set -e
popd > /dev/null

# root will be owner & group of these files
chown -R  root:root "${SUBMITTY_INSTALL_DIR}/src"
# "other" can cd into & ls all subdirectories
find "${SUBMITTY_INSTALL_DIR}/src" -type d -exec chmod 555 {} \;
# "other" can read all files
find "${SUBMITTY_INSTALL_DIR}/src" -type f -exec chmod 444 {} \;

chgrp submitty_daemon "${SUBMITTY_INSTALL_DIR}/src/grading/python/submitty_router.py"
chmod g+wrx           "${SUBMITTY_INSTALL_DIR}/src/grading/python/submitty_router.py"


#Set up sample files if not in worker mode.
if [ "${IS_WORKER}" == 0 ]; then
    ########################################################################################################################
    ########################################################################################################################
    # COPY THE SAMPLE FILES FOR COURSE MANAGEMENT

    echo -e "Copy the sample files"

    # copy the files from the repo
    rsync -rtz "${SUBMITTY_REPOSITORY}/more_autograding_examples" "${SUBMITTY_INSTALL_DIR}"

    # root will be owner & group of these files
    chown -R  root:root "${SUBMITTY_INSTALL_DIR}/more_autograding_examples"
    # but everyone can read all that files & directories, and cd into all the directories
    find "${SUBMITTY_INSTALL_DIR}/more_autograding_examples" -type d -exec chmod 555 {} \;
    find "${SUBMITTY_INSTALL_DIR}/more_autograding_examples" -type f -exec chmod 444 {} \;
fi


########################################################################################################################
########################################################################################################################
# BUILD JUNIT TEST RUNNER (.java file) if Java is installed on the machine

if [ -x "$(command -v javac)" ] &&
   [ -d ${SUBMITTY_INSTALL_DIR}/java_tools/JUnit ]; then
    echo -e "Build the junit test runner"

    # copy the file from the repo
    rsync -rtz "${SUBMITTY_REPOSITORY}/junit_test_runner/TestRunner.java" "${SUBMITTY_INSTALL_DIR}/java_tools/JUnit/TestRunner.java"

    pushd "${SUBMITTY_INSTALL_DIR}/java_tools/JUnit" > /dev/null
    # root will be owner & group of the source file
    chown  root:root  TestRunner.java
    # everyone can read this file
    chmod  444 TestRunner.java

    # compile the executable using the javac we use in the execute.cpp safelist
    /usr/bin/javac -cp ./junit-4.12.jar TestRunner.java

    # everyone can read the compiled file
    chown root:root TestRunner.class
    chmod 444 TestRunner.class

    popd > /dev/null


    # fix all java_tools permissions
    chown -R "root:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/java_tools"
    chmod -R 755                             "${SUBMITTY_INSTALL_DIR}/java_tools"
else
    echo -e "Skipping build of the junit test runner"
fi


#################################################################
# DRMEMORY SETUP
#################

# Dr Memory is a tool for detecting memory errors in C++ programs (similar to Valgrind)

# FIXME: Use of this tool should eventually be moved to containerized
# autograding and not installed on the native primary and worker
# machines by default

# FIXME: DrMemory is initially installed in install_system.sh
# It is re-installed here (on every Submitty software update) in case of version updates.

pushd /tmp > /dev/null

echo "Updating DrMemory..."

rm -rf /tmp/DrMemory*
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEMORY_TAG}/DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz
rsync --delete -a /tmp/DrMemory-Linux-${DRMEMORY_VERSION}/ ${SUBMITTY_INSTALL_DIR}/drmemory
rm -rf /tmp/DrMemory*

chown -R root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/drmemory
chmod -R 755 ${SUBMITTY_INSTALL_DIR}/drmemory



echo "...DrMemory ${DRMEMORY_TAG} update complete."

popd > /dev/null

