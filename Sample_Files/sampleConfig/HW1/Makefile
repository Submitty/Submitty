CONFIG=.
ROOT=~/Documents/hwserver
COURSE=csci1200
RCOS=$(ROOT)/$(COURSE)/code/rcos


assignment:
	g++ -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/Validator.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)validate.out
	g++ -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/runner.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)run.out
	g++ -I $(RCOS) -include $(CONFIG)/config.h $(RCOS)/grading/config.cpp -o $(ROOT)/$(COURSE)/bin/$(HW)configure.out
	$(ROOT)/$(COURSE)/bin/$(HW)configure.out assignment_config.json

clean:
	rm $(ROOT)/$(COURSE)/bin/*
	rm assignment_config.json
