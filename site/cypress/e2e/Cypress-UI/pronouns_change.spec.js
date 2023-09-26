describe("Tests cases about changing user pronouns", () => {
    let oldPronouns = "";
    const newPronouns = "They/Them"; // Define constant for new pronouns

    before(() => {
        cy.visit("/user_profile");
        cy.login("student");

        cy.get("#pronouns_val").as("pronounsVal").click(); // Alias for pronouns value
        cy.get("#user-pronouns-change").as("pronounsInput"); // Alias for pronouns input

        cy.get("@pronounsInput").then(($pronounsInput) => {
            oldPronouns = $pronounsInput.val();
        });

        cy.get('button[aria-label="Clear pronoun input"]').click();
        cy.get("@pronounsInput").type(newPronouns);
        cy.get("#edit-pronouns-submit").click();

        cy.get("@pronounsVal").contains(newPronouns);

        cy.logout();
    });

    after(() => {
        cy.visit("/user_profile");
        cy.login("student");

        cy.get("@pronounsVal").click();
        cy.get('button[aria-label="Clear pronoun input"]').click();
        if (oldPronouns !== "") {
            cy.get("@pronounsInput").type(oldPronouns);
        }
        cy.get("#edit-pronouns-submit").first().click();

        if (oldPronouns !== "") {
            cy.get("@pronounsVal").contains(oldPronouns);
        }
    });

    it("Verifies changed pronouns as instructor in Manage Students", () => {
        cy.visit(["sample", "users"]);
        cy.login("instructor");

        cy.get("#toggle-columns").click();
        cy.get("#toggle-pronouns").check();
        cy.get("#toggle-student-col-submit").first().click();

        cy.get(".td-pronouns:eq( 12 )").should("have.text", newPronouns);
    });

    it("Verifies changed pronouns as instructor in Student Photos", () => {
        cy.visit(["sample", "student_photos"]);
        cy.login("instructor");

        cy.get(".student-image-container > .name")
            .first()
            .contains(newPronouns);
    });
});
