<script setup lang="ts">
import { onMounted, ref } from 'vue';

const emit = defineEmits<{
    'close': [];
    'select-layout': [panels: number, isLeftTaller: boolean, twoInRight?: boolean];
}>();

function selectLayout(panels: number, isLeftTaller: boolean, twoInRight = false) {
    emit('select-layout', panels, isLeftTaller, twoInRight);
}

function close() {
    emit('close');
}

const singlePanel = ref<HTMLCanvasElement | null>(null);
const equalHeight = ref<HTMLCanvasElement | null>(null);
const tallLeft = ref<HTMLCanvasElement | null>(null);
const equalTwoInLeft = ref<HTMLCanvasElement | null>(null);
const equalTwoInRight = ref<HTMLCanvasElement | null>(null);
const tallLeftTwoInLeft = ref<HTMLCanvasElement | null>(null);
const tallLeftTwoInRight = ref<HTMLCanvasElement | null>(null);
const equalFourPanel = ref<HTMLCanvasElement | null>(null);
const tallLeftFourPanel = ref<HTMLCanvasElement | null>(null);

function fillCanvas(canvas: HTMLCanvasElement | null, draw: (ctx: CanvasRenderingContext2D) => void) {
    const ctx = canvas?.getContext('2d');
    if (ctx) {
        draw(ctx);
    }
}

function drawCanvases() {
    fillCanvas(singlePanel.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 288, 120);
    });

    fillCanvas(equalHeight.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 140, 120);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas(tallLeft.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 150);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas(equalTwoInLeft.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 58);
        ctx.fillRect(5, 82, 145, 58);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas(equalTwoInRight.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 120);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas(tallLeftTwoInLeft.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 73);
        ctx.fillRect(0, 77, 145, 73);
        ctx.fillRect(153, 20, 140, 120);
    });

    fillCanvas(tallLeftTwoInRight.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(153, 2, 140, 15);
        ctx.fillRect(0, 0, 145, 150);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas(equalFourPanel.value, (ctx) => {
        ctx.fillStyle = 'aliceblue';
        ctx.fillRect(0, 0, 350, 200);
        ctx.fillStyle = '#6d91b5';
        ctx.fillRect(5, 2, 288, 15);
        ctx.fillRect(5, 20, 145, 58);
        ctx.fillRect(5, 82, 145, 58);
        ctx.fillRect(153, 20, 140, 58);
        ctx.fillRect(153, 82, 140, 58);
    });

    fillCanvas(tallLeftFourPanel.value, (ctx) => {
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

onMounted(drawCanvases);
</script>

<template>
  <div
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
                <canvas ref="singlePanel" />
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
                <canvas ref="equalHeight" />
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
                <canvas ref="tallLeft" />
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
                <canvas ref="equalTwoInLeft" />
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
                <canvas ref="tallLeftTwoInLeft" />
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
                <canvas ref="equalTwoInRight" />
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
                <canvas ref="tallLeftTwoInRight" />
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
                <canvas ref="equalFourPanel" />
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
                <canvas ref="tallLeftFourPanel" />
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
