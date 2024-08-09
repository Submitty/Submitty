module.exports = {
    collectCoverage: true,
    collectCoverageFrom: [
        '<rootDir>/ts/**/*.js',
        '<rootDir>/ts/**/*.ts',
    ],
    coverageDirectory: '<rootDir>/tests/report/jest',
    testEnvironment: 'jsdom',
    testMatch: [
        '<rootDir>/tests/ts/**/*.spec.js',
    ],
    transform: {
        '\\.[jt]s$': 'babel-jest',
    },
};
