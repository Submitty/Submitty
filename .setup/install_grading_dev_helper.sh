
rsync -rtz /usr/local/submitty/GIT_CHECKOUT/Submitty/grading /usr/local/submitty/src

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/Sample_CMakeLists.txt

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|/usr/local/submitty|g" /usr/local/submitty/src/grading/system_call_check.cpp

cd /usr/local/submitty/src/grading/lib
cmake ..
make

chown -R  root:root /usr/local/submitty/src
find /usr/local/submitty/src -type d -exec chmod 555 {} \;
find /usr/local/submitty/src -type f -exec chmod 444 {} \;

# build the helper program for strace output and restrictions by system call categories
g++ /usr/local/submitty/src/grading/system_call_check.cpp -o /usr/local/submitty/bin/system_call_check.out
