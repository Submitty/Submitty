HW=HW1
COURSE=csci1200
assignment:
	g++ -include config.h ../../rcos/SCRIPTS/Validator.cpp -o ../../../../$(COURSE)/bin/$(HW)validate.out
	g++ -include config.h ../../rcos/SCRIPTS/runner.cpp -o ../../../../$(COURSE)/bin/$(HW)run.out
	g++ -include config.h ../../rcos/SCRIPTS/config.cpp -o ../../../../$(COURSE)/bin/$(HW)configure.out
	../../../../$(COURSE)/bin/$(HW)configure.out assignment_config.json

