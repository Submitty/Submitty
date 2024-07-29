<script setup lang="ts">
import Markdown from '@/components/markdown.vue';
import QueueTools from '@/components/office_hours_queue/queueTools.vue';
import { inject } from 'vue';

type Queue = {
    id: number;
    open: boolean;
    code: string;
    token: string;
    regex_pattern?: string;
    contact_information: boolean;
    message?: string;
    message_sent_time?: string;
    num_students: number;
};

const viewer = inject<{
    is_grader: boolean;
    queues: Queue[];
    announcement_msg: string;
}>('viewer')!;

const color_codes = inject<Record<string, string>>('color_codes') ?? {};
const base_url = inject<string>('base_url') ?? location.href;

const open_queues = viewer.queues.filter((v) => v.open);
const closed_queues = viewer.queues.filter((v) => !v.open);

</script>

<template>
  <div
    id="queue-root"
    class="d-flex flex-column gap-1"
  >
    <h1>Office Hours Queue</h1>

    <p>
      For more information:

      <a
        v-if="viewer.is_grader"
        target="_blank"
        href="https://submitty.org/grader/queue"
      >
        Managing the Office Hours Queue
        <i
          style="font-style:normal;"
          class="fa-question-circle"
        />
      </a>
      <a
        v-else
        target="_blank"
        href="https://submitty.org/student/queue"
      >
        Getting Help through the Office Hours Queue
        <i
          style="font-style:normal;"
          class="fa-question-circle"
        />
      </a>
    </p>

    <template v-if="viewer.is_grader">
      <template
        v-for="[label, queues] in [['Open Queues', open_queues], ['Closed Queues', closed_queues]]"
        :key="label"
      >
        <div class="d-flex flex-row justify-content-start gap-025">
          {{ label }}:
          <label
            v-for="queue in queues as Queue[]"
            :key="queue.id"
            tabindex="0"
            class="btn filter-buttons"
            :style="{
              'border-color': color_codes[queue.code],
              'background': color_codes[queue.code]
            }"
          >{{ queue['code'] }}
            <input
              :id="`queue_filter_${queue.id}`"
              type="checkbox"
              :title="`Toggle filter for: ${queue.code.toUpperCase()}`"
              :aria-label="`Toggle filter for: ${queue.code.toUpperCase()}`"
              class="page_loading queue_filter"
            />
          </label>
        </div>
      </template>
    </template>

    <div
      v-if="viewer.announcement_msg.length > 0"
      id="announcement"
      data-testid="announcement"
    >
      <h2>Office Hours Queue Announcements:</h2>
      <Markdown :content="viewer.announcement_msg" />
    </div>

    <QueueTools
      v-if="viewer.is_grader"
      :base-url="base_url"
    />
  </div>
</template>

<style scoped>
.gap-025 {
  gap: 0.25em;
}

.gap-1 {
  gap: 1em;
}

.filter-buttons {
  color: black;
  word-wrap: break-word;
}

.queue_filter {
  display: none;
}

#announcement {
  background-color: var(--standard-light-gray);
  padding: 0.75rem;
}

[data-theme="dark"] #announcement {
  background-color: var(--standard-dark-gray);
}
</style>
