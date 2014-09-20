CONFIG=.
ROOT=/projects/submit3
COURSE=csci1200
RCOS=/projects/submit3/csci1200/code/rcos
HW=lab1
#gcc=g++ -std=c++0x
gcc=clang++

bindir=$(ROOT)/$(COURSE)/bin/$(HW)



validate_files=$(RCOS)/grading/Validator.cpp \
               $(RCOS)/modules/tokens.cpp \
	       $(RCOS)/modules/tokenSearch.cpp \
	       $(RCOS)/modules/diffNaive.cpp \
	       $(RCOS)/modules/myersDiff.cpp \
	       $(RCOS)/modules/textMasking.cpp \
	       $(RCOS)/modules/clean.cpp \
	       $(RCOS)/modules/difference.cpp \
	       $(RCOS)/grading/TestCase.cpp

runner_files=$(RCOS)/grading/runner.cpp \
               $(RCOS)/modules/tokens.cpp \
	       $(RCOS)/modules/tokenSearch.cpp \
	       $(RCOS)/modules/diffNaive.cpp \
	       $(RCOS)/modules/myersDiff.cpp \
	       $(RCOS)/modules/textMasking.cpp \
	       $(RCOS)/modules/clean.cpp \
	       $(RCOS)/modules/difference.cpp \
	       $(RCOS)/grading/TestCase.cpp


config_files=$(RCOS)/grading/config.cpp \
               $(RCOS)/modules/tokens.cpp \
	       $(RCOS)/modules/tokenSearch.cpp \
	       $(RCOS)/modules/diffNaive.cpp \
	       $(RCOS)/modules/myersDiff.cpp \
	       $(RCOS)/modules/textMasking.cpp \
	       $(RCOS)/modules/clean.cpp \
	       $(RCOS)/modules/difference.cpp \
	       $(RCOS)/grading/TestCase.cpp


assignment:
	test -d $(bindir) || mkdir $(bindir)
#	$(gcc) -DDEBUG -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/Validator.cpp  $(RCOS)/grading/TestCase.cpp  -o $(bindir)/validate.out
	$(gcc) -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(validate_files) -o $(bindir)/validate.out
	$(gcc) -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(runner_files)   -o $(bindir)/run.out
	$(gcc) -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(config_files)   -o $(bindir)/configure.out
	#valgrind 
	$(bindir)/configure.out assignment_config.json

clean:
	rm $(ROOT)/$(COURSE)/bin/*
	rm assignment_config.json
