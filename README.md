_If you would just like to run the project, take a look at [the setup page](https://github.com/JFrei86/HWserver/wiki/Setup)._
[![Build Status](https://travis-ci.org/RCOS-Grading-Server/HWserver.svg?branch=develop)](https://travis-ci.org/RCOS-Grading-Server/HWserver)

# BACKGROUND
The homework server started out as a simple server for uploading student assignments for courses like Data Structures (CSCI 1200) and Computer Science I (CSCI 1100). Over time, the server has evolved and gained features for submission and grading which are used today for supporting more than 500 students at times for weekly homework. Because of the way the server is currently designed, creating scripts for grading homework and regrading homework are possible but require a significant amount of time for instructors. The server is currently managed by RPI lab staff and Professor Cutler from the Computer Science department. The goal of the project is to research and create algorithms to effectively grade different types of homework based on student output on submission. We will accomplish this by creating a modularized system for grading that improves the ability to create homework assignments on the server and by reducing the number of custom scripts required. Improvements to the server’s user interface will also be made to the student side to allow student to more effectively view their grades on assignment and improve the ability to submit homework.

### LICENSING
All non-RPI specific parts of the homework server are under the BSD "3-Clause" License.

### TECHNOLOGIES USED
The following technologies will be used in the project:
*	C++ (Autograding algorithms portion and Security)
*	HTML, CSS, PHP (Frontend)
* 	Bash, Make (Backend/Setup Scripts)

### GOALS
*	Establish a test server for running previous homework submissions on new algorithms
*	Clean out bloat-code, tidy up existing code, and comment
*	Develop a set of algorithms that can become modules for the instructor to use without having to write custom scripts
*	Improve UX for the Instructor when creating homework assignments.
*	Improve the UX for students when reviewing past homework submissions and homework submission workflow

# HOW TO USE
### Setup
To setup a new server, take a look at [the setup page](https://github.com/JFrei86/HWserver/wiki/Setup), or set up according to [the manual setup page](https://github.com/JFrei86/HWserver/wiki/Manual-Setup)

### Adding new assignment
To add a new assignment, put it in _"$YourClass/code/build/$hwID"_, the test inputs into _"$YourClass/code/build/$hwID/test_input"_, and the test outputs into _"$YourClass/code/build/$hwID/test_output"_ and then run ```make all move CONFIG=$hwID```

Make sure to also add an entry to _"csci1200/config/class.json"_

### Grading
Submissions go to _"$YourClass/submissions/$hwID/$username/$SubmisionNumber"_
Grades and outputs go to _"$YourClass/results/$hwID/$username/$SubmisionNumber"_

#### Autograder
To start the automatic grader, type (where bindir is the parent directory) ```bindir/csci1200/code/rcos/bashScript/grade_students.sh binder to_be_graded```

#### Re-grade
Just run ```touch to_be_graded/$YourClass__$hwID__$username__$SubmisionNumber```
