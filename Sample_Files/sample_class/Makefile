CONFIG=$(dirname $0)
CURDIR=$(CONFIG)
RCOS=$(CURDIR)/../../rcos
# just the last part of the current directory
HW=$(notdir $(CURDIR))
bindir=$(CURDIR)/../../../bin/$(HW)
coursedir=$(CURDIR)/../../../

gcc=clang++
#gcc=g++ -std=c++11

default: assignment run

all: assignment run

config_files=$(RCOS)/grading/config.cpp \
		$(RCOS)/grading/TestCase.cpp \
		$(RCOS)/modules/myersDiff.cpp \
		$(RCOS)/modules/tokens.cpp \
		$(RCOS)/modules/tokenSearch.cpp \
		$(RCOS)/modules/diffNaive.cpp \
		$(RCOS)/modules/textMasking.cpp \
		$(RCOS)/modules/clean.cpp \
		$(RCOS)/modules/difference.cpp

compile_files=$(RCOS)/grading/compile.cpp \
		$(RCOS)/modules/tokens.cpp \
		$(RCOS)/modules/tokenSearch.cpp \
		$(RCOS)/modules/diffNaive.cpp \
		$(RCOS)/modules/myersDiff.cpp \
		$(RCOS)/modules/textMasking.cpp \
		$(RCOS)/modules/clean.cpp \
		$(RCOS)/modules/difference.cpp \
		$(RCOS)/grading/execute.cpp \
		$(RCOS)/grading/TestCase.cpp

runner_files=$(RCOS)/grading/runner.cpp \
		$(RCOS)/grading/TestCase.cpp \
		$(RCOS)/modules/tokens.cpp \
		$(RCOS)/modules/tokenSearch.cpp \
		$(RCOS)/modules/diffNaive.cpp \
		$(RCOS)/modules/myersDiff.cpp \
		$(RCOS)/modules/textMasking.cpp \
		$(RCOS)/modules/clean.cpp \
		$(RCOS)/modules/difference.cpp \
		$(RCOS)/grading/execute.cpp


validate_files=$(RCOS)/grading/Validator.cpp \
		$(RCOS)/modules/tokens.cpp \
		$(RCOS)/modules/tokenSearch.cpp \
		$(RCOS)/modules/diffNaive.cpp \
		$(RCOS)/modules/myersDiff.cpp \
		$(RCOS)/modules/textMasking.cpp \
		$(RCOS)/modules/clean.cpp \
		$(RCOS)/modules/difference.cpp \
		$(RCOS)/grading/execute.cpp \
		$(RCOS)/grading/TestCase.cpp


assignment:
	echo 'making hw' $(HW)
	test -d $(bindir) || mkdir $(bindir)
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(config_files)   -o $(bindir)/configure.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(compile_files)  -o $(bindir)/compile.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(runner_files)   -o $(bindir)/run.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CURDIR) $(validate_files) -o $(bindir)/validate.out

run:
#	valgrind
	$(bindir)/configure.out $(bindir)/assignment_config.json

move:
	cp $(bindir)/assignment_config.json $(coursedir)/config/$(HW)_assignment_config.json
	cp -r $(CURDIR)/test_input $(coursedir)/test_input/$(HW)
	cp -r $(CURDIR)/test_output $(coursedir)/test_output/$(HW)

clean:
#	rm $(ROOT)/$(COURSE)/bin/*
	rm $(bindir)
	rm assignment_config.json
