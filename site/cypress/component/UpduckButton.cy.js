import UpduckButton from '@/components/forum/UpduckButton.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

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
        cy.mount(UpduckButton, { props: baseProps });
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
        cy.mount(UpduckButton, { props: { ...baseProps, postId: 5 } });
        cy.get('[data-testid="like-count"]').should('have.text', '0');

        cy.mount(UpduckButton, { props: { ...baseProps, likeCount: 99999, postId: 6 } });
        cy.get('[data-testid="like-count"]').should('have.text', '99999');
    });

    it('shows instructor like when likedByStaff is true and hides when false', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, likedByStaff: true, postId: 7 } });
        cy.get('[data-testid="instructor-like"]').should('be.visible');

        cy.mount(UpduckButton, { props: { ...baseProps, postId: 8 } });
        cy.get('[data-testid="instructor-like"]').should('not.be.visible');
    });

    it('shows likers icon when showLikersIcon is true and clicking it emits show-liked-users', () => {
        mountWithEmitSpy(UpduckButton, 'show-liked-users', { ...baseProps, showLikersIcon: true, postId: 99 });
        cy.get('[data-testid="show-upduck-list"]').should('exist').click({ force: true });
        cy.get('@eventHandler').should('have.been.calledOnceWith', { postId: 99 });
    });

    it('emits toggle-like when like button is clicked', () => {
        mountWithEmitSpy(UpduckButton, 'toggle-like', { ...baseProps, postId: 11, threadId: 22, currentUser: 'user2' });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('@eventHandler').should('have.been.calledOnceWith', { postId: 11, threadId: 22, currentUser: 'user2' });
    });

    it('does not throw when no event handlers are provided', () => {
        cy.mount(UpduckButton, { props: { ...baseProps, showLikersIcon: true, postId: 55 } });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="show-upduck-list"]').click({ force: true });
        cy.get('[data-testid="upduck-container"]').should('exist');
    });

    it('keyboard activation (Enter) emits toggle-like', () => {
        mountWithEmitSpy(UpduckButton, 'toggle-like', { ...baseProps, postId: 201, threadId: 202, currentUser: 'user2' });
        cy.get('[data-testid="upduck-button"]').focus();
        cy.get('[data-testid="upduck-button"]').type('{enter}');
        cy.get('@eventHandler').should('have.been.calledOnceWith', { postId: 201, threadId: 202, currentUser: 'user2' });
    });

    it('multiple clicks emit toggle-like multiple times', () => {
        mountWithEmitSpy(UpduckButton, 'toggle-like', { ...baseProps, postId: 301, threadId: 302, currentUser: 'user2' });
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('[data-testid="upduck-button"]').click();
        cy.get('@eventHandler').should('have.been.calledTwice');
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
            mountWithEmitSpy(UpduckButton, 'toggle-like', { ...baseProps, postId: 0 });
            cy.get('#likeIcon_0').should('exist');
            cy.get('#likeCounter_0').should('have.text', '0');
            cy.get('[data-testid="upduck-button"]').click();
            cy.get('@eventHandler').should('have.been.calledOnceWith', { postId: 0, threadId: baseProps.threadId, currentUser: baseProps.currentUser });
        });

        it('handles negative likeCount gracefully (renders as-is)', () => {
            cy.mount(UpduckButton, { props: { ...baseProps, likeCount: -5, postId: 77 } });
            cy.get('[data-testid="like-count"]').should('have.text', '-5');
        });
    });
});
