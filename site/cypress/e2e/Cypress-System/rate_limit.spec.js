describe('Apache mod_qos Rate Limiting', () => {
  const loginUrl = '/authentication/login';
  const invalidUser = 'invalidSubmittyUser';
  const invalidPass = 'invalidPassword123!';

  it('should return 429 after exceeding login rate limit', () => {
    // Try 30+ login attempts (limit is 30 per 60s)
    let got429 = false;
    cy.wrap(Array.from({ length: 32 })).each((_, i) => {
      cy.request({
        method: 'POST',
        url: loginUrl,
        failOnStatusCode: false,
        form: true,
        body: {
          user_id: invalidUser,
          password: invalidPass,
          stay_logged_in: true,
          login: 'Login',
        },
      }).then((resp) => {
        if (i < 30) {
          expect(resp.status).to.not.eq(429);
        } else {
          expect(resp.status).to.eq(429);
          got429 = true;
        }
      });
    });

    expect(got429).to.be.true;
  });

  it('should return 429 after exceeding global GET rate limit', () => {
    // Try 100+ GET requests to home (limit is 100 per 60s)
    let got429 = false;
    cy.wrap(Array.from({ length: 102 })).each((_, i) => {
      cy.request({
        method: 'GET',
        url: '/',
        failOnStatusCode: false,
      }).then((resp) => {
        if (i < 100) {
          expect(resp.status).to.not.eq(429);
        } else {
          expect(resp.status).to.eq(429);
          got429 = true;
        }
      });
    });

    cy.then(() => {
      expect(got429).to.be.true;
    });
  });

  it('should allow requests after waiting for rate limit window', () => {
    // Wait 61 seconds to clear the rate limit window
    cy.wait(61000);

    // Try login again, should not get a 429 response
    cy.request({
      method: 'POST',
      url: loginUrl,
      failOnStatusCode: false,
      form: true,
      body: {
        user_id: invalidUser,
        password: invalidPass,
        stay_logged_in: true,
        login: 'Login',
      },
    }).then((resp) => {
      expect(resp.status).to.not.eq(429);
    });
  });
});
