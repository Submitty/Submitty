// @ts-check

// @ts-expect-error
import eslint from '@eslint/js';

import stylistic from '@stylistic/eslint-plugin';
// @ts-expect-error
import globals from 'globals';
import tseslint from 'typescript-eslint';
// @ts-expect-error
import cypress from 'eslint-plugin-cypress/flat';
// @ts-expect-error
import jest from 'eslint-plugin-jest';

export default tseslint.config(
    {
        name: 'Files to include',
        files: ['**/*.{js,ts}'],
    },
    {
        name: 'Base rules for all files',
        extends: [eslint.configs.recommended],
        languageOptions: {
            globals: {
                ...globals.jquery,
                ...globals.browser,
                ...globals.es2020,
                Cookies: 'readonly',
            },
            parserOptions: {
                ecmaVersion: 2020,
                sourceType: 'module',
            },
        },
        rules: {
            'no-unused-vars': 'off',

            'eqeqeq': ['error', 'always'],
            'curly': ['error'],
            'default-param-last': ['error'],
            'no-var': ['error'],
            'prefer-arrow-callback': ['error'],
            'prefer-const': ['error'],
            'prefer-template': ['error'],
            'no-restricted-syntax': [
                'error',
                {
                    message:
                        'Direct use of `document.cookie` is not allowed. Consider using `Cookies.get` or `Cookies.set`.',
                    selector:
                        'MemberExpression[object.name="document"][property.name="cookie"]',
                },
            ],
        },
    },
    {
        name: 'Style rules for all files',
        extends: [
            /** @type {import("typescript-eslint").Config} */
            /** @type {unknown} */
            (
                stylistic.configs.customize({
                    braceStyle: 'stroustrup',
                    indent: 4,
                    semi: true,
                })
            ),
        ],
        rules: {
            '@stylistic/linebreak-style': ['error', 'unix'],
            '@stylistic/quotes': ['error', 'single', { avoidEscape: true }],
            '@stylistic/semi-style': ['error'],
        },
    },
    {
        name: 'Files in top directory are commonJS and not modules',
        files: ['*.{js,ts}'],
        languageOptions: {
            sourceType: 'commonjs',
            globals: globals.node,
        },
    },
    {
        name: 'Options for typescript files',
        files: ['**/*.ts'],
        extends: [...tseslint.configs.recommended],
    },
    {
        name: 'Options for typescript files in ts, which have their own tsconfig',
        files: ['ts/**/*.ts'],
        extends: [...tseslint.configs.recommendedTypeChecked],
        languageOptions: {
            parserOptions: {
                project: true,
                // @ts-expect-error
                tsconfigRootDir: import.meta.dirname,
            },
        },
    },
    {
        name: 'Options for cypress files',
        files: ['cypress/**/*.{js,ts}'],
        extends: [cypress.configs.recommended],
        languageOptions: {
            globals: globals.nodeBuiltin,
        },
    },
    {
        name: 'Options for jest files',
        files: ['tests/**/*.{js,ts}'],
        extends: [jest.configs['flat/recommended']],
        languageOptions: {
            globals: globals.nodeBuiltin,
        },
    },
);
