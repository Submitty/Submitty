<script setup lang="ts">
declare global {
  interface Window {
    confirmOverride?: (
      userId: string,
      memberList: Record<string, string>,
      fullTeam: boolean
    ) => void;
  }
}

const { memberList, userId } = defineProps<{
  memberList: Record<string, string>;
  userId:    string;
}>();

// remove the popup from the DOM
function closeDomPopup() {
  const el = document.getElementById('override_team_popup');
  if (el) el.remove();
}

function cancel() {
  window.confirmOverride?.(userId, memberList, false);
  closeDomPopup();
}

function confirm() {
  window.confirmOverride?.(userId, memberList, true);
  closeDomPopup();
}
</script>

<template>
  <div id="override_team_popup" class="popup-form">
    <div class="popup-box" @click="cancel">
      <div class="popup-window" @click.stop>
        <div class="form-title">
          <h1>Team Update</h1>
        </div>
        <div class="form-body">
          <h3>
            This student has one or more teammates. Should this apply to them as
            well?
          </h3>
          <p>Team member list:</p>
          <ul>
            <li v-for="(name, id) in memberList" :key="id">
              {{ name }} ({{ id }})
            </li>
          </ul>
          <div class="form-buttons">
            <div class="form-button-container">
              <a
                class="btn btn-default close-button"
                data-testid="deny-team-override"
                @click="cancel"
              >
                No
              </a>
              <a
                class="btn btn-primary"
                data-testid="confirm-team-override"
                @click="confirm"
              >
                Yes
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
