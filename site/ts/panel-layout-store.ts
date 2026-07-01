type Listener = (state: { numOfPanels: number; dividedColName: 'LEFT' | 'RIGHT' }) => void;

interface StoreData {
    state: { numOfPanels: number; dividedColName: 'LEFT' | 'RIGHT' };
    listeners: Listener[];
}

declare global {
    interface Window {
        __submittyPanelLayoutStore__?: StoreData;
    }
}

const GLOBAL_KEY = '__submittyPanelLayoutStore__';

function getOrCreateStore(): StoreData {
    if (typeof window !== 'undefined' && !window[GLOBAL_KEY]) {
        window[GLOBAL_KEY] = {
            state: loadSavedState(),
            listeners: [],
        };
    }
    return window[GLOBAL_KEY]!;
}

function loadSavedState() {
    try {
        const saved = localStorage.getItem('taLayoutDetails');
        if (saved) {
            const data = JSON.parse(saved) as { numOfPanelsEnabled?: number; dividedColName?: string };
            return {
                numOfPanels: data.numOfPanelsEnabled ?? 1,
                dividedColName: (data.dividedColName ?? 'LEFT') as 'LEFT' | 'RIGHT',
            };
        }
    }
    catch {
        // Ignore parse errors
    }
    return { numOfPanels: 1, dividedColName: 'LEFT' as 'LEFT' | 'RIGHT' };
}

export function getLayoutState() {
    return getOrCreateStore().state;
}

export function updateLayout(numOfPanels: number, dividedColName: 'LEFT' | 'RIGHT') {
    const store = getOrCreateStore();
    store.state = { numOfPanels, dividedColName };
    store.listeners.forEach((fn) => fn(store.state));
}

export function onLayoutChange(fn: Listener): () => void {
    const store = getOrCreateStore();
    store.listeners.push(fn);
    fn(store.state);
    return () => {
        const idx = store.listeners.indexOf(fn);
        if (idx >= 0) {
            store.listeners.splice(idx, 1);
        }
    };
}
