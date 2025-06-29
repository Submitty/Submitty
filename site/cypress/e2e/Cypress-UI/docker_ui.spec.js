const docker_ui_path = '/admin/docker';

/**
 * This test is designed for a single run --
 * It will not work if the system is already updated.
 *
 * To revert the states:
 *  - pushd /usr/local/submitty/config
 *  - jq '{default: .default}' autograding_containers.json | sponge autograding_containers.json
 *  - chown submitty_php:submitty_daemonphp autograding_containers.json
 *  - popd
 *
 * If `sponge' command is missing, install `moreutils' package, or edit the file manually:
 * {
 *     "default": [
 *         "submitty/autograding-default:latest",
 *         "submitty/python:latest",
 *         "submitty/clang:latest",
 *         "submitty/gcc:latest",
 *         "submitty/rust:latest",
 *         "submitty/java:latest",
 *         "submitty/pdflatex:latest"
 *     ],
 *     "python": [
 *         "submitty/autograding-default:latest",
 *         "submitty/python:latest"
 *     ],
 *     "cpp": [
 *         "submitty/autograding-default:latest",
 *         "submitty/clang:latest",
 *         "submitty/gcc:latest"
 *     ],
 *     "notebook": [
 *         "submitty/autograding-default:latest"
 *     ]
 * }
 */

describe('Docker UI Test', () => {
    beforeEach(() => {
        cy.login();
        cy.visit(docker_ui_path);
    });

    it('Should update the machine information', () => {
        // Click "Update dockers and machines" button
        cy.get('#update-machines')
            .should('have.text', ' Update dockers and machines')
            .click();
        // Should prompt a success message
        cy.get('.alert-success')
            .invoke('text')
            .should('contain', 'Successfully queued the system to update'
            + ' docker, please refresh the page in a bit.');

        // Allow the system to update the info and reload
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('[data-testid="docker-version"]')
                .invoke('text')
                .then((text) => {
                    return text !== 'Unknown';
                });
        }, 10000);
        // Updated time should not be "Unknown"
        cy.get('[data-testid="systemwide-info"]')
            .should('not.contain.text', 'Unknown');
        // Updated OS info should not be empty
        cy.get('[data-testid="system-info"]')
            .should('not.be.empty');
        // Updated docker version should not be "Unknown"
        cy.get('[data-testid="docker-version"]')
            .should('not.contain.text', 'Unknown');
    });

    it('Should filter images with tags', () => {
        // This tag has no images
        cy.get('button[data-capability=\'et-cetera\']')
            .click();
        cy.get('.image-row')
            .should('not.be.visible');
        // Default filter should have all images
        cy.get('button[data-capability=\'default\']')
            .click();
        cy.get('.image-row')
            .should('be.visible');
    });

    it('Should not add invalid image', () => {
        // Check invalid format
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/invalid-image');
        cy.get('#docker-warning')
            .should('be.visible');
        cy.get('#send-button')
            .should('be.disabled');

        // Check valid format but invalid image
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/invalid-image:0.0');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.get('.alert-error')
            .should('have.text', 'submitty/invalid-image not found on DockerHub');
    });

    it('Should link existed image to a new tag', () => {
        // Check empty tag list, should have `et-cetera'
        cy.get('#capabilities-list')
            .contains('et-cetera');
        // Check valid format and valid image
        cy.get('#capability-form')
            .select('et-cetera');
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        // Check success message for adding to config
        cy.get('.alert-success')
            .should('contain.text', 'submitty/autograding-default:latest has been added to the configuration!');

        // Update the machine to link existing image to a new tag
        cy.get('#update-machines').click();
        cy.get('.alert-success')
            .should('contain.text', 'Successfully queued the system to update');

        // Allow the system to update the info and reload
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('#capabilities-list')
                .invoke('text')
                .then((text) => {
                    return !text.includes('et-cetera');
                });
        }, 10000, 500);

        // Check the empty tag list
        cy.get('#capabilities-list')
            .should('not.contain.text', 'et-cetera');

        // Try to add it again, should fail
        cy.get('#capability-form')
            .select('et-cetera');
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.get('.alert-error')
            .should('have.text', 'submitty/autograding-default:latest '
            + 'already exists in capability et-cetera');
    });

    // NOTE: Can be refactored later to speed up the Cypress test since
    //       we need to wait for the system to install the image
    //       Currently, using one of the smaller images submitty/prolog:8.
    it('Should add new image and remove it', () => {
        cy.reload();
        // Add a new image
        cy.get('#capability-form')
            .select('python');
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/prolog:8');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();
        // Check success message for adding to config
        cy.get('.alert-success')
            .should('contain.text', 'submitty/prolog:8 has been added to the configuration!');

        // Update the machine to pull the image
        cy.get('#update-machines').click();
        cy.get('.alert-success')
            .should('contain.text', 'Successfully queued the system to update');

        // Allow the system to install the image and update UI
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('body').then(($body) => {
                const exists = $body.find('[data-image-id="submitty/prolog:8"]').length > 0;
                return exists;
            });
        }, 10000, 500);

        // Check if the image can be removed
        cy.get('[data-image-id="submitty/prolog:8"]')
            .should('contain.text', 'Remove');

        // Remove the image
        cy.get('[data-image-id="submitty/prolog:8"]')
            .should('be.visible')
            .click();
        cy.get('.alert-success')
            .should('contain.text', 'submitty/prolog:8 has been removed from the configuration.');

        // Confirm dialog return true
        cy.on('window:confirm', () => true);

        // Update the machine to remove the image
        cy.get('#update-machines').click();
        cy.get('.alert-success')
            .should('contain.text', 'Successfully queued the system to update');

        cy.get('[data-image-id="submitty/prolog:8"]')
            .should('not.exist');
    });
});
