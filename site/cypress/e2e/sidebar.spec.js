import { getCurrentSemester } from '../support/utils.js';

const currentSemester = `${getCurrentSemester()}`;

Cypress.Commands.add('sidebarContains', (title, extension) => {
    cy.get('aside ul li').contains(title).should('have.attr', 'href').and('contain', extension);
    if (!title.includes('Logout')) {
        cy.get('aside ul li').contains(title).click();
        if (title.includes('Autograding')) {
            cy.get('#main > .content > h1').should('contain', 'Job Statistics');
        }
        else if (title.includes('Discussion Forum')) {
            cy.get('#main > .content').should('contain', 'Create Thread');
        }
        else if (title.includes('Collapse Sidebar')) {
            return;
        }
        else {
            cy.get('#main > .content').should('contain', title);
        }
    }
});

Cypress.Commands.add('testBaseSidebar', (user) => {
    cy.sidebarContains('My Courses', '/home');
    cy.sidebarContains('My Profile', '/user_profile');
    cy.sidebarContains('Authentication Tokens', '/authentication_tokens');
    cy.sidebarContains('Calendar', '/calendar');
    if (user.includes('instructor')) {
        cy.sidebarContains('Docker', '/admin/docker');
        cy.sidebarContains('New Course', '/home/courses/new');
        cy.sidebarContains('Autograding Status', '/autograding_status');
    }
    cy.sidebarContains('Logout', '/authentication/logout');
    
});

Cypress.Commands.add('testCourseSidebar', (user, course) => {
    cy.login(user);
    cy.visit([course]);
    cy.sidebarContains('Gradeables', `/courses/${currentSemester}/sample`);
    cy.sidebarContains('Notifications', `/courses/${currentSemester}/sample/notifications`);
    cy.sidebarContains('Office Hours Queue', `/courses/${currentSemester}/sample/office_hours_queue`);
    cy.sidebarContains('Polls', `/courses/${currentSemester}/sample/polls`);
    cy.sidebarContains('Course Materials', `/courses/${currentSemester}/sample/course_materials`);
    cy.sidebarContains('Discussion Forum', `/courses/${currentSemester}/sample/forum`);
    cy.sidebarContains('My Late Day', `/courses/${currentSemester}/sample/late_table`);

    if (user != 'student') {
        cy.sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
        if (user != 'ta') {
            cy.sidebarContains('New Gradeable', `/courses/${currentSemester}/sample/gradeable`);
            cy.sidebarContains('Course Settings', `/courses/${currentSemester}/sample/config`);
            cy.sidebarContains('SQL Toolbox', `/courses/${currentSemester}/sample/sql_toolbox`);
            cy.sidebarContains('Email Status', `/courses/${currentSemester}/sample/email_status`);

            cy.sidebarContains('Manage Students', `/courses/${currentSemester}/sample/users`);
            cy.sidebarContains('Manage Graders', `/courses/${currentSemester}/sample/graders`);
            cy.sidebarContains('Sections', `/courses/${currentSemester}/sample/sections`);
            cy.sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
            cy.sidebarContains('Student Activity Dashboard', `/courses/${currentSemester}/sample/activity`);

            cy.sidebarContains('Late Days Allowed', `/courses/${currentSemester}/sample/late_days`);
            cy.sidebarContains('Excused Absence Extensions', `/courses/${currentSemester}/sample/extensions`);
            cy.sidebarContains('Grade Override', `/courses/${currentSemester}/sample/grade_override`);
            cy.sidebarContains('Plagiarism Detection', `/courses/${currentSemester}/sample/plagiarism`);
            cy.sidebarContains('Grade Reports', `/courses/${currentSemester}/sample/reports`);
        }
    }
    cy.testBaseSidebar(user);
});

describe('Test sidebars', () => {
    ['student', 'ta', 'instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });
        it(`Test ${user} home sidebars`, () => {
            cy.login(user);
            cy.testBaseSidebar(user);
        });

        // Sample Course
        it(`Test ${user} sample course sidebar`, () => {
            cy.testCourseSidebar(user, 'sample');
        });
    });
});
