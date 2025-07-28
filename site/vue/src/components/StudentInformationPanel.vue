<script setup lang="ts">
import { ref, onMounted } from 'vue';
import VersionChoice from './VersionChoice.vue';

interface Team {
    name?: string;
    members: User[];
}

interface User {
    id: string;
    displayedGivenName: string;
    displayedFamilyName: string;
}

interface Submitter {
    id: string;
    team?: Team;
    user?: User;
}

interface Version {
    points: number;
    days_late: number;
}

export type Versions = Record<number, Version>;

interface Props {
    displayVersion: number;
    activeVersion: number;
    highestVersion: number;
    updateVersionUrl: string;
    csrfToken: string;
    teamAssignment: boolean;
    submitter: Submitter;
    submissionTime?: {
        date: string;
        timezone: string;
    };
    tables: string[];
    versions: Versions;
    totalPoints?: number;
}

const props = defineProps<Props>();

const activeTab = ref(1);

const formatSubmissionTime = (submissionTime?: { date: string; timezone: string }): string => {
    return new Date(submissionTime?.date || '').toLocaleString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZone: submissionTime?.timezone,
        timeZoneName: 'short',
    });
};

const onChangeNavTab = (tab: number): void => {
    const tableLength = props.tables.length;
    if (tab > tableLength || tab <= 0) {
        alert('Invalid Navigation');
        return;
    }
    activeTab.value = tab;
};

const checkForm = (): boolean => {
    // Add form validation logic here
    return true;
};

const handleVersionChange = (version: number) => {
    // Handle version change logic here
    console.log('Version changed to:', version);
    // add gradeable_version to the current URL
    const url = new URL(window.location.href);
    url.searchParams.set('gradeable_version', version.toString());
    window.location.href = url.toString();
};

onMounted(() => {
    // Initialize first tab as active
    onChangeNavTab(1);
});
console.log(props);
</script>

<template>
  <div
    id="student_info"
    class="rubric_panel"
    data-testid="student-info"
  >
    <div>
      <span class="grading_label">Student Information</span>
      <div class="inner-container">
        <h5
          class="label"
          style="float:right; padding-right:15px;"
        >
          Browse Student Submissions:
        </h5>
        <div
          class="rubric-title"
          data-testid="rubric-title"
        >
          <div style="float:right;">
            <VersionChoice
              :formatting="'font-size: 13px;'"
              :active-version="activeVersion"
              :display-version="displayVersion"
              :versions="versions"
              :total-points="totalPoints"
              @change="handleVersionChange"
            />

            <!-- If viewing the active version, show cancel button, otherwise show button to switch active -->
            <form
              v-if="displayVersion > 0"
              id="student-info-ta-version-form"
              style="display: inline;"
              method="post"
              :action="updateVersionUrl"
            >
              <input
                type="hidden"
                name="csrf_token"
                :value="csrfToken"
              />
              <input
                type="submit"
                class="btn btn-default btn-xs"
                style="float:right; margin: 0 10px;"
                :value="displayVersion === activeVersion ? 'Cancel Student Submission' : 'Grade This Version'"
              />
            </form>
            <br v-if="displayVersion > 0" />
            <br v-if="displayVersion > 0" />
          </div>

          <div style="padding-left:10px;">
            <b>
              <template v-if="teamAssignment">
                Team Name: {{ submitter.team?.name || 'Not Set' }}<br />
                Team:<br />
                <div
                  v-for="teamMember in submitter.team?.members"
                  :key="teamMember.id"
                >
                  &emsp;{{ teamMember.displayedGivenName }} {{ teamMember.displayedFamilyName }} ({{ teamMember.id }})<br />
                </div>
              </template>
              <template v-else>
                {{ submitter.user?.displayedGivenName }} {{ submitter.user?.displayedFamilyName }} ({{ submitter.id }})
                <br />
              </template>

              Submission Number: {{ activeVersion }} / {{ highestVersion }}<br />
              Submitted: {{ formatSubmissionTime(submissionTime) }}<br />
            </b>
          </div>

          <br />
          <form
            v-if="teamAssignment"
            id="late-days-form"
            class="form-signin"
            method="post"
            enctype="multipart/form-data"
            @submit="checkForm"
          >
            <div
              class="tab-bar-wrapper"
              data-testid="tab-bar-wrapper"
            >
              <a
                v-for="(teamMember, index) in submitter.team?.members"
                :id="`page_${index + 1}_nav`"
                :key="teamMember.id"
                class="nav-bar key_to_click normal-btn"
                :class="{ 'active-btn': activeTab === index + 1 }"
                @click="onChangeNavTab(index + 1)"
              >
                {{ teamMember.displayedGivenName }} {{ teamMember.displayedFamilyName }} ({{ teamMember.id }})
              </a>
            </div>
            <br />

            <div class="modal-body">
              <div
                v-for="(table, index) in tables"
                :id="`page_${index + 1}_content`"
                :key="index"
                class="page-content"
                :style="{ display: activeTab === index + 1 ? 'block' : 'none' }"
                v-html="table"
              />
            </div>
          </form>
          <div
            v-else
            v-html="tables[0]"
          />
        </div>
      </div>
    </div>
  </div>
</template>
