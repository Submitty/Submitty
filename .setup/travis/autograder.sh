echo "Setting up auto-grader test suite"
apt-get install -yqq --force-yes python automake cmake make clang gcc g++ g++-multilib libseccomp2 seccomp libseccomp-dev valgrind
mkdir -p /usr/local/submitty
mkdir -p /var/local/submitty
mkdir -p /usr/local/submitty/src
cp -r tests /usr/local/submitty/test_suite
cp -r sample_files /usr/local/submitty/sample_files
cp -r grading/ /usr/local/submitty/src/

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/test_suite/integrationTests/lib.py
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/system_call_check.cpp
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|/var/local/submitty|g" /usr/local/submitty/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|/var/local/submitty|g" /usr/local/submitty/src/grading/CMakeLists.txt

# building the autograding library
mkdir -p /usr/local/submitty/src/grading/lib
pushd /usr/local/submitty/src/grading/lib
cmake ..
make
popd

chmod -R 777 /usr/local/submitty

echo "Getting DrMemory..."
mkdir -p /usr/local/submitty/DrMemory
pushd /tmp
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C /usr/local/submitty/DrMemory
ln -s /usr/local/submitty/DrMemory/DrMemory-Linux-${DRMEM_VER} /usr/local/submitty/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz
popd
