import ReceivedMarkForm from "../../vue/src/components/ta_grading/ReceivedMarkForm.vue";

function makeStats(overrides = {}) {
    return {
        section_submitter_count: "3",
        total_submitter_count: "10",
        submitter_ids: ["student1", "student2", "student3"],
        submitter_anon_ids: {},
        ...overrides,
    };
}

describe("ReceivedMarkForm", () => {
    describe("rendering", () => {
        beforeEach(() => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: "Question 1",
                    markTitle: "Partial Credit",
                    stats: makeStats(),
                    gradedByMeWithMark: [
                        { name: "student1", url: "/grade?who_id=student1" },
                    ],
                    gradedByOthersWithMark: [
                        { name: "student2", url: "/grade?who_id=student2" },
                    ],
                    gradedByMeWithoutMark: [
                        { name: "student3", url: "/grade?who_id=student3" },
                    ],
                    gradedByOthersWithoutMark: [],
                },
            });
        });

        it("shows component title and mark title", () => {
            cy.get('[data-testid="question-title"]').should(
                "have.text",
                "Question 1",
            );
            cy.get('[data-testid="mark-title"]').should(
                "have.text",
                "Partial Credit",
            );
        });

        it("shows submitter count and ensures component counts are removed", () => {
            cy.get('[data-testid="section-submitter-count"]').should(
                "have.text",
                "3",
            );
            cy.get('[data-testid="total-submitter-count"]').should(
                "have.text",
                "10",
            );
            cy.get('[data-testid="section-graded-component-count"]').should(
                "not.exist",
            );
            cy.get('[data-testid="total-graded-component-count"]').should(
                "not.exist",
            );
            cy.get('[data-testid="section-total-component-count"]').should(
                "not.exist",
            );
            cy.get('[data-testid="total-total-component-count"]').should(
                "not.exist",
            );
            cy.get('[data-testid="different-mark-submitter-count"]').should(
                "have.text",
                "1",
            );
        });

        it("displays combined count for full-access instructors", () => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: "Question 1",
                    markTitle: "Full Credit",
                    stats: {
                        section_submitter_count: "13",
                        total_submitter_count: "13",
                        is_full_grader: true,
                        submitter_ids: ["student1", "student2"],
                        submitter_anon_ids: {},
                    },
                    gradedByMeWithMark: [
                        { name: "student1", url: "/grade?who_id=student1" },
                    ],
                    gradedByOthersWithMark: [],
                    gradedByMeWithoutMark: [
                        { name: "student2", url: "/grade?who_id=student2" },
                        { name: "student3", url: "/grade?who_id=student3" },
                    ],
                    gradedByOthersWithoutMark: [],
                },
            });
            cy.get('[data-testid="section-submitter-count"]').should(
                "have.text",
                "13",
            );
            cy.get('[data-testid="different-mark-submitter-count"]').should(
                "have.text",
                "2",
            );
            cy.get('[data-testid="total-submitter-count"]').should("not.exist");
        });

        it("renders categorized student lists across both sections", () => {
            cy.get('[data-testid="graded-by-me-with-mark"]').within(() => {
                cy.contains("a", "student1").should("be.visible");
            });
            cy.get('[data-testid="graded-by-others-with-mark"]').within(() => {
                cy.contains("a", "student2").should("be.visible");
            });
            cy.get('[data-testid="graded-by-me-without-mark"]').within(() => {
                cy.contains("a", "student3").should("be.visible");
            });
            cy.get('[data-testid="graded-by-others-without-mark"]').within(
                () => {
                    cy.contains("em", "None").should("be.visible");
                },
            );
        });
    });

    describe("stats default values", () => {
        it("shows zeros when stats fields are null", () => {
            cy.mount(ReceivedMarkForm, {
                props: {
                    show: true,
                    componentTitle: "Q1",
                    markTitle: "Full Credit",
                    stats: {
                        section_submitter_count: null,
                        total_submitter_count: null,
                        submitter_ids: [],
                        submitter_anon_ids: {},
                    },
                },
            });
            cy.get('[data-testid="section-submitter-count"]').should(
                "have.text",
                "0",
            );
        });
    });
});
