echo "Setting up auto-grader test suite"
apt-get install -yqq --force-yes python automake cmake make clang gcc g++ g++-multilib libseccomp2 seccomp libseccomp-dev valgrind
mkdir -p /usr/local/hss
mkdir -p /var/local/hss
mkdir -p /usr/local/hss/src
cp -r tests /usr/local/hss/test_suite
mkdir -p /usr/local/hss/test_suite/log
cp -r sample_files /usr/local/hss/sample_files
cp -r grading/ /usr/local/hss/src/

sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/test_suite/integrationTests/lib.py
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/system_call_check.cpp
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_DATA_DIR__|/var/local/hss|g" /usr/local/hss/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_DATA_DIR__|/var/local/hss|g" /usr/local/hss/src/grading/CMakeLists.txt

# building the autograding library
mkdir -p /usr/local/hss/src/grading/lib
pushd /usr/local/hss/src/grading/lib
cmake ..
make
popd

chmod -R 777 /usr/local/hss

echo "Getting DrMemory..."
mkdir -p /usr/local/hss/DrMemory
pushd /tmp
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C /usr/local/hss/DrMemory
ln -s /usr/local/hss/DrMemory/DrMemory-Linux-${DRMEM_VER} /usr/local/hss/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz
popd