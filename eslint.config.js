import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['public/build', 'vendor', 'node_modules', 'storage'] },
    {
        files: ['resources/frontend/**/*.{ts,tsx}'],
        extends: [js.configs.recommended, ...tseslint.configs.recommended],
        languageOptions: {
            ecmaVersion: 2022,
            globals: globals.browser,
        },
        plugins: {
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
            // Protecció XSS: no s'injecta mai HTML sense escapar.
            'react/no-danger': 'off',
            'no-restricted-syntax': [
                'error',
                {
                    selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
                    message: 'No es permet dangerouslySetInnerHTML (risc de XSS).',
                },
            ],
        },
    },
    {
        // Components generats per shadcn/ui: exporten també les variants d'estil.
        files: ['resources/frontend/src/components/ui/**/*.tsx'],
        rules: { 'react-refresh/only-export-components': 'off' },
    },
);
