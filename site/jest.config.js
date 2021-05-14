module.exports = {
    setupFilesAfterEnv: [
      "<rootDir>/tests/mjs/setupTests.js",
    ],
    testEnvironment: "jsdom",
    testMatch: [
      "<rootDir>/tests/mjs/**/*.spec.js",
    ],
    transform: {
      "\\.js$": "babel-jest"
    },
};
