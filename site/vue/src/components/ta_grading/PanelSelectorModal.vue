<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    visible?: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const show = ref(props.visible);

function onTogglePanelModal(e: CustomEvent<boolean>) {
    show.value = e.detail;
}

function close() {
    show.value = false;
    emit('close');
}

function selectLayout(panels: number, isLeftTaller: boolean, twoInRight = false) {
    window.changePanelsLayout(panels, isLeftTaller, twoInRight);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && show.value) {
        close();
    }
}

function fillCanvas(id: string, draw: (ctx: CanvasRenderingContext2D) => void) {
    const canvas = document.getElementById(id) as HTMLCanvasElement | null;
    const ctx = canvas?.getContext('2d');
    if (ctx) {
        draw(ctx);
    }
}

function drawCanvases() {
    fillCanvas('single-panel', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 288, 120);
    });

    fillCanvas('equal-height', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 140, 120);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas('tall-left', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 150);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas('equal-two-in-left', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 58);
        ctx.fillRect(5, 82, 145, 58);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas('equal-two-in-right', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 120);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas('tall-left-two-in-left', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 73);
        ctx.fillRect(0, 77, 145, 73);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas('tall-left-two-in-right', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 150);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas('equal-four-panel', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 58);
        ctx.fillRect(5, 82, 145, 58);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas('tall-left-four-panel', (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 73);
        ctx.fillRect(0, 77, 145, 73);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });
}

onMounted(() => {
    window.addEventListener('toggle-panel-modal', onTogglePanelModal as EventListener);
    document.addEventListener('keydown', onKeydown);
    drawCanvases();
});

onUnmounted(() => {
    window.removeEventListener('toggle-panel-modal', onTogglePanelModal as EventListener);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <div
    v-show="show"
    id="panels-selector-modal"
    class="popup-form"
  >
    <div
      class="popup-box"
      @click="close"
    >
      <div
        class="popup-window"
        data-testid="popup-window"
        @click.stop
      >
        <div class="form-title">
          <h1>Panel Selector</h1>
          <button
            data-testid="close-button"
            class="btn btn-default close-button"
            tabindex="-1"
            type="button"
            @click="close"
          >
            Close
          </button>
        </div>
        <div class="form-body">
          <div
            id="layout-option-1"
            class="layout-option"
          >
            <div class="layout-option-title">
              <h2>Single panel option</h2>
            </div>
            <hr />
            <div class="layout-option-cont">
              <div class="layout-option-item">
                <canvas id="single-panel" />
                <div class="flex-col">
                  <span>single panel</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(1, false)"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div
            id="layout-option-2"
            class="layout-option"
          >
            <div class="layout-option-title">
              <h2>Two panel options</h2>
            </div>
            <hr />
            <div class="layout-option-cont">
              <div class="layout-option-item">
                <canvas id="equal-height" />
                <div class="flex-col">
                  <span>side-by-side</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(2, false)"
                  >
                    Apply
                  </button>
                </div>
              </div>
              <div class="layout-option-item">
                <canvas id="tall-left" />
                <div class="flex-col">
                  <span>side-by-side, taller left</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(2, true)"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div
            id="layout-option-3"
            class="layout-option"
          >
            <div class="layout-option-title">
              <h2>Three panel options</h2>
            </div>
            <hr />
            <div class="layout-option-cont">
              <div class="layout-option-item">
                <canvas id="equal-two-in-left" />
                <div class="flex-col">
                  <span>two on left, one on right</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(3, false)"
                  >
                    Apply
                  </button>
                </div>
              </div>
              <div class="layout-option-item">
                <canvas id="tall-left-two-in-left" />
                <div class="flex-col">
                  <span>two on left, one on right, taller left</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(3, true)"
                  >
                    Apply
                  </button>
                </div>
              </div>
              <div class="layout-option-item">
                <canvas id="equal-two-in-right" />
                <div class="flex-col">
                  <span>one on left, two on right</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(3, false, true)"
                  >
                    Apply
                  </button>
                </div>
              </div>
              <div class="layout-option-item">
                <canvas id="tall-left-two-in-right" />
                <div class="flex-col">
                  <span>one on left, two on right, taller left</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(3, true, true)"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div
            id="layout-option-4"
            class="layout-option"
          >
            <div class="layout-option-title">
              <h2>Four panel options</h2>
            </div>
            <hr />
            <div class="layout-option-cont">
              <div class="layout-option-item">
                <canvas id="equal-four-panel" />
                <div class="flex-col">
                  <span>two on left, two on right</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(4, false)"
                  >
                    Apply
                  </button>
                </div>
              </div>
              <div class="layout-option-item">
                <canvas id="tall-left-four-panel" />
                <div class="flex-col">
                  <span>two on left, two on right, taller left</span>
                  <button
                    type="button"
                    class="btn btn-primary"
                    @click="selectLayout(4, true)"
                  >
                    Apply
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
