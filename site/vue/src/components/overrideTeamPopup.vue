<script setup lang="ts">
    const { memberList, isDelete } = defineProps<{
        memberList: Record<string, string>
        isDelete: boolean
    }>()

    function cancel() {
        window.confirmOverride?.(0, isDelete)
    }

    function confirm() {
        window.confirmOverride?.(1, isDelete)
    }
</script>



<template>
  <div class="popup-form" id="override_team_popup">
    <div class="popup-box" @click="cancel">
      <div class="popup-window" @click.stop>
        <div class="form-title">
          <h1>Team Update</h1>
          <button class="btn btn-default close-button" type="button" @click="cancel">
            Close
          </button>
        </div>
        <div class="form-body">
          <h3>This student has one or more teammates. Should this apply to them as well?</h3>
          <p>Team member list:</p>
          <ul>
            <li v-for="(name, id) in memberList" :key="id">
              {{ name }} ({{ id }})
            </li>
          </ul>
          <div class="form-buttons">
            <div class="form-button-container">
              <a class="btn btn-default close-button" @click="cancel">No</a>
              <a class="btn btn-primary" @click="confirm">Yes</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>