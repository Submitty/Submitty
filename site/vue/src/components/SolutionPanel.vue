<script setup lang="ts">
import { defineProps, ref } from 'vue';
import MarkdownArea from './MarkdownArea.vue';
import { buildCourseUrl } from '../../../ts/utils/server';

type Component = {
    id: string;
    title: string;
    isFirstEdit: boolean;
    author: string;
    solutionNotes: string;
    editedAt: string;
};

interface SolutionTaNotesResponse {
    status: string;
    data: {
        edited_at: string;
        author: string;
        current_user_id: string;
    };
}

const props = defineProps<{
    solutionComponents: Component[];
    gradeableId: string;
    itempoolItem: string;
    isItempoolLinked: boolean;
    currentUserId: string;
}>();

const solutionComponents = ref(props.solutionComponents);

const detectSolutionChange = (componentId: string, newValue: string) => {
    textboxValueRefs.value[componentId] = newValue;
};

function updateSolutionTaNotes(gradeable_id: string, component_id: string, itempool_item: string) {
    const component = solutionComponents.value.find((c) => c.id === component_id);
    if (!component) {
        window.displayErrorMessage('Component not found');
        return;
    }
    const data = {
        solution_text: textboxValueRefs.value[component_id],
        component_id,
        itempool_item,
        csrf_token: window.csrfToken,
    };
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'solution_ta_notes']),
        type: 'POST',
        data,
        success: function (res: string) {
            const response = JSON.parse(res) as SolutionTaNotesResponse;
            if (response.status === 'success') {
                window.displaySuccessMessage('Solution has been updated successfully');
                // Dom manipulation after the Updating/adding the solution note
                solutionComponents.value = solutionComponents.value.map((comp) => {
                    if (comp.id === component_id) {
                        return {
                            ...comp,
                            solutionNotes: textboxValueRefs.value[component_id],
                            editedAt: response.data.edited_at,
                            author: response.data.current_user_id === response.data.author ? `${response.data.author} (You)` : response.data.author,
                        };
                    }
                    return comp;
                });
            }
            else {
                window.displayErrorMessage('Something went wrong while updating the solution');
            }
        },
        error: function (err) {
            console.log(err);
        },
    });
}
const textboxValueRefs = ref<Record<string, string>>({});
for (const component of props.solutionComponents) {
    textboxValueRefs.value[component.id] = component.solutionNotes;
}
</script>

<template>
  <div
    id="solution_ta_notes"
    class="rubric_panel"
    data-testid="solution-ta-notes"
  >
    <div class="row">
      <span class="grading_label">Solution/TA Notes</span>
    </div>
    <div
      v-for="component in solutionComponents"
      :id="'solution-box-' + component.id"
      :key="component.id"
      class="solution-box box"
      :data-first-edit="component.isFirstEdit ? '1' : '0'"
    >
      <div class="solution-header">
        <div class="component-title">
          Solution for {{ component.title }}
          <span v-if="isItempoolLinked">
            (student having {{ itempoolItem }})
          </span>
        </div>
        <div
          v-if="!component.isFirstEdit"
          class="last-edit"
        >
          Last edit @
          <i class="last-edit-time">{{ component.editedAt }}</i> by
          <i class="last-edit-author">{{ component.author
          }}<span v-if="currentUserId === component.author">
            (You)
          </span></i>
        </div>
      </div>
      <div
        class="solution-cont"
        :data-component_id="component.id"
        :data-original-solution="component.solutionNotes || ''"
      >
        <div :id="'sol-textbox-cont-' + component.id + '-edit'">
          <label
            :for="'textbox-solution-' + component.id"
            tabindex="0"
            class="screen-reader"
          >Solution for {{ component.title }}</label>

          <MarkdownArea
            :markdown-area-id="'textbox-solution-' + component.id"
            class="solution-ta-notes-textbox"
            :markdown-area-value="component.solutionNotes"
            :placeholder="
              'Start writing the solution for ' +
                component.title +
                ' component.'
            "
            :preview-div-id="
              'solution_notes_preview_' + component.id
            "
            :render-header="true"
            min-height="150px"
            max-height="400px"
            :no-maxlength="true"
            :initialize-preview="!component.isFirstEdit"
            @update:model-value="
              (newValue) =>
                detectSolutionChange(component.id, newValue)
            "
          />

          <button
            type="button"
            :disabled="textboxValueRefs[component.id] === component.solutionNotes"
            class="btn btn-primary solution-save-btn"
            @click="
              updateSolutionTaNotes(
                gradeableId,
                component.id,
                itempoolItem,
              )
            "
          >
            Save Changes
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped></style>
