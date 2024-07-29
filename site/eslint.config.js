// @ts-check

const eslint = require('@eslint/js');
const stylistic = require('@stylistic/eslint-plugin');
const jest = require('eslint-plugin-jest');
const globals = require('globals');
const tseslint = require('typescript-eslint');
// eslint-pluging-cypress/flat doesnt have ts definitions yet
// @ts-expect-error TS2307
const cypress = require('eslint-plugin-cypress/flat');
const vuelint = require('eslint-plugin-vue');

module.exports = tseslint.config(
    {
        name: 'Files to include',
        files: ['**/*.{js,ts,mts}'],
    },
    {
        // name: 'Files to ignore', (this line can be uncommented with eslint >=9.0)
        ignores: [
            'node_modules/**',
            'public/mjs/**',
            '**/vendor/**',
        ],
    },
    {
        name: 'Base options for all files',
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
            // twig and eslint do not play well together, would be nice to re-enable this rule
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
                    arrowParens: true,
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
                tsconfigRootDir: __dirname,
            },
        },
    },
    {
        name: 'Options for Vue files',
        files: ['vue/**/*.{js,ts,vue}'],
        // @ts-expect-error vuelint doesnt have ts types yet
        extends: [...(vuelint.configs['flat/recommended']), ...tseslint.configs.recommendedTypeChecked],
        languageOptions: {
            parser: require('vue-eslint-parser'),
            globals: globals.browser,
            parserOptions: {
                parser: '@typescript-eslint/parser',
                project: 'tsconfig.app.json',
                extraFileExtensions: ['.vue'],
                tsconfigRootDir: `${__dirname}/vue`,
            },
        },
        rules: {
            'vue/multi-word-component-names': ['off'],
            'vue/block-lang': ['error', { script: { lang: 'ts' } }],
            'vue/block-order': ['error', { order: ['script:not([setup])', 'script[setup]', 'template', 'style'] }],
            'vue/component-api-style': ['error', ['script-setup']],
            'vue/define-emits-declaration': ['error', 'type-literal'],
            'vue/define-macros-order': ['error',
                {
                    order: ['defineOptions', 'defineModel', 'defineProps', 'defineEmits', 'defineSlots'],
                    defineExposeLast: true,
                },
            ],
            'vue/define-props-declaration': ['error', 'type-based'],
            'vue/html-self-closing': ['error', { html: { void: 'any' } }],
            'vue/no-boolean-default': ['error', 'no-default'],
            'vue/no-ref-object-reactivity-loss': ['warn'],
            'vue/no-required-prop-with-default': ['error', { autofix: true }],
            'vue/no-useless-mustaches': ['error'],
            'vue/prefer-separate-static-class': ['error'],
            'vue/require-typed-object-prop': ['error'],
            'vue/require-typed-ref': ['error'],
            'vue/valid-define-options': ['error'],
        },
    },
    {
        name: 'Options for cypress files',
        files: ['cypress/**/*.{js,ts}'],
        extends: [cypress.configs.recommended],
        languageOptions: {
            globals: globals.nodeBuiltin,
        },
        rules: {
            'no-restricted-syntax': [
                'error',
                {
                    selector: '[type=CallExpression][callee.object.name=cy][callee.property.name=waitAndReloadUntil]',
                    message: 'Do not wait for arbitrary time periods',
                },
            ],
        },
    },
    {
        name: 'Options for jest files',
        files: ['tests/**/*.{js,ts}'],
        extends: [jest.configs['flat/recommended']],
        languageOptions: {
            globals: { ...globals.nodeBuiltin, ...jest.environments.globals.globals },
        },
    },
);
