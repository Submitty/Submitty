import * as Twig from 'twig';
import { PanelElement } from './ts/ta-grading';
import type { ComponentGradeInfo } from './ts/ta-grading-rubric';
export { };

declare global {
    interface Window {
        toggleComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void>;
        onAjaxInit(): void;
        showVerifyComponent(graded_component: ComponentGradeInfo | undefined, grader_id: string): boolean;
        onAddNewMark(me: HTMLElement): Promise<void>;
        onRestoreMark(me: HTMLElement): void;
        onDeleteMark(me: HTMLElement): void;
        onDeleteComponent(me: HTMLElement): Promise<void>;
        importComponentsFromFile(me: HTMLElement): Promise<void>;
        onAddComponent(peer: boolean): Promise<void>;
        onMarkPointsChange(me: HTMLElement): Promise<void>;
        onGetMarkStats(me: HTMLElement): Promise<void>;
        onClickComponent(me: HTMLElement, edit_mode?: boolean): Promise<void>;
        onCancelEditRubricComponent(me: HTMLElement): void;
        onChangeOverallComment(me: HTMLElement): Promise<void>;
        onCancelComponent(me: HTMLElement): Promise<void>;
        onCustomMarkChange(me: HTMLElement): Promise<void>;
        onToggleMark(me: HTMLElement): Promise<void>;
        onToggleCustomMark(me: HTMLElement): Promise<void>;
        onVerifyAll(me: HTMLElement): Promise<void>;
        onVerifyComponent(me: HTMLElement): Promise<void>;
        onClickCountUp(me: HTMLElement): void;
        onClickCountDown(me: HTMLElement): void;
        onComponentPointsChange(me: HTMLElement): Promise<void>;
        onComponentTitleChange(me: HTMLElement): void;
        onComponentPageNumberChange(me: HTMLElement): void;
        onMarkPublishChange(me: HTMLElement): void;
        setPdfPageAssignment(page: number): Promise<void>;
        reloadPeerRubric(gradeable_id: string, anon_id: string): Promise<void>;
        open_overall_comment_tab(user: string): void;
        updateAllComponentVersions(): Promise<void>;
        showSettings(): void;
        restoreAllHotkeys(): void;
        removeAllHotkeys(): void;
        remapHotkey(i: number): void;
        remapUnset(i: number): void;
        updateThePanelsElements(panelsAvailabilityObj: Record<PanelElement, boolean>): void;
        gotoMainPage(): void;
        changePanelsLayout (panelsCount: string | number, isLeftTaller: boolean, twoOnRight: boolean): void;
        exchangeTwoPanels(): void;
        openAll (click_class: string, class_modifier: string): void;
        changeCurrentPeer(): void;
        clearPeerMarks (submitter_id: string, gradeable_id: string, csrf_token: string): void;
        newEditPeerComponentsForm(): void;
        imageRotateIcons (iframe: string): void;
        collapseFile (panel: string): void;
        uploadAttachment(): void;
        deleteAttachment(target: string, file_name: string): void;
        reloadGradingRubric (gradeable_id: string, anon_id: string | undefined): Promise<void>;
        csrfToken: string;
        PDF_PAGE_NONE: number;
        PDF_PAGE_STUDENT: number;
        $: JQueryStatic;
        Twig: typeof Twig;
        togglePanelSelectorModal: (show: boolean) => void;
        taLayoutDet: {
            numOfPanelsEnabled: number;
            isFullScreenMode: boolean;
            isFullLeftColumnMode: boolean;
            currentOpenPanel: string | null;
            currentTwoPanels: Record<string, string | null>;
            currentActivePanels: Record<string, boolean>;
            dividedColName: string;
            leftPanelWidth: string;
            bottomPanelHeight: string;
            bottomFourPanelRightHeight: string;
        };
    }
    interface JQueryStatic {
        active: number;
    }

}
