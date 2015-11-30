#!/bin/bash

sudo add-apt-repository ppa:ubuntu-toolchain-r/test -y
sudo add-apt-repository -y ppa:boost-latest/ppa
sudo apt-get update -qq

sudo apt-get -qqy install gcc-4.8 g++-4.8 hhvm-dev libpq-dev libjemalloc-dev

sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-4.8 60 \
                         --slave /usr/bin/g++ g++ /usr/bin/g++-4.8
sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-4.6 40 \
                         --slave /usr/bin/g++ g++ /usr/bin/g++-4.6
sudo update-alternatives --set gcc /usr/bin/gcc-4.8

sudo apt-get install -qqy libboost1.55-all-dev

#wget http://www.canonware.com/download/jemalloc/jemalloc-3.6.0.tar.bz2
#tar xjvf jemalloc-3.6.0.tar.bz2
#cd jemalloc-3.6.0
#./configure --prefix=$CMAKE_PREFIX_PATH
#make
#sudo make install
#cd ..

svn checkout http://google-glog.googlecode.com/svn/trunk/ google-glog
cd google-glog
./configure #--prefix=$CMAKE_PREFIX_PATH
make
sudo make install
cd ..

git clone https://github.com/PocketRent/hhvm-pgsql
cd hhvm-pgsql
#wget http://github.com/PocketRent/hhvm-pgsql/archive/3.6.0.tar.gz
#tar -xvf 3.6.0.tar.gz
cd hhvm-pgsql-3.6.0
hphpize
cmake .
LD_LIBRARY_PATH=/usr/local/lib
make
sudo mv pgsql.so /etc/hhvm

sudo echo -e "extension_dir = /etc/hhvm
hhvm.extensions[pgsql] = pgsql.so" >> /etc/hhvm/php.ini

sudo service hhvm restart

hhvm --php -r 'var_dump(function_exists("pg_connect"));'
cd ..