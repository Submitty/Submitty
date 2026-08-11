import UserInfoBlock from '@/components/forum/UserInfoBlock.vue';

const defaultProps = {
    username: 'User 1',
    fullName: 'User One (user1)',
    isAnonymous: false,
    isOP: false,
    pronouns: 'She/Her',
    showPronouns: true,
    postDate: 'Jan 1, 2026',
    editDate: null,
    authorEmail: 'user1@example.com',
    showEmailToggle: false,
    showUserInfoToggle: false,
};

describe('UserInfoBlock', () => {
    it('renders the identity block and omits the toggles when disabled', () => {
        cy.mount(UserInfoBlock, { props: defaultProps });
        cy.get('[data-testid="last-edit"]').should('exist');
        cy.get('[data-testid="author-name"]').should('have.text', 'User 1');
        cy.get('[data-testid="post-user-id"]').should('contain', 'User 1');
        cy.get('[data-testid="email-toggle"]').should('not.exist');
        cy.get('[data-testid="author-email"]').should('not.exist');
        cy.get('[data-testid="user-info-toggle"]').should('not.exist');
    });

    it('shows the OP badge with title when isOP and hides it otherwise', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, isOP: true } });
        cy.get('[data-testid="post-user-id"]').should('contain', 'OP');
        cy.get('[data-testid="post-user-id"]').should('have.attr', 'title', 'Original Poster');

        cy.mount(UserInfoBlock, { props: defaultProps });
        cy.get('[data-testid="post-user-id"]').should('not.contain', 'OP');
        cy.get('[data-testid="post-user-id"]').should('not.have.attr', 'title');
    });

    it('renders pronouns when showPronouns and omits them otherwise', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, pronouns: 'They/Them' } });
        cy.get('[data-testid="post-user-pronouns"]').should('have.text', '(They/Them)');

        cy.mount(UserInfoBlock, { props: { ...defaultProps, showPronouns: false } });
        cy.get('[data-testid="post-user-pronouns"]').should('not.exist');
    });

    it('renders the post date and the last edit indicator when present', () => {
        cy.mount(UserInfoBlock, { props: defaultProps });
        cy.get('[data-testid="last-edit"]').should('contain', 'Jan 1, 2026');
        cy.get('[data-testid="last-edit"]').should('not.contain', 'Last edit at');

        cy.mount(UserInfoBlock, { props: { ...defaultProps, editDate: 'Feb 2, 2026' } });
        cy.get('[data-testid="last-edit"]').should('contain', 'Last edit at Feb 2, 2026');
    });

    it('renders the identity line with the same spacing as the original Twig markup', () => {
        cy.mount(UserInfoBlock, {
            props: { ...defaultProps, isOP: true, editDate: 'Feb 2, 2026' },
        });
        cy.get('[data-testid="last-edit"]').should(
            'have.text',
            'User 1 OP (She/Her) Jan 1, 2026 (Last edit at Feb 2, 2026)',
        );
    });

    it('reveals the mailto link only after clicking the email toggle', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, showEmailToggle: true } });
        cy.get('[data-testid="author-email"]').should('not.be.visible');
        cy.get('[data-testid="email-toggle"]').click({ force: true });
        cy.get('[data-testid="author-email"]').should('be.visible');
        cy.get('[data-testid="author-email"]').should('have.attr', 'href', 'mailto:user1@example.com');
        cy.get('[data-testid="email-toggle"]').click({ force: true });
        cy.get('[data-testid="author-email"]').should('not.be.visible');
    });

    it('toggles between the visible username and the full name with the eye icon', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, showUserInfoToggle: true } });
        cy.get('[data-testid="author-name"]').should('have.text', 'User 1');
        cy.get('[data-testid="user-info-toggle-icon"]').should('have.class', 'fa-eye');
        cy.get('[data-testid="user-info-toggle"]').should('have.attr', 'title', 'Show full user information');

        cy.get('[data-testid="user-info-toggle"]').click({ force: true });
        cy.get('[data-testid="author-name"]').should('have.text', 'User One (user1)');
        cy.get('[data-testid="user-info-toggle-icon"]').should('have.class', 'fa-eye-slash');
        cy.get('[data-testid="user-info-toggle"]').should('have.attr', 'title', 'Hide full user information');

        cy.get('[data-testid="user-info-toggle"]').click({ force: true });
        cy.get('[data-testid="author-name"]').should('have.text', 'User 1');
        cy.get('[data-testid="user-info-toggle-icon"]').should('have.class', 'fa-eye');
    });

    it('applies the anonymous revealed styling only while revealed', () => {
        cy.mount(UserInfoBlock, {
            props: { ...defaultProps, showUserInfoToggle: true, isAnonymous: true },
        });
        cy.get('[data-testid="author-name"]').should('not.have.class', 'user-info-anon-revealed');
        cy.get('[data-testid="user-info-toggle"]').click({ force: true });
        cy.get('[data-testid="author-name"]').should('have.class', 'user-info-anon-revealed');
        cy.get('[data-testid="user-info-toggle"]').click({ force: true });
        cy.get('[data-testid="author-name"]').should('not.have.class', 'user-info-anon-revealed');
    });

    it('keyboard activation (Enter and Space) toggles the user info reveal', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, showUserInfoToggle: true } });
        cy.get('[data-testid="user-info-toggle"]').focus();
        cy.get('[data-testid="user-info-toggle"]').type('{enter}');
        cy.get('[data-testid="author-name"]').should('have.text', 'User One (user1)');
        cy.get('[data-testid="user-info-toggle"]').type(' ');
        cy.get('[data-testid="author-name"]').should('have.text', 'User 1');
    });

    it('keyboard activation (Enter) toggles the email link', () => {
        cy.mount(UserInfoBlock, { props: { ...defaultProps, showEmailToggle: true } });
        cy.get('[data-testid="email-toggle"]').focus();
        cy.get('[data-testid="email-toggle"]').type('{enter}');
        cy.get('[data-testid="author-email"]').should('be.visible');
    });

    it('has accessible markup on the interactive toggles', () => {
        cy.mount(UserInfoBlock, {
            props: { ...defaultProps, showEmailToggle: true, showUserInfoToggle: true },
        });
        cy.get('[data-testid="email-toggle"]').should('have.attr', 'title').and('not.be.empty');
        cy.get('[data-testid="email-toggle"]').should('have.attr', 'aria-label').and('not.be.empty');
        cy.get('[data-testid="user-info-toggle"]').should('have.attr', 'title').and('not.be.empty');
        cy.get('[data-testid="user-info-toggle"]').should('have.attr', 'aria-label').and('not.be.empty');
    });
});
