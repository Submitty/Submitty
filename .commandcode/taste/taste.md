# vue
- Avoid `initProps()` patterns that copy reactive props to local refs in `<script setup>` — props are already reactive and don't need redundant local copies. Confidence: 0.80
- Do not assign to `window`, use `document.addEventListener`, or set up any global bridge functions in Vue component files — use only props and emits for communication, and handle events in the Twig template. Confidence: 0.95
- Use CSS classes instead of bare HTML element selectors in `<style scoped>` blocks. Confidence: 0.65
- Do not modify shared/generic components (e.g., Popup.vue) when building a specific component that uses them — keep all changes scoped to the component being worked on. Confidence: 0.70
- Prefer `v-show`/`v-if` over CSS `!important` display none for hiding child component elements. Confidence: 0.60

# testing
- Write tests that are absolutely necessary and sufficient — avoid trivial tests, ensure >95% branch and line coverage, and do not only cover happy paths. Confidence: 0.65
- Write out props explicitly in each test case rather than spreading baseProps; avoid redundantly passing props that duplicate values already in baseProps. Confidence: 0.65
