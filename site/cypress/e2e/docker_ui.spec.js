const docker_ui_path = '/admin/docker';

describe('Docker UI Test', () => {
    beforeEach(() => {
        cy.visit('/');
        cy.login();
        cy.visit(docker_ui_path);
    });

    it('Should update the machine information', () => {
        // Click "Update dockers and machines" button
        cy.get('#update-machines')
            .should('have.text', ' Update dockers and machines')
            .click();
        cy.wait(100);
        // Should prompt a success message
        cy.get('.alert-success')
            .invoke('text')
            .should('contain', 'Successfully queued the system to update' +
                ' docker, please refresh the page in a bit.');

        // Allow the system to update the info and reload
        cy.wait(10000);
        cy.reload();

        // Updated time should not be "Unknown"
        cy.get(':nth-child(1) > p')
            .should('not.have.text', 'Unknown');
        // Updated OS info should not be empty
        cy.get('.machine-table > tbody:nth-child(1) > tr:nth-child(3) > td:nth-child(3)')
            .should('not.be.empty');
        // Updated docker version should not be "Error"
        cy.get('.machine-table > tbody:nth-child(1) > tr:nth-child(3) > td:nth-child(4)')
            .should('not.have.text', 'Error');
    });

    it('Should filter images with tags', () => {
        // These tags have no images
        ['cpp', 'et-cetera', 'notebook', 'python'].forEach((tag) => {
            cy.get(`button[data-capability="${tag}"]`)
                .click();
            cy.get('.image-row')
                .should('not.be.visible');
        });
        // Default filter should have all images
        cy.get("button[data-capability='default']")
            .click();
        cy.get('.image-row')
            .should('be.visible');
    });

    it('Should not add invalid image', () => {
        // Check invalid format
        cy.get('#add-field')
            .clear()
            .type('submitty/invalid-image');
        cy.get('#docker-warning')
            .should('be.visible');
        cy.get('#send-button')
            .should('be.disabled');

        // Check valid format but invalid image
        cy.get('#add-field')
            .clear()
            .type('submitty/invalid-image:0.0');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.wait(100);
        cy.get('.alert-error')
            .should('have.text', 'submitty/invalid-image not found on DockerHub');
    });

    it('Should link existed image to a new tag', () => {
        // Check empty tag list, should have `cpp'
        cy.get('#capabilities-list')
            .contains('cpp');
        // Check valid format and valid image
        cy.get('#capability-form')
            .select('cpp');
        cy.get('#add-field')
            .clear()
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.wait(100);
        cy.get('.alert-success')
            .should('have.text', 'submitty/autograding-default:latest' +
                  ' found on DockerHub and queued to be added!');

        cy.wait(10000);
        cy.reload();

        // Check the empty tag list
        cy.get('#capabilities-list')
            .should('not.have.text', 'cpp');

        // Try to add it again, should fail
        cy.get('#add-field')
            .clear()
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.wait(100);
        cy.get('.alert-error')
            .should('have.text', 'submitty/autograding-default:latest ' +
                    'already exists in capability cpp');
    });

    it('Should add new image', () => {
        cy.reload();
        // Add a new image
        cy.get('#capability-form')
            .select('python');
        cy.get('#add-field')
            .clear()
            .type('submitty/python:2.7');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();
        cy.get('.alert-success')
            .should('have.text', 'submitty/python:2.7 found on DockerHub' +
                    ' and queued to be added!');
    });
});
