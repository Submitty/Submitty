<script setup lang="ts">
const { userGraders } = defineProps<{
    userGraders: Record<string, { grader_id: string; timestamp: string }[]>;
}>();
function formatTimestamp(timestamp: string): string {
    return window.luxon.DateTime.fromFormat(timestamp, 'yyyy-MM-dd HH:mm:ssZZ')
        .toRelative({ base: window.luxon.DateTime.now() }) || timestamp;
}
</script>

<template>
  <div v-if="Object.keys(userGraders).length">
    <ul>
      <li
        v-for="(graders, componentTitle) in userGraders"
        :key="componentTitle"
      >
        <p>{{ componentTitle }}: {{ graders.map(g=> `${g.grader_id} - ${formatTimestamp(g.timestamp)}`).join(", ") }}</p>
      </li>
    </ul>
  </div>
</template>
