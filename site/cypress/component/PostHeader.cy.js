import PostHeader from '@/components/forum/PostHeader.vue';
import { mountWithEmitSpy } from '../support/component_test_utils';

const defaultProps = {
    threadId: 456,
    title: '(456) Sample Thread',
    isAnnounced: false,
    isExpiring: false,
    canPin: true,
    isFavorite: false,
};

describe('PostHeader', () => {
    it('renders the thread title', () => {
        cy.mount(PostHeader, { props: defaultProps });
        cy.get('[data-testid="post-header"]').should('contain', defaultProps.title);
    });

    it('shows the pin button for instructors when the thread is not announced', () => {
        cy.mount(PostHeader, { props: defaultProps });
        cy.get('[data-testid="pin-thread-button"]')
            .should('exist')
            .and('have.class', 'not-active-thread-announcement');
        cy.get('[data-testid="pinned-icon"]').should('not.exist');
    });

    it('emits pin-thread with threadId when pin is clicked', () => {
        mountWithEmitSpy(PostHeader, 'pin-thread', defaultProps);
        cy.get('[data-testid="pin-thread-button"]').click({ force: true });
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
    });

    it('shows the unpin control when announced and canPin; emits unpin-thread on click', () => {
        mountWithEmitSpy(PostHeader, 'unpin-thread', { ...defaultProps, isAnnounced: true });
        cy.get('[data-testid="pin-thread-button"]')
            .should('exist')
            .and('have.class', 'active-thread-remove-announcement')
            .and('have.attr', 'title', 'Unpin thread');
        cy.get('[data-testid="pinned-icon"]').should('not.exist');
        cy.get('[data-testid="pin-thread-button"]').click({ force: true });
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
    });

    it('uses the expiring variant class on the unpin control when expiring', () => {
        cy.mount(PostHeader, { props: { ...defaultProps, isAnnounced: true, isExpiring: true } });
        cy.get('[data-testid="pin-thread-button"]')
            .should('have.class', 'active-thread-remove-expiring-announcement')
            .and('have.attr', 'title', 'Thread is expiring soon click to unpin');
    });

    it('renders a static pinned icon (no action) for non-instructors when announced', () => {
        cy.mount(PostHeader, { props: { ...defaultProps, isAnnounced: true, canPin: false } });
        cy.get('[data-testid="pinned-icon"]').should('exist').and('have.class', 'active-thread-announcement');
        cy.get('[data-testid="pin-thread-button"]').should('not.exist');
    });

    it('uses the expiring variant class on the static pinned icon', () => {
        cy.mount(PostHeader, { props: { ...defaultProps, isAnnounced: true, canPin: false, isExpiring: true } });
        cy.get('[data-testid="pinned-icon"]').should('have.class', 'active-thread-announcement-expiring');
    });

    it('renders no pin element when canPin is false and the thread is not announced', () => {
        cy.mount(PostHeader, { props: { ...defaultProps, canPin: false } });
        cy.get('[data-testid="pin-thread-button"]').should('not.exist');
        cy.get('[data-testid="pinned-icon"]').should('not.exist');
    });

    it('shows the unbookmark control when favorited and emits unbookmark-thread on click', () => {
        mountWithEmitSpy(PostHeader, 'unbookmark-thread', { ...defaultProps, isFavorite: true });
        cy.get('[data-testid="bookmark-thread-button"]')
            .should('exist')
            .and('have.class', 'current-favorite')
            .and('have.attr', 'title', 'Unbookmark Thread');
        cy.get('[data-testid="bookmark-thread-button"]').click({ force: true });
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
    });

    it('shows the bookmark control when not favorited and emits bookmark-thread on click', () => {
        mountWithEmitSpy(PostHeader, 'bookmark-thread', defaultProps);
        cy.get('[data-testid="bookmark-thread-button"]')
            .should('exist')
            .and('have.class', 'pinned-thread');
        cy.get('[data-testid="bookmark-thread-button"]').click({ force: true });
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
    });

    it('keyboard activation (Enter and Space) emits pin-thread', () => {
        mountWithEmitSpy(PostHeader, 'pin-thread', defaultProps);
        cy.get('[data-testid="pin-thread-button"]').focus();
        cy.get('[data-testid="pin-thread-button"]').type('{enter}');
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
        cy.get('[data-testid="pin-thread-button"]').type(' ');
        cy.get('@eventHandler').should('have.been.calledTwice');
    });

    it('keyboard activation (Enter and Space) emits unpin-thread', () => {
        mountWithEmitSpy(PostHeader, 'unpin-thread', { ...defaultProps, isAnnounced: true });
        cy.get('[data-testid="pin-thread-button"]').focus();
        cy.get('[data-testid="pin-thread-button"]').type('{enter}');
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
        cy.get('[data-testid="pin-thread-button"]').type(' ');
        cy.get('@eventHandler').should('have.been.calledTwice');
    });

    it('keyboard activation (Enter and Space) emits bookmark-thread', () => {
        mountWithEmitSpy(PostHeader, 'bookmark-thread', defaultProps);
        cy.get('[data-testid="bookmark-thread-button"]').focus();
        cy.get('[data-testid="bookmark-thread-button"]').type('{enter}');
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
        cy.get('[data-testid="bookmark-thread-button"]').type(' ');
        cy.get('@eventHandler').should('have.been.calledTwice');
    });

    it('keyboard activation (Enter and Space) emits unbookmark-thread', () => {
        mountWithEmitSpy(PostHeader, 'unbookmark-thread', { ...defaultProps, isFavorite: true });
        cy.get('[data-testid="bookmark-thread-button"]').focus();
        cy.get('[data-testid="bookmark-thread-button"]').type('{enter}');
        cy.get('@eventHandler').should('have.been.calledOnceWith', defaultProps.threadId);
        cy.get('[data-testid="bookmark-thread-button"]').type(' ');
        cy.get('@eventHandler').should('have.been.calledTwice');
    });
});
