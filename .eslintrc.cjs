module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  globals: {
    __: 'readonly',
    wp: 'readonly',
    fluxMediaAdmin: 'readonly',
    jQuery: 'readonly',
  },
  parserOptions: {
    ecmaFeatures: { jsx: true },
    ecmaVersion: 'latest',
    sourceType: 'module',
  },
  plugins: ['react', 'react-hooks'],
  extends: [
    'eslint:recommended',
    'plugin:react/recommended',
    'plugin:react-hooks/recommended',
  ],
  settings: {
    react: { version: 'detect' },
  },
  rules: {
    'react/react-in-jsx-scope': 'off',
    'react/prop-types': 'off',
    'no-unused-vars': 'warn',
    'no-useless-catch': 'warn',
  },
  ignorePatterns: [
    'assets/js/dist/',
    'src/assets/common/js/dist/',
    'wporg/',
    'node_modules/',
    'vendor/',
    'vendor-prefixed/',
  ],
};
