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
 *         "submitty/java:latest",
 *         "submitty/pdflatex:latest",
 *     ]
 * }
 * NOTE: sysinfo log is currently broken, so docker version will always show Error. Once this is fixed,
 * we should uncomment the relevant test.
 */

describe('Docker UI Test', () => {
    beforeEach(() => {
        cy.login();
        cy.visit(docker_ui_path);
    });
    // !DEPRECATED: Installer will also update the docker info
    // it('Should be the first update', () => {
    //     // No info update should be made before this test...
    //     // Check if the update time is "Unknown"
    //     cy.get(':nth-child(1) > p')
    //         .should('contain.text', 'Unknown');
    //     // Check if the OS info is empty
    //     cy.get('.machine-table > tbody:nth-child(1) > tr:nth-child(3) > td:nth-child(3)')
    //         .invoke('text')
    //         .should('match', /[\n ]*/);
    // });

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
        // NOTE: Will currently always be Error. Fix sysinfo logging to fix this.
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('[data-testid="docker-version"]')
                .invoke('text')
                .then((text) => {
                    return text !== 'Error';
                });
        }, 10000);
        // Updated time should not be "Unknown"
        cy.get('[data-testid="systemwide-info"]')
            .should('not.contain.text', 'Unknown');
        // Updated OS info should not be empty
        cy.get('[data-testid="system-info"]')
            .should('not.be.empty');
        // Updated docker version should not be "Error"
        cy.get('[data-testid="docker-version"]')
            .should('not.contain.text', 'Error');
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
        // Check empty tag list, should have `cpp'
        cy.get('#capabilities-list')
            .contains('cpp');
        // Check valid format and valid image
        cy.get('#capability-form')
            .select('cpp');
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.get('.alert-success')
            .should('have.text', 'submitty/autograding-default:latest'
            + ' found on DockerHub and queued to be added!');

        // Allow the system to update the info and reload
        // eslint-disable-next-line no-restricted-syntax
        cy.waitAndReloadUntil(() => {
            return cy.get('#capabilities-list')
                .invoke('text')
                .then((text) => {
                    return !text.includes('cpp');
                });
        }, 10000);

        // Check the empty tag list
        cy.get('#capabilities-list')
            .should('not.contain.text', 'cpp');

        // Try to add it again, should fail
        cy.get('#add-field')
            .clear();
        cy.get('#add-field')
            .type('submitty/autograding-default:latest');
        cy.get('#send-button')
            .should('not.be.disabled')
            .click();

        cy.get('.alert-error')
            .should('have.text', 'submitty/autograding-default:latest '
            + 'already exists in capability cpp');
    });

    // NOTE: Can be refactored later to speed up the Cypress test since
    //       we need to wait for the system to install the image
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
        cy.get('.alert-success')
            .should('have.text', 'submitty/prolog:8 found on DockerHub'
            + ' and queued to be added!');

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

        // Confirm dialog return true
        cy.on('window:confirm', () => true);

        cy.get('[data-image-id="submitty/prolog:8"]')
            .should('not.exist');
    });
});
