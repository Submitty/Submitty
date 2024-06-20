# Cypress has been split up into five groups that run separately.

cypress-system is where all the tests that require system changes to occur before the tests can be run, that being the autograding tests, different login tests, etc.
This is also where the accessibility test has been placed, to free space for other tests in the Admin folder.

Cypress-Admin is all the tests that are 'top level' system tests that don't require changes to the system, or instructor level tests.

Cypress-Feature is all the tests that focus on features of Submitty, that arent directly related to creating, editing, submitting to, or grading gradeables. 

Cypress-Gradeable is all the tests that ARE directly related to creating, editing, submitting to, or grading gradeables. 

Cypress-UI is all the tests that primarily focus on ensuring the correct UI is displayed to users.


The spec files in these folders can be moved around at a later date if necessary. If the names of the folders need to change, you have to edit the name of the containers matrix in the submitty_ci.yml Github Actions file in the .github/workflows folder. 

If you wish to rename Cypress-UI to Cypres-UserInterface, for example, in the submitty_ci.yml, replace "UI" with "UserInterface", and this will update the CI to find the new folders. 