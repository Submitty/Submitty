describe('Tests cases revolving around gradeable access and submition', () => {
    ['student','instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });
        it('Should upload file and download it', () => {
            cy.login(user);
            cy.visit(['sample','gradeable','stuff']);
        });

    });
});
describe('Tests cases revolving around notebook gradeable access and submition', () => {
    ['student','instructor'].forEach((user) => {
        beforeEach(() => {
            cy.visit('/');
        });
        it('Should have radio buttons and not have ', () => {

        });

    });
});