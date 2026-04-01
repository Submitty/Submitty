import { createApp } from 'vue';
import ScoreBox from '../../vue/components/ScoreBox.vue';

const el = document.getElementById('score-box-app');
createApp(ScoreBox, {
  gradeableId: el.dataset.gradeableId,
  userId: el.dataset.userId,
  initialScore: Number(el.dataset.initialScore),
  max: Number(el.dataset.max)
}).mount('#score-box-app');
