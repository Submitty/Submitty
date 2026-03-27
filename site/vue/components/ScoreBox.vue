<template>
  <div class="score-box">
    <span class="badge" :class="badgeClass">{{ displayText }}</span>
    <input type="number" v-model.number="score" :max="max" min="0" />
    <button @click="saveScore">Save</button>
    <span v-if="saved" class="success-msg">Saved!</span>
  </div>
</template>

<script>
import axios from 'axios';
export default {
  props: ['gradeableId', 'userId', 'initialScore', 'max'],
  data() {
    return {
      score: this.initialScore,
      saved: false
    };
  },
  computed: {
    badgeClass() {
      if (this.score >= this.max) return 'green-background';
      if (this.score > this.max * 0.5) return 'yellow-background';
      return 'red-background';
    },
    displayText() {
      return `${this.score} / ${this.max}`;
    }
  },
  methods: {
    async saveScore() {
      await axios.post('/api/score/set', {
        gradeable_id: this.gradeableId,
        user_id: this.userId,
        score: this.score
      });
      this.saved = true;
      setTimeout(() => (this.saved = false), 2000);
    }
  }
};
</script>

<style scoped>
.score-box { display: flex; align-items: center; gap: 8px; }
.badge { padding: 4px 8px; border-radius: 4px; color: #fff; }
.green-background { background: #4caf50; }
.yellow-background { background: #ffeb3b; color: #333; }
.red-background { background: #f44336; }
.success-msg { color: #4caf50; margin-left: 8px; }
</style>
