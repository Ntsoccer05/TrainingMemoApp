module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    // vue3-recommended はフォーマット系ルールが大量に含まれ既存コードとの相性が悪いため、
    // バグ予防に的を絞った vue3-essential を採用(整形は将来 Prettier 導入時に別途対応)
    'plugin:vue/vue3-essential',
    'plugin:@typescript-eslint/recommended',
  ],
  parser: 'vue-eslint-parser',
  parserOptions: {
    parser: '@typescript-eslint/parser',
    ecmaVersion: 2021,
    sourceType: 'module',
  },
  plugins: ['@typescript-eslint'],
  rules: {
    // 既存コードの段階移行のため、当面は warn に留める項目
    '@typescript-eslint/no-explicit-any': 'warn',
    'vue/multi-word-component-names': 'off',
    // review-digest により仕組み化(docs/review-history.json: デバッグコード残存, count=3)
    // デバッグ用 console.log の消し忘れが繰り返し指摘されていたため、機械的に検出する。
    // console.warn/error は意図的なログとして許可する。
    'no-console': ['error', { allow: ['warn', 'error'] }],
  },
  ignorePatterns: ['dist/', 'vendor/', 'node_modules/', 'vite-env.d.ts'],
};
