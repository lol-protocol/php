'use strict';

// ESLint mínimo: errores reales (variables sin declarar o sin usar, duplicados, código
// inalcanzable...), no estilo. El proyecto no tiene dependencias: ESLint se instala solo para
// correr esto (la CI lo hace con npm install --no-save). Para correrlo a mano:
//   npm install --no-save --no-package-lock eslint@10.15.0 @eslint/js@10.0.1 && npx eslint .

const js = require('@eslint/js');

const navegador = {
  window: 'readonly', document: 'readonly', navigator: 'readonly', performance: 'readonly', localStorage: 'readonly',
  requestAnimationFrame: 'readonly', setTimeout: 'readonly', clearTimeout: 'readonly', setInterval: 'readonly', clearInterval: 'readonly',
  console: 'readonly', Blob: 'readonly', File: 'readonly', URL: 'readonly', Event: 'readonly', confirm: 'readonly',
  AudioContext: 'readonly', OfflineAudioContext: 'readonly', AudioWorkletNode: 'readonly', TextEncoder: 'readonly', TextDecoder: 'readonly',
  // Node, para los módulos que corren en los dos lados
  module: 'writable', require: 'readonly', Buffer: 'readonly',
};

const nodo = {
  require: 'readonly', module: 'writable', __dirname: 'readonly', process: 'readonly', Buffer: 'readonly', console: 'readonly',
  setTimeout: 'readonly', clearTimeout: 'readonly', performance: 'readonly', TextEncoder: 'readonly', TextDecoder: 'readonly',
  window: 'readonly', document: 'readonly', Event: 'readonly', File: 'readonly', localStorage: 'readonly', URL: 'readonly',
  OfflineAudioContext: 'readonly', Blob: 'readonly', // dentro de page.evaluate, que corre en el navegador
};

module.exports = [
  { ignores: ['node_modules/**', 'docs/**'] },
  js.configs.recommended,
  {
    files: ['js/**/*.js'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'script', globals: navegador },
    rules: {
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
      'no-var': 'error',
      'prefer-const': 'error',
      eqeqeq: ['error', 'always', { null: 'ignore' }],
    },
  },
  {
    files: ['pruebas/**/*.js', 'eslint.config.cjs'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'commonjs', globals: nodo },
    rules: {
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
      'no-var': 'error',
      'prefer-const': 'error',
    },
  },
];
