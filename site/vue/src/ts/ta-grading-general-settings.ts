import type { Ref } from 'vue';
import { getFirstOpenComponentId, NO_COMPONENT_ID } from '../../../ts/ta-grading-rubric';

export type SettingsValue = {
    name: string;
    storageCode: string;
    options: Record<string, string>;
    default: string;
    currValue: string;
};

export type SettingsData = {
    id: string;
    name: string;
    values: SettingsValue[];
}[];

export const getDefaultSettingsData = (fullAccess: boolean): SettingsData => {
    return [
        {
            id: 'general-setting-list',
            name: 'General',
            values: [
                {
                    name: 'Prev/Next student arrow functionality',
                    storageCode: 'general-setting-arrow-function',
                    options: {
                        'Prev/Next Student': 'default',
                        'Prev/Next Ungraded Student': 'ungraded',
                        'Prev/Next Itempool Student': 'itempool',
                        'Prev/Next Ungraded Itempool Student': 'ungraded-itempool',
                        'Prev/Next Grade Inquiry': 'inquiry',
                        'Prev/Next Active Grade Inquiry': 'active-inquiry',
                    },
                    default: 'Prev/Next Student',
                    currValue: 'default',
                },
                {
                    name: 'Prev/Next buttons navigate through',
                    storageCode: 'general-setting-navigate-assigned-students-only',
                    options: fullAccess
                        ? {
                                'All students': 'false',
                                'Only students in assigned registration/rotation sections': 'true',
                            }
                        : { } as Record<string, string>,
                    default: 'Only students in assigned registration/rotation sections',
                    currValue: 'true',
                },
            ],
        },
        {
            id: 'notebook-setting-list',
            name: 'Notebook',
            values: [
                {
                    name: 'Expand files in notebook file submission on page load',
                    storageCode: 'notebook-setting-file-submission-expand',
                    options: {
                        No: 'false',
                        Yes: 'true',
                    },
                    default: 'No',
                    currValue: 'false',
                },
            ],
        },
    ];
};

export const loadTAGradingSettingData = (settingsData: Ref<SettingsData>) => {
    const settingVal: SettingsData = settingsData.value;
    for (let i = 0; i < settingVal.length; i++) {
        for (let x = 0; x < settingVal[i].values.length; x++) {
            const currentSetting = settingVal[i].values[x];
            const localStorageValue = localStorage.getItem(currentSetting.storageCode);
            if (currentSetting.storageCode === 'general-setting-arrow-function') {
                // if the inquiry status is on, we set to active inquiry. Otherwise we check localStorage
                currentSetting.currValue = window.Cookies.get('inquiry_status') === 'on'
                    ? 'active-inquiry'
                    : localStorageValue || currentSetting.options[currentSetting.default];
            }
            else {
                // for other settings, we check localStorage first, then cookies, then default
                currentSetting.currValue = localStorageValue || currentSetting.options[currentSetting.default];
            }
        }
    }
};

// TODO: This could be improved
function loadStudentArrowTooltip(data: string): [string, string] {
    let filteredData = data;
    const inquiry_status = window.Cookies.get('inquiry_status');
    if (inquiry_status === 'on') {
        filteredData = 'active-inquiry';
    }
    else {
        // if inquiry_status is off, and data equals active inquiry means the user set setting to active-inquiry manually
        // and need to set back to default since user also manually changed inquiry_status to off.
        if (filteredData === 'active-inquiry') {
            filteredData = 'default';
        }
    }
    let component_id = NO_COMPONENT_ID;
    switch (filteredData) {
        case 'ungraded':
            component_id = getFirstOpenComponentId(false);
            if (component_id === NO_COMPONENT_ID) {
                return ['Previous ungraded student', 'Next ungraded student'];
            }
            else {
                return [`Next ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    `Next ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
            }
        case 'itempool':
            component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                return ['Previous student', 'Next student'];
            }
            else {
                return [`Previous student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    `Next student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
            }
        case 'ungraded-itempool':
            component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                component_id = getFirstOpenComponentId();
                if (component_id === NO_COMPONENT_ID) {
                    return ['Previous ungraded student', 'Next ungraded student'];
                }
                else {
                    return [`Previous ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                        `Next ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
                }
            }
            else {
                return [`Previous ungraded student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    `Next ungraded student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
            }
        case 'inquiry':
            component_id = getFirstOpenComponentId();
            if (component_id === NO_COMPONENT_ID) {
                return ['Previous student with inquiry', 'Next student with inquiry'];
            }
            else {
                return [`Previous student with inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    `Next student with inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
            }
        case 'active-inquiry':
            component_id = getFirstOpenComponentId();
            if (component_id === NO_COMPONENT_ID) {
                return ['Previous student with active inquiry', 'Next student with active inquiry'];
            }
            else {
                return [`Previous student with active inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    `Next student with active inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`];
            }
        default:
            return ['Previous student', 'Next student'];
    }
}

export const optionsCallback = (option: SettingsValue, emit: (event: 'changeNavigationTitles', args: [string, string]) => void) => {
    switch (option.storageCode) {
        case 'general-setting-arrow-function':
            window.Cookies.set('inquiry_status', option.currValue !== 'active-inquiry' ? 'off' : 'on');
            emit('changeNavigationTitles', loadStudentArrowTooltip(option.currValue));
            break;
        case 'general-setting-navigate-assigned-students-only':
            window.Cookies.set('view', option.currValue === 'true' ? 'assigned' : 'all', { path: '/' });
            break;
    }
};
