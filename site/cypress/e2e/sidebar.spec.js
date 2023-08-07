import { getCurrentSemester } from '../support/utils.js';

const currentSemester = getCurrentSemester();

function sidebarContains(title, extension, header = title) {
    cy.get('[data-testid="sidebar"]').contains(title).should('have.attr', 'href').and('contain', extension);
    cy.visit(extension);
    cy.get('#main > .content').should('contain', header);
}

function baseSidebar() {
    sidebarContains('My Courses', '/home');
    sidebarContains('My Profile', '/user_profile');
    sidebarContains('Calendar', '/calendar');
    cy.get('[data-testid="sidebar"]').contains('Collapse Sidebar').should('exist');
    cy.get('[data-testid="sidebar"]').contains('Logout').should('exist');
}

function instructorSidebar() {
    sidebarContains('New Gradeable', `/courses/${currentSemester}/sample/gradeable`);
    sidebarContains('Course Settings', `/courses/${currentSemester}/sample/config`);
    sidebarContains('SQL Toolbox', `/courses/${currentSemester}/sample/sql_toolbox`);
    sidebarContains('Email Status', `/courses/${currentSemester}/sample/email_status`);

    sidebarContains('Manage Students', `/courses/${currentSemester}/sample/users`);
    sidebarContains('Manage Graders', `/courses/${currentSemester}/sample/graders`);
    sidebarContains('Manage Sections', `/courses/${currentSemester}/sample/sections`, 'Manage Registration Sections');
    sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
    sidebarContains('Student Activity Dashboard', `/courses/${currentSemester}/sample/activity`);

    sidebarContains('Late Days Allowed', `/courses/${currentSemester}/sample/late_days`);
    sidebarContains('Excused Absence Extensions', `/courses/${currentSemester}/sample/extensions`);
    sidebarContains('Grade Override', `/courses/${currentSemester}/sample/grade_override`);
    sidebarContains('Plagiarism Detection', `/courses/${currentSemester}/sample/plagiarism`);
    sidebarContains('Grade Reports', `/courses/${currentSemester}/sample/reports`);
    sidebarContains('Docker', '/admin/docker');
    sidebarContains('New Course', '/home/courses/new');
    sidebarContains('Autograding Status', '/autograding_status', 'Job Statistics');
}

function notHaveInstructorSidebars() {
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
}
function baseCourseSidebar(user, course) {
    cy.visit('/');
    cy.login(user);
    cy.visit([course]);
    sidebarContains('Gradeables', `/courses/${currentSemester}/sample`);
    sidebarContains('Notifications', `/courses/${currentSemester}/sample/notifications`);
    sidebarContains('Office Hours Queue', `/courses/${currentSemester}/sample/office_hours_queue`);
    sidebarContains('Polls', `/courses/${currentSemester}/sample/polls`);
    sidebarContains('Course Materials', `/courses/${currentSemester}/sample/course_materials`);
    sidebarContains('Discussion Forum', `/courses/${currentSemester}/sample/forum`, 'Create Thread');
    sidebarContains('My Late Day', `/courses/${currentSemester}/sample/late_table`);
}

describe('Test sidebars', () => {
    // Sample Course
    it('Test student sidebars', () => {
        baseCourseSidebar('student', 'sample');
        baseSidebar();
        notHaveInstructorSidebars();
        cy.visit(['sample']);
        cy.get('[data-testid="sidebar"]').contains('Student Photos').should('not.exist');
    });

    it('Test ta sidebars', () => {
        baseCourseSidebar('ta', 'sample');
        baseSidebar();
        cy.visit(['sample']);
        sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
        notHaveInstructorSidebars();
    });

    it('Test instructor sidebars', () => {
        baseCourseSidebar('instructor', 'sample');
        instructorSidebar();
        baseSidebar();
        cy.visit(['sample']);
        sidebarContains('Student Photos', `/courses/${currentSemester}/sample/student_photos`);
    });

});
