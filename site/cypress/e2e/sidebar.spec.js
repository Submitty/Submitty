import { getCurrentSemester } from '../support/utils.js';

const currentSemester = `${getCurrentSemester()}`;

Cypress.Commands.add('sidebarContains', (title, extension, header = title) => {
    cy.get('[data-testid="sidebar"]').contains(title).should('have.attr', 'href').and('contain', extension);
    cy.visit(extension);
    cy.get('#main > .content').should('contain', header);
});

Cypress.Commands.add('baseSidebar', () => {
    cy.sidebarContains('My Courses', '/home');
    cy.sidebarContains('My Profile', '/user_profile');
    cy.sidebarContains('Authentication Tokens', '/authentication_tokens');
    cy.sidebarContains('Calendar', '/calendar');
    cy.get('[data-testid="sidebar"]').contains('Collapse Sidebar').should('exist');
    cy.get('[data-testid="sidebar"]').contains('Logout').should('exist'); 
});

Cypress.Commands.add('instructorSidebar', () => {
    cy.sidebarContains('New Gradeable', `/courses/${currentSemester}/sample/gradeable`);
    cy.sidebarContains('Course Settings', `/courses/${currentSemester}/sample/config`);
    cy.sidebarContains('SQL Toolbox', `/courses/${currentSemester}/sample/sql_toolbox`);
    cy.sidebarContains('Email Status', `/courses/${currentSemester}/sample/email_status`);

    cy.sidebarContains('Manage Students', `/courses/${currentSemester}/sample/users`);
    cy.sidebarContains('Manage Graders', `/courses/${currentSemester}/sample/graders`);
    cy.sidebarContains('Manage Sections', `/courses/${currentSemester}/sample/sections`, 'Manage Registration Sections');
    cy.sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
    cy.sidebarContains('Student Activity Dashboard', `/courses/${currentSemester}/sample/activity`);

    cy.sidebarContains('Late Days Allowed', `/courses/${currentSemester}/sample/late_days`);
    cy.sidebarContains('Excused Absence Extensions', `/courses/${currentSemester}/sample/extensions`);
    cy.sidebarContains('Grade Override', `/courses/${currentSemester}/sample/grade_override`);
    cy.sidebarContains('Plagiarism Detection', `/courses/${currentSemester}/sample/plagiarism`);
    cy.sidebarContains('Grade Reports', `/courses/${currentSemester}/sample/reports`);
    cy.sidebarContains('Docker', '/admin/docker');
    cy.sidebarContains('New Course', '/home/courses/new');
    cy.sidebarContains('Autograding Status', '/autograding_status', 'Job Statistics');
});

Cypress.Commands.add('notHaveInstructorSidebars', () => {
    cy.get('[data-testid="sidebar"]').contains('New Gradeable').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Course Settings').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Email Status').should('not.exist');

    cy.get('[data-testid="sidebar"]').contains('Manage Students').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Manage Graders').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Manage Sections').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Email Status').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Student Activity Dashboard').should('not.exist');

    cy.get('[data-testid="sidebar"]').contains('Late Days Allowed').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Excused Absence Extensions').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Grade Override').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Plagiarism Detection').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Grade Reports').should('not.exist');

    cy.get('[data-testid="sidebar"]').contains('Docker').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('New Course').should('not.exist');
    cy.get('[data-testid="sidebar"]').contains('Autograding Status').should('not.exist');
});

Cypress.Commands.add('baseCourseSidebar', (user, course) => {
    cy.visit('/');
    cy.login(user);
    cy.visit([course]);
    cy.sidebarContains('Gradeables', `/courses/${currentSemester}/sample`);
    cy.sidebarContains('Notifications', `/courses/${currentSemester}/sample/notifications`);
    cy.sidebarContains('Office Hours Queue', `/courses/${currentSemester}/sample/office_hours_queue`);
    cy.sidebarContains('Polls', `/courses/${currentSemester}/sample/polls`);
    cy.sidebarContains('Course Materials', `/courses/${currentSemester}/sample/course_materials`);
    cy.sidebarContains('Discussion Forum', `/courses/${currentSemester}/sample/forum`, 'Create Thread');
    cy.sidebarContains('My Late Day', `/courses/${currentSemester}/sample/late_table`);
});

describe('Test sidebars', () => {
    // Sample Course
    it(`Test student sidebars`, () => {
        cy.baseCourseSidebar('student', 'sample');
        cy.baseSidebar();
        cy.notHaveInstructorSidebars();
        cy.get('[data-testid="sidebar"]').contains('Student Photos').should('not.exist');
    });

    it(`Test ta sidebars`, () => {
        cy.baseCourseSidebar('ta', 'sample');
        cy.baseSidebar();
        cy.visit(['sample']);
        cy.sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
        cy.notHaveInstructorSidebars();
    });

    it(`Test instructor sidebars`, () => {
        cy.baseCourseSidebar('instructor', 'sample');
        cy.instructorSidebar();
        cy.baseSidebar();
        cy.visit(['sample']);
        cy.sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
    });

});
