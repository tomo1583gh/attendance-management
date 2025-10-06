module.exports = {
  extends: ['stylelint-config-standard'],
  plugins: [],
  rules: {
    'no-duplicate-selectors': true,
    'declaration-block-no-duplicate-properties': [
      true,
      { ignore: ['consecutive-duplicates-with-different-values'] }
    ],
    'no-descending-specificity': null, // 慣れてきたら true へ

    // ★ BEM（block__element--modifier）を許容
    //    例: .auth-header__inner, .auth-links__item--sub などをOKにする
    'selector-class-pattern': [
      '^[a-z0-9]+(?:-[a-z0-9]+)*(?:__(?:[a-z0-9]+(?:-[a-z0-9]+)*))?(?:--(?:[a-z0-9]+(?:-[a-z0-9]+)*))?$',
      { resolveNestedSelectors: true }
    ],

    // ★ 既存コードの @media (min|max-width: ...) を許容
    'media-feature-range-notation': 'prefix'
  },
};