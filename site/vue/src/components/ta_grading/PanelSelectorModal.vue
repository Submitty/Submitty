<script setup lang="ts">
import PanelSelector from './PanelSelector.vue';
import Popup from '../../components/Popup.vue';

const emit = defineEmits<{
    'close': [];
    'select-layout': [layout: { panels: number; isLeftTaller: boolean; twoInRight: boolean }];
}>();

function selectLayout(panels: number, isLeftTaller: boolean, twoInRight = false) {
    emit('select-layout', { panels, isLeftTaller, twoInRight });
}

function rect(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, fill = '#6d91b5') {
    ctx.fillStyle = fill;
    ctx.fillRect(x, y, w, h);
}

interface LayoutOption {
    id: string;
    label: string;
    draw: (ctx: CanvasRenderingContext2D) => void;
    testid: string;
    onSelect: () => void;
}

const sections: { id: string; title: string; options: LayoutOption[] }[] = [
    {
        id: 'layout-option-1',
        title: 'Single panel option',
        options: [
            {
                id: 'single-panel',
                label: 'single panel',
                testid: 'layout-single-panel-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 5, 2, 288, 15);
                    rect(ctx, 5, 20, 288, 120);
                },
                onSelect: () => selectLayout(1, false),
            },
        ],
    },
    {
        id: 'layout-option-2',
        title: 'Two panel options',
        options: [
            {
                id: 'equal-height',
                label: 'side-by-side',
                testid: 'layout-two-panel-equal-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 5, 2, 288, 15);
                    rect(ctx, 5, 20, 140, 120);
                    rect(ctx, 153, 20, 140, 120);
                },
                onSelect: () => selectLayout(2, false),
            },
            {
                id: 'tall-left',
                label: 'side-by-side, taller left',
                testid: 'layout-two-panel-tall-left-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 153, 2, 140, 15);
                    rect(ctx, 0, 0, 145, 150);
                    rect(ctx, 153, 20, 140, 120);
                },
                onSelect: () => selectLayout(2, true),
            },
        ],
    },
    {
        id: 'layout-option-3',
        title: 'Three panel options',
        options: [
            {
                id: 'equal-two-in-left',
                label: 'two on left, one on right',
                testid: 'layout-three-panel-two-left-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 5, 2, 288, 15);
                    rect(ctx, 5, 20, 145, 58);
                    rect(ctx, 5, 82, 145, 58);
                    rect(ctx, 153, 20, 140, 120);
                },
                onSelect: () => selectLayout(3, false),
            },
            {
                id: 'tall-left-two-in-left',
                label: 'two on left, one on right, taller left',
                testid: 'layout-three-panel-two-left-tall-left-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 153, 2, 140, 15);
                    rect(ctx, 0, 0, 145, 73);
                    rect(ctx, 0, 77, 145, 73);
                    rect(ctx, 153, 20, 140, 120);
                },
                onSelect: () => selectLayout(3, true),
            },
            {
                id: 'equal-two-in-right',
                label: 'one on left, two on right',
                testid: 'layout-three-panel-two-right-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 5, 2, 288, 15);
                    rect(ctx, 5, 20, 145, 120);
                    rect(ctx, 153, 20, 140, 58);
                    rect(ctx, 153, 82, 140, 58);
                },
                onSelect: () => selectLayout(3, false, true),
            },
            {
                id: 'tall-left-two-in-right',
                label: 'one on left, two on right, taller left',
                testid: 'layout-three-panel-two-right-tall-left-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 153, 2, 140, 15);
                    rect(ctx, 0, 0, 145, 150);
                    rect(ctx, 153, 20, 140, 58);
                    rect(ctx, 153, 82, 140, 58);
                },
                onSelect: () => selectLayout(3, true, true),
            },
        ],
    },
    {
        id: 'layout-option-4',
        title: 'Four panel options',
        options: [
            {
                id: 'equal-four-panel',
                label: 'two on left, two on right',
                testid: 'layout-four-panel-equal-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 5, 2, 288, 15);
                    rect(ctx, 5, 20, 145, 58);
                    rect(ctx, 5, 82, 145, 58);
                    rect(ctx, 153, 20, 140, 58);
                    rect(ctx, 153, 82, 140, 58);
                },
                onSelect: () => selectLayout(4, false),
            },
            {
                id: 'tall-left-four-panel',
                label: 'two on left, two on right, taller left',
                testid: 'layout-four-panel-tall-left-apply',
                draw: (ctx) => {
                    rect(ctx, 0, 0, 350, 200, 'aliceblue');
                    rect(ctx, 153, 2, 140, 15);
                    rect(ctx, 0, 0, 145, 73);
                    rect(ctx, 0, 77, 145, 73);
                    rect(ctx, 153, 20, 140, 58);
                    rect(ctx, 153, 82, 140, 58);
                },
                onSelect: () => selectLayout(4, true),
            },
        ],
    },
];
</script>

<template>
  <Popup
    id="panels-selector-modal"
    title="Panel Selector"
    :visible="true"
    @toggle="$emit('close')"
  >
    <template #trigger>
      <span class="trigger-placeholder" />
    </template>
    <div class="form-body">
      <div
        v-for="section in sections"
        :id="section.id"
        :key="section.title"
        class="layout-option"
      >
        <div class="layout-option-title">
          <h2>{{ section.title }}</h2>
        </div>
        <hr />
        <div class="layout-option-cont">
          <PanelSelector
            v-for="opt in section.options"
            :key="opt.id"
            :option-id="opt.id"
            :label="opt.label"
            :draw="opt.draw"
            :testid="opt.testid"
            @select="opt.onSelect"
          />
        </div>
      </div>
    </div>
  </Popup>
</template>

<style scoped>
.trigger-placeholder {
  display: none;
}
</style>
