module.exports = {
    collectCoverage: true,
    collectCoverageFrom: [
        '<rootDir>/ts/**/*.js',
        '<rootDir>/ts/**/*.ts',
    ],
    coverageDirectory: '<rootDir>/tests/report/jest',
    setupFilesAfterEnv: [
        '<rootDir>/tests/ts/setupTests.js',
    ],
    testEnvironment: 'jsdom',
    testMatch: [
        '<rootDir>/tests/ts/**/*.spec.js',
    ],
    transform: {
        '\\.[jt]s$': 'babel-jest',
    },
};
