CONFIG=.
ROOT=/projects/submit3
COURSE=csci1200
RCOS=/projects/submit3/csci1200/code/rcos
#gcc=g++ -std=c++0x
gcc=clang

assignment:
	$(gcc) -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/Validator.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)validate.out
	$(gcc) -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/runner.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)run.out
	$(gcc) -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/config.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)configure.out
	$(ROOT)/$(COURSE)/bin/$(HW)configure.out assignment_config.json

clean:
	rm $(ROOT)/$(COURSE)/bin/*
	rm assignment_config.json
