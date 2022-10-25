module.exports = {
    setupFilesAfterEnv: [
        '<rootDir>/tests/api/setupTests.ts',
    ],
    testMatch: [
        '<rootDir>/tests/api/**/*.spec.ts',
    ],
    transform: {
        '\\.[jt]s$': 'babel-jest',
    },
};
