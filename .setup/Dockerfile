FROM ubuntu:16.04

RUN apt-get update
RUN apt-get install -qqy python python-dev python3 python3-dev libpython3.5
RUN apt-get install -qqy clang autoconf automake autotools-dev diffstat finger gdb git git-man \
hardening-includes p7zip-full patchutils \
libpq-dev unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev \
javascript-common  \
libfile-mmagic-perl libgnupg-interface-perl libbsd-resource-perl libarchive-zip-perl gcc g++ \
g++-multilib jq libseccomp-dev libseccomp2 seccomp junit flex bison spim poppler-utils pdftk
RUN apt-get install -qqy imagemagick

RUN mkdir -p /usr/local/submitty
COPY drmemory /usr/local/submitty/drmemory
COPY SubmittyAnalysisTools /usr/local/submitty/SubmittyAnalysisTools

RUN mkdir -p /var/local/submitty/autograding_tmp
#RUN mkdir -p /var/local/submitty/autograding_tmp/untrustedtmp/TMP_COMPILATION
#RUN mkdir -p /var/local/submitty/autograding_tmp/untrustedtmp/TMP_COMPILATION
#RUN mkdir -p /var/local/submitty/autograding_tmp/untrustedtmp/TMP_WORK

WORKDIR /var/local/submitty/autograding_tmp

# mount to /usr/src/app
