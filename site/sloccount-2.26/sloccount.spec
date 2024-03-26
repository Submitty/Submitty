#
# RPM spec file for "sloccount".
#
%define PKG_VERSION 2.26

Name: sloccount
Summary: Measures source lines of code (SLOC) in programs
Version: %{PKG_VERSION}
Release: 1
Copyright: GPL
Group: Development/Tools
Source: http://www.dwheeler.com/sloccount/sloccount-%{PKG_VERSION}.tar.gz
URL: http://www.dwheeler.com/sloccount
Vendor: David A. Wheeler
Packager: David A. Wheeler <dwheeler@dwheeler.com>
Prefix: /usr
BuildRoot:     /var/tmp/%name-buildroot

%description
SLOCCount (pronounced "sloc-count") is a suite of programs for counting
physical source lines of code (SLOC) in potentially large software systems
(thus, SLOCCount is a "software metrics tool" or "software measurement tool").
SLOCCount can count physical SLOC for a wide number of languages;
listed alphabetically, they are: Ada, Assembly, awk, Bourne shell, C, C++,
C shell, COBOL, Expect, Fortran, Java, lex/flex, LISP (including Scheme),
Modula-3, Objective-C, Pascal, Perl, PHP, Python, sed, TCL, and Yacc.
SLOCCount can automatically determine if a file
is a source code file or not, and if so, which language it's written in.
As a result, you can analyze large systems completely automatically;
it's been used to examine entire GNU/Linux distributions, for example.
SLOCCount also includes some report-generating tools
to collect the data generated and present it in several different formats.
Normally you can just run "sloccount DIRECTORY" and all the source code
in the directory and its descendants will be counted.

%prep
%setup

%build
make

%install
rm -rf ${RPM_BUILD_ROOT}
mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
mkdir -p ${RPM_BUILD_ROOT}%{_mandir}/man1
make install_programs PREFIX=${RPM_BUILD_ROOT}%{_prefix}
make install_man PREFIX=${RPM_BUILD_ROOT}%{_prefix}

%clean
rm -rf ${RPM_BUILD_ROOT}

%files
%defattr(-, root, root)
%doc sloccount.html README ChangeLog COPYING TODO
%{_bindir}/*
%{_mandir}/*/*
