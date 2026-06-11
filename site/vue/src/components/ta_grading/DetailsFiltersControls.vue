<script setup lang="ts">
import { onMounted, ref } from 'vue';

declare global {
    interface Window {
        Cookies: {
            get: (key: string) => string | undefined;
            set: (key: string, value: string, options?: { path?: string; expires?: number }) => void;
        };
        updateSimpleGradingRowNumbersAndColors: () => void;
        updateElectronicGradingRowNumbersAndColors: () => void;
    }
}

const props = withDefaults(defineProps<{
    showAllSections: boolean;
    toggleAnon: boolean;
    gradeInquiryOnly: boolean;
    canFilterWithdrawn: boolean;
    anonMode: boolean;
    gradeableId?: string;
    isTeamAssignment: boolean;
}>(), {
    gradeableId: undefined,
});

const viewSectionsChecked = ref(false);
const randomOrderChecked = ref(false);
const inquiryOnlyChecked = ref(false);
const withdrawnHiddenChecked = ref(false);

const coursePath = document.body.dataset.coursePath ?? '';
const cookieArguments = { path: coursePath, expires: 365 };

onMounted(() => {
    const inquiryFilterStatus = window.Cookies?.get('inquiry_status');
    const assignedFilterStatus = window.Cookies?.get('view');
    const randomFilterStatus = window.Cookies?.get('sort');
    const withdrawnFilterStatus = window.Cookies?.get('include_withdrawn_students') || 'omit';

    if (props.showAllSections) {
        viewSectionsChecked.value = assignedFilterStatus === 'assigned' || assignedFilterStatus === undefined;
    }

    randomOrderChecked.value = randomFilterStatus === 'random';

    if (props.gradeInquiryOnly) {
        inquiryOnlyChecked.value = inquiryFilterStatus === 'on';
    }

    // Withdrawn filtering and row numbering depend on DOM being ready - onMount was too early for this
    const applyDomUpdates = () => {
        const withdrawnFilterElements = $('[data-student="electronic-grade-withdrawn"]');
        withdrawnFilterElements.hide();

        if (props.canFilterWithdrawn) {
            if (withdrawnFilterStatus === 'omit') {
                withdrawnHiddenChecked.value = true;
                withdrawnFilterElements.hide();
            }
            else {
                withdrawnHiddenChecked.value = false;
                withdrawnFilterElements.show();
            }
        }

        if (props.isTeamAssignment) {
            withdrawnFilterElements.show();
        }

        window.updateElectronicGradingRowNumbersAndColors();
        $('table').removeClass('table-striped');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyDomUpdates);
    }
    else {
        applyDomUpdates();
    }

    if (props.gradeInquiryOnly && inquiryOnlyChecked.value) {
        applyInquiryFilter();
    }
});

const applyInquiryFilter = () => {
    if (inquiryOnlyChecked.value) {
        $('.grade-button').each(function () {
            if (typeof $(this).attr('data-grade-inquiry') === 'undefined') {
                $(this).closest('.grade-table').addClass('inquiry-only-disabled');
            }
        });
    }
    else {
        $('.grade-button').each(function () {
            $(this).closest('.grade-table').removeClass('inquiry-only-disabled');
        });
    }
};

const onChangeSections = (event: Event) => {
    const checked = (event.target as HTMLInputElement | null)?.checked ?? false;
    viewSectionsChecked.value = checked;
    window.Cookies.set('view', checked ? 'assigned' : 'all', cookieArguments);
    localStorage.setItem(
        'general-setting-navigate-assigned-students-only',
        checked ? 'true' : 'false',
    );
    location.reload();
};

const onChangeSortOrder = (event: Event) => {
    const checked = (event.target as HTMLInputElement | null)?.checked ?? false;
    randomOrderChecked.value = checked;
    window.Cookies.set('sort', checked ? 'random' : 'id', cookieArguments);
    location.reload();
};

const onChangeInquiry = (event: Event) => {
    const checked = (event.target as HTMLInputElement | null)?.checked ?? false;
    inquiryOnlyChecked.value = checked;
    window.Cookies.set('inquiry_status', checked ? 'on' : 'off', cookieArguments);
    applyInquiryFilter();

    const banner = document.getElementById('inquiry-banner');
    if (banner) {
        banner.style.display = checked ? '' : 'none';
    }
};

const onChangeAnon = (event: Event) => {
    const checked = (event.target as HTMLInputElement | null)?.checked ?? false;
    window.Cookies.set('anon_mode', checked ? 'on' : 'off', cookieArguments);
    location.reload();
};

const onFilterWithdrawn = (event: Event) => {
    const checked = (event.target as HTMLInputElement | null)?.checked ?? false;
    withdrawnHiddenChecked.value = checked;

    const withdrawnElectronic = $('[data-student="electronic-grade-withdrawn"]');
    const withdrawnSimple = $('[data-student="simple-grade-withdrawn"]');

    if (checked) {
        withdrawnElectronic.hide();
        withdrawnSimple.hide();
        window.Cookies.set('include_withdrawn_students', 'omit', cookieArguments);
    }
    else {
        withdrawnElectronic.show();
        withdrawnSimple.show();
        window.Cookies.set('include_withdrawn_students', 'include', cookieArguments);
    }

    $('table').removeClass('table-striped');
    window.updateSimpleGradingRowNumbersAndColors();
    window.updateElectronicGradingRowNumbersAndColors();
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
