module.exports = {
    collectCoverage: true,
    collectCoverageFrom: [
        '<rootDir>/public/mjs/**/*.js',
    ],
    coverageDirectory: '<rootDir>/tests/report/jest',
    setupFilesAfterEnv: [
        '<rootDir>/tests/mjs/setupTests.js',
    ],
    testEnvironment: 'jsdom',
    testMatch: [
        '<rootDir>/tests/mjs/**/*.spec.js',
    ],
    transform: {
        '\\.js$': 'babel-jest',
    },
};
