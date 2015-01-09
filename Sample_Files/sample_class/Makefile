CONFIG=$(dirname $0)

#Source of repository
RCOS=$(CONFIG)/../../../../rcos

# just the last part of the current directory
HW=$(notdir $(CONFIG))

# HW id
ID=$(HW)

# The class directory
coursedir=$(CONFIG)/../../..

# Where the compiled binaries are stored, under the class directory
class_bin=$(coursedir)/bin/$(ID)

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
	@ echo "coursedir: " $(coursedir)
	@ echo "class_bin: " $(class_bin)
	@ echo "ID: " $(ID)
	@ echo "HW: " $(HW)
	@ echo "RCOS: " $(RCOS)
	@ echo "CONFIG: " $(CONFIG)
	echo 'making hw'  $(HW)
	test -d $(class_bin) || mkdir $(class_bin)
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CONFIG) $(config_files)   -o $(class_bin)/configure.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CONFIG) $(compile_files)  -o $(class_bin)/compile.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CONFIG) $(runner_files)   -o $(class_bin)/run.out
	$(gcc)  -g -DDEBUG -I $(RCOS) -I $(CONFIG) $(validate_files) -o $(class_bin)/validate.out

run:
#	valgrind
	$(class_bin)/configure.out $(class_bin)/assignment_config.json

move:
	cp $(class_bin)/assignment_config.json $(coursedir)/config/$(ID)_assignment_config.json
	cp -r $(CONFIG)/test_input $(coursedir)/test_input/$(ID)
	cp -r $(CONFIG)/test_output $(coursedir)/test_output/$(ID)

clean:
#	rm $(ROOT)/$(COURSE)/bin/*
	rm $(class_bin)
	rm assignment_config.json
