CONFIG=.
ROOT=/projects/submit3
COURSE=csci1200
RCOS=/projects/submit3/csci1200/code/rcos
HW=hw1
#gcc=g++ -std=c++0x
gcc=clang++

bindir=$(ROOT)/$(COURSE)/bin/$(HW)

assignment:
	test -d $(bindir) || mkdir $(bindir)
#	$(gcc) -DDEBUG -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/Validator.cpp  $(RCOS)/grading/TestCase.cpp  -o $(bindir)/validate.out
	$(gcc) -g -DDEBUG -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/Validator.cpp   -o $(bindir)/validate.out
	$(gcc) -g -DDEBUG -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/runner.cpp    -o $(bindir)/run.out
	$(gcc) -g -DDEBUG -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/config.cpp    -o $(bindir)/configure.out
	$(bindir)/configure.out assignment_config.json

clean:
	rm $(ROOT)/$(COURSE)/bin/*
	rm assignment_config.json
