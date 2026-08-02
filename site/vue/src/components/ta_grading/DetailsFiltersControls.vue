<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    showAllSections: boolean;
    toggleAnon: boolean;
    gradeInquiryOnly: boolean;
    canFilterWithdrawn: boolean;
    anonMode: boolean;
    gradeableId: string;
    initialViewSections?: boolean;
    initialRandomOrder?: boolean;
    initialInquiryOnly?: boolean;
    initialHideWithdrawn?: boolean;
}>();

const emit = defineEmits<{
    'mounted': [{ inquiryOnly: boolean }];
    'view-sections-change': [checked: boolean];
    'sort-order-change': [checked: boolean];
    'anon-change': [checked: boolean];
    'inquiry-change': [checked: boolean];
    'withdrawn-change': [checked: boolean];
}>();

const viewSectionsChecked = ref(props.initialViewSections ?? false);
const randomOrderChecked = ref(props.initialRandomOrder ?? false);
const inquiryOnlyChecked = ref(props.initialInquiryOnly ?? false);
const withdrawnHiddenChecked = ref(props.initialHideWithdrawn ?? false);

onMounted(() => {
    emit('mounted', { inquiryOnly: inquiryOnlyChecked.value });
});

const onChangeSections = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    viewSectionsChecked.value = checked;
    emit('view-sections-change', checked);
};

const onChangeSortOrder = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    randomOrderChecked.value = checked;
    emit('sort-order-change', checked);
};

const onChangeInquiry = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    inquiryOnlyChecked.value = checked;
    emit('inquiry-change', checked);
};

const onChangeAnon = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    emit('anon-change', checked);
};

const onFilterWithdrawn = (event: Event) => {
    const checked = (event.target as HTMLInputElement).checked;
    withdrawnHiddenChecked.value = checked;
    emit('withdrawn-change', checked);
};
</script>

<template>
  <div class="row-wrapper">
    <label
      v-if="showAllSections"
      for="toggle-view-sections"
      data-testid="view-sections-label"
      class="column-wrapper"
    >
      Only Assigned Sections
      <input
        id="toggle-view-sections"
        type="checkbox"
        data-testid="view-sections"
        :checked="viewSectionsChecked"
        @change="onChangeSections"
      >
    </label>

    <label
      for="toggle-random-order"
      data-testid="random-order-label"
      class="column-wrapper"
    >
      Randomize Order
      <input
        id="toggle-random-order"
        type="checkbox"
        data-testid="random-order-checkbox"
        :checked="randomOrderChecked"
        @change="onChangeSortOrder"
      >
    </label>

    <label
      v-if="toggleAnon"
      for="toggle-anon-students"
      data-testid="anon-students-label"
      class="column-wrapper"
      :data-gradeable-id="gradeableId"
    >
      Anonymize Student Names
      <input
        id="toggle-anon-students"
        type="checkbox"
        data-testid="anon-students-checkbox"
        :checked="anonMode"
        @change="onChangeAnon"
      >
    </label>

    <label
      v-if="gradeInquiryOnly"
      for="toggle-inquiry-only"
      data-testid="inquiry-only-label"
      class="column-wrapper"
    >
      Grade Inquiries Only
      <input
        id="toggle-inquiry-only"
        type="checkbox"
        data-testid="inquiry-only-checkbox"
        :checked="inquiryOnlyChecked"
        @change="onChangeInquiry"
      >
    </label>

    <label
      v-if="canFilterWithdrawn"
      for="toggle-filter-withdrawn"
      data-testid="filter-withdrawn-label"
      class="column-wrapper"
    >
      Hide Withdrawn Students
      <input
        id="toggle-filter-withdrawn"
        type="checkbox"
        data-testid="filter-withdrawn-checkbox"
        :checked="withdrawnHiddenChecked"
        @change="onFilterWithdrawn"
      >
    </label>
  </div>
</template>
