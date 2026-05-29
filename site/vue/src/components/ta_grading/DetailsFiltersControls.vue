<script setup lang="ts">
import { onMounted, ref } from 'vue';

declare global {
    interface Window {
        changeSections: () => void;
        changeSortOrder: () => void;
        changeAnon: () => void;
        changeInquiry: () => void;
        filter_withdrawn_students: () => void;
        updateElectronicGradingRowNumbersAndColors: () => void;
        Cookies?: { get: (key: string) => string | undefined };
    }
}

const {
    showAllSections,
    toggleAnon,
    gradeInquiryOnly,
    canFilterWithdrawn,
    anonMode,
    gradeableId,
    isTeamAssignment,
} = defineProps<{
    showAllSections: boolean;
    toggleAnon: boolean;
    gradeInquiryOnly: boolean;
    canFilterWithdrawn: boolean;
    anonMode: boolean;
    gradeableId?: string;
    isTeamAssignment: boolean;
}>();

const viewSectionsChecked = ref(false);
const randomOrderChecked = ref(false);
const inquiryOnlyChecked = ref(false);
const withdrawnHiddenChecked = ref(false);

onMounted(() => {
    const inquiryFilterStatus = window.Cookies?.get('inquiry_status');
    const assignedFilterStatus = window.Cookies?.get('view');
    const randomFilterStatus = window.Cookies?.get('sort');
    const withdrawnFilterStatus = window.Cookies?.get('include_withdrawn_students') || 'omit';

    if (showAllSections) {
        viewSectionsChecked.value = assignedFilterStatus === 'assigned' || assignedFilterStatus === undefined;
    }

    randomOrderChecked.value = randomFilterStatus === 'random';

    if (gradeInquiryOnly) {
        inquiryOnlyChecked.value = inquiryFilterStatus === 'on';
    }

    const withdrawnFilterElements = $('[data-student="electronic-grade-withdrawn"]');
    withdrawnFilterElements.hide();

    if (canFilterWithdrawn) {
        if (withdrawnFilterStatus === 'omit') {
            withdrawnHiddenChecked.value = true;
            withdrawnFilterElements.hide();
        }
        else {
            withdrawnHiddenChecked.value = false;
            withdrawnFilterElements.show();
        }
    }

    if (isTeamAssignment) {
        withdrawnFilterElements.show();
    }

    window.updateElectronicGradingRowNumbersAndColors();
    $('table').removeClass('table-striped');
});

const onChangeSections = () => window.changeSections();
const onChangeSortOrder = () => window.changeSortOrder();
const onChangeAnon = () => window.changeAnon();
const onChangeInquiry = () => window.changeInquiry();
const onFilterWithdrawn = () => window.filter_withdrawn_students();
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