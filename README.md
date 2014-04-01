## BACKGROUND
The homework server started out as a simple server for uploading student assignments for courses like Data Structures (CSCI 1200) and Computer Science I (CSCI 1100). Over time, the server has evolved and gained features for submission and grading which are used today for supporting more than 500 students at times for weekly homework. Because of the way the server is currently designed, creating scripts for grading homework and regrading homework are possible but require a significant amount of time for instructors. The server is currently managed by RPI lab staff and Professor Cutler from the Computer Science department. The goal of the project is to research and create algorithms to effectively grade different types of homework based on student output on submission. We will accomplish this by creating a modularized system for grading that improves the ability to create homework assignments on the server and by reducing the number of custom scripts required. Improvements to the server’s user interface will also be made to the student side to allow student to more effectively view their grades on assignment and improve the ability to submit homework.
## LICENSING
The current homework server is not open source and parts of the server for security reasons may continue to be closed source however, changes to the autograding system will be released under the BSD "3-Clause" License. A duplicate server will be created for development to allow students involved to work freely without disrupting the students currently enrolled in Data Structures and CS I.
## TECHNOLOGIES USED
The following technologies will be used in the project:
*	Perl (Server, Security, Majority of Legacy Code)
*	C, C++ (Autograding algorithms portion and Security)
*	HTML, CSS, PHP (Frontend)

## GOALS
*	Establish a test server for running previous homework submissions on new algorithms
*	Clean out bloat-code, tidy up existing code, and comment
*	Develop a set of algorithms that can become modules for the instructor to use without having to write custom scripts
*	Improve UX for the Instructor when creating homework assignments.
*	Improve the UX for students when reviewing past homework submissions and homework submission workflow

## Mid-Semester Update
* Reviewed legacy code and determined system design changes that were neccessary
* Researched and developed baseline algorithms for preliminary testing
* Created a preliminary redesign for the student interface
* Currently developing a test system to analyze the fairness and efficiency of grading algorithms

