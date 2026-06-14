import UpduckButton from '@/components/forum/UpduckButton.vue';

describe('UpduckButton', () => {
    const baseProps = {
        postId: 123,
        threadId: 456,
        currentUser: 'jndlansh',
        userLiked: false,
        likeCount: 0,
        likedByStaff: false,
        showLikersIcon: false,
    };

    it('renders correct image for liked and unliked states', () => {
        cy.window().then((win) => {
            win.toggleLike = cy.stub().as('toggleLike');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, userLiked: false, postId: 123 } });
        cy.get('[data-testid="upduck-container"]').within(() => {
            cy.get('img')
                .should('have.attr', 'src')
                .and('include', '/img/light-mode-off-duck.svg');
            cy.get('#likeIcon_123').should('exist');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, userLiked: true, postId: 321 } });
        cy.get('[data-testid="upduck-container"]').within(() => {
            cy.get('img')
                .should('have.attr', 'src')
                .and('include', '/img/on-duck-button.svg');
            cy.get('#likeIcon_321').should('exist');
        });
    });

    it('renders like count including zero and large numbers', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, likeCount: 0, postId: 5 } });
        cy.get('[data-testid="like-count"]').should('have.text', '0');

        cy.mount(UpduckButton, { props: { ...baseProps, likeCount: 99999, postId: 6 } });
        cy.get('[data-testid="like-count"]').should('have.text', '99999');
    });

    it('shows instructor like when likedByStaff is true and hides when false', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, likedByStaff: true, postId: 7 } });
        cy.get('[data-testid="instructor-like"]').should('be.visible');

        cy.mount(UpduckButton, { props: { ...baseProps, likedByStaff: false, postId: 8 } });
        cy.get('[data-testid="instructor-like"]').should('not.be.visible');
    });

    it('shows likers icon when showLikersIcon is true and clicking it calls window.showUpduckUsers with postId', () => {
        cy.window().then((win) => {
            win.showUpduckUsers = cy.stub().as('showUpduckUsers');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: true, postId: 99 } });
        cy.get('[data-testid="show-upduck-list"]').should('exist').click({ force: true });

        cy.get('@showUpduckUsers').should('have.been.calledOnceWith', 99);
    });

    it('calls window.toggleLike with correct args when like button is clicked', () => {
        cy.window().then((win) => {
            win.toggleLike = cy.stub().as('toggleLike');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: false, postId: 11, threadId: 22, currentUser: 'JManion' } });
        cy.get('[data-testid="upduck-button"]').click();

        cy.get('@toggleLike').should('have.been.calledOnceWith', 11, 22, 'JManion');
    });

    it('does not throw when window handlers are missing', () => {
        cy.window().then((win) => {
            delete win.toggleLike;
            delete win.showUpduckUsers;
        });

        // mounting and clicking should not throw
        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: true, postId: 55 } });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="show-upduck-list"]').click({ force: true });

        // If no exception thrown, component remains mounted
        cy.get('[data-testid="upduck-container"]').should('exist');
    });

    it('keyboard activation (Enter) triggers toggleLike', () => {
        cy.window().then((win) => {
            win.toggleLike = cy.stub().as('toggleLike');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 201, threadId: 202, currentUser: 'jndlansh' } });
        cy.get('[data-testid="upduck-button"]').focus();
        cy.get('[data-testid="upduck-button"]').type('{enter}');
        cy.get('@toggleLike').should('have.been.calledOnceWith', 201, 202, 'jndlansh');
    });

    it('multiple clicks call toggleLike multiple times', () => {
        cy.window().then((win) => {
            win.toggleLike = cy.stub().as('toggleLike');
        });

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 301, threadId: 302, currentUser: 'dan' } });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('@toggleLike').should('have.been.calledTwice');
    });

    it('id attributes include postId for both icon and counter', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, postId: 999, likeCount: 7 } });
        cy.get('#likeIcon_999').should('exist');
        cy.get('#likeCounter_999').should('have.text', '7');
    });

    it('has accessible markup: image alt and button title', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, postId: 401 } });
        cy.get('img[alt="Like"]').should('exist');
        cy.get('[data-testid="upduck-button"]').should('have.attr', 'title').and('not.be.empty');
    });

    describe('edge cases', () => {
        it('handles postId = 0 without breaking ids', () => {
            cy.window().then((win) => {
                win.toggleLike = cy.stub().as('toggleLike');
            });
            cy.mount(UpduckButton, { props: { ...baseProps, postId: 0, likeCount: 0 } });
            cy.get('#likeIcon_0').should('exist');
            cy.get('#likeCounter_0').should('have.text', '0');
            cy.get('[data-testid="upduck-button"]').click();
            cy.get('@toggleLike').should('have.been.calledOnceWith', 0, baseProps.threadId, baseProps.currentUser);
        });

        it('handles negative likeCount gracefully (renders as-is)', () => {
            cy.mount(UpduckButton, { props: { ...baseProps, likeCount: -5, postId: 77 } });
            cy.get('[data-testid="like-count"]').should('have.text', '-5');
        });
    });
});
