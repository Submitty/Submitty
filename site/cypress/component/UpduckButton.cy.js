import UpduckButton from '@/components/forum/UpduckButton.vue';

const baseProps = {
    postId: 123,
    threadId: 456,
    currentUser: 'user1',
    userLiked: false,
    likeCount: 0,
    likedByStaff: false,
    showLikersIcon: false,
};

describe('UpduckButton', () => {
    it('renders correct image for liked and unliked states', () => {
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

    it('shows likers icon when showLikersIcon is true and clicking it emits show-liked-users', () => {
        const onShowLikedUsers = cy.stub().as('onShowLikedUsers');

        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: true, postId: 99, onShowLikedUsers } });
        cy.get('[data-testid="show-upduck-list"]').should('exist').click({ force: true });

        cy.get('@onShowLikedUsers').should('have.been.calledOnceWith', { postId: 99 });
    });

    it('emits toggle-like when like button is clicked', () => {
        const onToggleLike = cy.stub().as('onToggleLike');

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 11, threadId: 22, currentUser: 'user2', onToggleLike } });
        cy.get('[data-testid="upduck-button"]').click();

        cy.get('@onToggleLike').should('have.been.calledOnceWith', { postId: 11, threadId: 22, currentUser: 'user2' });
    });

    it('does not throw when no event handlers are provided', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: true, postId: 55 } });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="show-upduck-list"]').click({ force: true });
        cy.get('[data-testid="upduck-container"]').should('exist');
    });

    it('keyboard activation (Enter) emits toggle-like', () => {
        const onToggleLike = cy.stub().as('onToggleLike');

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 201, threadId: 202, currentUser: 'user2', onToggleLike } });
        cy.get('[data-testid="upduck-button"]').focus();
        cy.get('[data-testid="upduck-button"]').type('{enter}');
        cy.get('@onToggleLike').should('have.been.calledOnceWith', { postId: 201, threadId: 202, currentUser: 'user2' });
    });

    it('multiple clicks emit toggle-like multiple times', () => {
        const onToggleLike = cy.stub().as('onToggleLike');

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 301, threadId: 302, currentUser: 'user2', onToggleLike } });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('@onToggleLike').should('have.been.calledTwice');
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
            const onToggleLike = cy.stub().as('onToggleLike');

            cy.mount(UpduckButton, { props: { ...baseProps, postId: 0, likeCount: 0, onToggleLike } });
            cy.get('#likeIcon_0').should('exist');
            cy.get('#likeCounter_0').should('have.text', '0');
            cy.get('[data-testid="upduck-button"]').click();
            cy.get('@onToggleLike').should('have.been.calledOnceWith', { postId: 0, threadId: baseProps.threadId, currentUser: baseProps.currentUser });
        });

        it('handles negative likeCount gracefully (renders as-is)', () => {
            cy.mount(UpduckButton, { props: { ...baseProps, likeCount: -5, postId: 77 } });
            cy.get('[data-testid="like-count"]').should('have.text', '-5');
        });
    });
});
