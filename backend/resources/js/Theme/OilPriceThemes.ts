/**
 * Oil Price Calculator — Full Theme System
 *
 * Architecture:
 *   BASE: 'dark' | 'light'  →  switches the shell (App.vue CSS blocks handle fallback)
 *   ACCENT: one of 16 keys  →  full palette override applied via JS
 *
 * Each accent defines ALL CSS variables for BOTH dark and light shells.
 * When user picks an accent, ALL 11 CSS vars are overridden — not just --amber.
 * This gives a full visual transformation like WhattNexus / Hospital / New School.
 */

export type BaseMode = 'dark' | 'light';

export interface AccentShell {
    '--bg-deep':      string;
    '--bg-panel':     string;
    '--bg-panel-alt': string;
    '--amber':        string;
    '--amber-dim':    string;
    '--up':           string;
    '--down':         string;
    '--text-hi':      string;
    '--text-mid':     string;
    '--border':       string;
    '--line':         string;
}

export interface AccentDefinition {
    name: string;
    icon: string;
    dark:  AccentShell;
    light: AccentShell;
}

export const AccentColors: Record<string, AccentDefinition> = {

    amber: {
        name: 'Amber', icon: '🔥',
        dark: {
            '--bg-deep':      '#0b0f14',
            '--bg-panel':     '#161e29',
            '--bg-panel-alt': '#1c2634',
            '--amber':        '#f2a63f',
            '--amber-dim':    '#c97f1e',
            '--up':           '#e8604c',
            '--down':         '#4fbe8d',
            '--text-hi':      '#f5f1e8',
            '--text-mid':     '#9faab5',
            '--border':       '#2a3441',
            '--line':         '#2a3441',
        },
        light: {
            '--bg-deep':      '#f3f6fa',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#eef2f7',
            '--amber':        '#d97706',
            '--amber-dim':    '#b45309',
            '--up':           '#c62828',
            '--down':         '#2e7d32',
            '--text-hi':      '#0f172a',
            '--text-mid':     '#475569',
            '--border':       '#cbd5e1',
            '--line':         '#cbd5e1',
        },
    },

    blue: {
        name: 'Blue', icon: '💎',
        dark: {
            '--bg-deep':      '#0b1329',
            '--bg-panel':     '#121e3d',
            '--bg-panel-alt': '#182852',
            '--amber':        '#3b82f6',
            '--amber-dim':    '#2563eb',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#eff6ff',
            '--text-mid':     '#93c5fd',
            '--border':       'rgba(59,130,246,0.25)',
            '--line':         'rgba(59,130,246,0.20)',
        },
        light: {
            '--bg-deep':      '#f0f9ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#e0f2fe',
            '--amber':        '#0284c7',
            '--amber-dim':    '#0369a1',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#0c4a6e',
            '--text-mid':     '#0369a1',
            '--border':       '#bae6fd',
            '--line':         '#bae6fd',
        },
    },

    emerald: {
        name: 'Emerald', icon: '🌲',
        dark: {
            '--bg-deep':      '#061712',
            '--bg-panel':     '#0d261e',
            '--bg-panel-alt': '#133328',
            '--amber':        '#10b981',
            '--amber-dim':    '#059669',
            '--up':           '#f87171',
            '--down':         '#34d399',
            '--text-hi':      '#ecfdf5',
            '--text-mid':     '#6ee7b7',
            '--border':       'rgba(16,185,129,0.25)',
            '--line':         'rgba(16,185,129,0.20)',
        },
        light: {
            '--bg-deep':      '#f0fdf4',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#dcfce7',
            '--amber':        '#16a34a',
            '--amber-dim':    '#15803d',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#14532d',
            '--text-mid':     '#15803d',
            '--border':       '#bbf7d0',
            '--line':         '#bbf7d0',
        },
    },

    rose: {
        name: 'Rose', icon: '🌹',
        dark: {
            '--bg-deep':      '#1a0b0e',
            '--bg-panel':     '#281216',
            '--bg-panel-alt': '#36181e',
            '--amber':        '#ef4444',
            '--amber-dim':    '#dc2626',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#fef2f2',
            '--text-mid':     '#fca5a5',
            '--border':       'rgba(239,68,68,0.25)',
            '--line':         'rgba(239,68,68,0.20)',
        },
        light: {
            '--bg-deep':      '#fff1f2',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#ffe4e6',
            '--amber':        '#e11d48',
            '--amber-dim':    '#be123c',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#881337',
            '--text-mid':     '#be123c',
            '--border':       '#fecdd3',
            '--line':         '#fecdd3',
        },
    },

    purple: {
        name: 'Purple', icon: '💜',
        dark: {
            '--bg-deep':      '#120b24',
            '--bg-panel':     '#1b1236',
            '--bg-panel-alt': '#25184a',
            '--amber':        '#8b5cf6',
            '--amber-dim':    '#7c3aed',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#f5f3ff',
            '--text-mid':     '#c4b5fd',
            '--border':       'rgba(139,92,246,0.25)',
            '--line':         'rgba(139,92,246,0.20)',
        },
        light: {
            '--bg-deep':      '#faf5ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#f3e8ff',
            '--amber':        '#9333ea',
            '--amber-dim':    '#7e22ce',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#581c87',
            '--text-mid':     '#7e22ce',
            '--border':       '#e9d5ff',
            '--line':         '#e9d5ff',
        },
    },

    teal: {
        name: 'Teal', icon: '🌊',
        dark: {
            '--bg-deep':      '#051a18',
            '--bg-panel':     '#0a2825',
            '--bg-panel-alt': '#0f3632',
            '--amber':        '#14b8a6',
            '--amber-dim':    '#0d9488',
            '--up':           '#f87171',
            '--down':         '#34d399',
            '--text-hi':      '#f0fdfa',
            '--text-mid':     '#5eead4',
            '--border':       'rgba(20,184,166,0.25)',
            '--line':         'rgba(20,184,166,0.20)',
        },
        light: {
            '--bg-deep':      '#f0fdfa',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#ccfbf1',
            '--amber':        '#0d9488',
            '--amber-dim':    '#0f766e',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#134e4a',
            '--text-mid':     '#0f766e',
            '--border':       '#99f6e4',
            '--line':         '#99f6e4',
        },
    },

    orange: {
        name: 'Orange', icon: '🟠',
        dark: {
            '--bg-deep':      '#150d07',
            '--bg-panel':     '#201208',
            '--bg-panel-alt': '#2d1a0e',
            '--amber':        '#f97316',
            '--amber-dim':    '#ea580c',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#fff7ed',
            '--text-mid':     '#fdba74',
            '--border':       'rgba(249,115,22,0.25)',
            '--line':         'rgba(249,115,22,0.20)',
        },
        light: {
            '--bg-deep':      '#fff7ed',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#ffedd5',
            '--amber':        '#ea580c',
            '--amber-dim':    '#c2410c',
            '--up':           '#c62828',
            '--down':         '#2e7d32',
            '--text-hi':      '#7c2d12',
            '--text-mid':     '#c2410c',
            '--border':       '#fed7aa',
            '--line':         '#fed7aa',
        },
    },

    sky: {
        name: 'Sky', icon: '🛫',
        dark: {
            '--bg-deep':      '#080f1a',
            '--bg-panel':     '#0d1927',
            '--bg-panel-alt': '#132234',
            '--amber':        '#38bdf8',
            '--amber-dim':    '#0ea5e9',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#f0f9ff',
            '--text-mid':     '#7dd3fc',
            '--border':       'rgba(56,189,248,0.25)',
            '--line':         'rgba(56,189,248,0.20)',
        },
        light: {
            '--bg-deep':      '#f0f9ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#e0f2fe',
            '--amber':        '#0284c7',
            '--amber-dim':    '#0369a1',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#0c4a6e',
            '--text-mid':     '#075985',
            '--border':       '#bae6fd',
            '--line':         '#bae6fd',
        },
    },

    indigo: {
        name: 'Indigo', icon: '🧊',
        dark: {
            '--bg-deep':      '#0c0d1e',
            '--bg-panel':     '#13152f',
            '--bg-panel-alt': '#1c1f42',
            '--amber':        '#818cf8',
            '--amber-dim':    '#6366f1',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#e0e7ff',
            '--text-mid':     '#a5b4fc',
            '--border':       'rgba(129,140,248,0.25)',
            '--line':         'rgba(129,140,248,0.20)',
        },
        light: {
            '--bg-deep':      '#eef2ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#e0e7ff',
            '--amber':        '#4f46e5',
            '--amber-dim':    '#4338ca',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#1e1b4b',
            '--text-mid':     '#4338ca',
            '--border':       '#c7d2fe',
            '--line':         '#c7d2fe',
        },
    },

    gold: {
        name: 'Gold', icon: '💰',
        dark: {
            '--bg-deep':      '#100c02',
            '--bg-panel':     '#1c1504',
            '--bg-panel-alt': '#261e07',
            '--amber':        '#fbbf24',
            '--amber-dim':    '#f59e0b',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#fffbeb',
            '--text-mid':     '#fcd34d',
            '--border':       'rgba(251,191,36,0.25)',
            '--line':         'rgba(251,191,36,0.20)',
        },
        light: {
            '--bg-deep':      '#fffbeb',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#fef3c7',
            '--amber':        '#b45309',
            '--amber-dim':    '#92400e',
            '--up':           '#c62828',
            '--down':         '#2e7d32',
            '--text-hi':      '#451a03',
            '--text-mid':     '#b45309',
            '--border':       '#fde68a',
            '--line':         '#fde68a',
        },
    },

    green: {
        name: 'Green', icon: '🟢',
        dark: {
            '--bg-deep':      '#071209',
            '--bg-panel':     '#0d200f',
            '--bg-panel-alt': '#132d16',
            '--amber':        '#22c55e',
            '--amber-dim':    '#16a34a',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#f0fdf4',
            '--text-mid':     '#86efac',
            '--border':       'rgba(34,197,94,0.25)',
            '--line':         'rgba(34,197,94,0.20)',
        },
        light: {
            '--bg-deep':      '#f0fdf4',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#dcfce7',
            '--amber':        '#15803d',
            '--amber-dim':    '#166534',
            '--up':           '#c62828',
            '--down':         '#2e7d32',
            '--text-hi':      '#14532d',
            '--text-mid':     '#166534',
            '--border':       '#bbf7d0',
            '--line':         '#bbf7d0',
        },
    },

    navy: {
        name: 'Navy', icon: '⚓',
        dark: {
            '--bg-deep':      '#050c1a',
            '--bg-panel':     '#091428',
            '--bg-panel-alt': '#0d1d38',
            '--amber':        '#60a5fa',
            '--amber-dim':    '#3b82f6',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#eff6ff',
            '--text-mid':     '#93c5fd',
            '--border':       'rgba(96,165,250,0.20)',
            '--line':         'rgba(96,165,250,0.15)',
        },
        light: {
            '--bg-deep':      '#eff6ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#dbeafe',
            '--amber':        '#1e40af',
            '--amber-dim':    '#1e3a8a',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#1e3a8a',
            '--text-mid':     '#1d4ed8',
            '--border':       '#bfdbfe',
            '--line':         '#bfdbfe',
        },
    },

    crimson: {
        name: 'Crimson', icon: '❤️',
        dark: {
            '--bg-deep':      '#160509',
            '--bg-panel':     '#220810',
            '--bg-panel-alt': '#2e0d17',
            '--amber':        '#fb7185',
            '--amber-dim':    '#f43f5e',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#fff1f2',
            '--text-mid':     '#fda4af',
            '--border':       'rgba(251,113,133,0.25)',
            '--line':         'rgba(251,113,133,0.20)',
        },
        light: {
            '--bg-deep':      '#fff1f2',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#ffe4e6',
            '--amber':        '#be123c',
            '--amber-dim':    '#9f1239',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#881337',
            '--text-mid':     '#be123c',
            '--border':       '#fecdd3',
            '--line':         '#fecdd3',
        },
    },

    cyan: {
        name: 'Cyan', icon: '🏥',
        dark: {
            '--bg-deep':      '#040f14',
            '--bg-panel':     '#091820',
            '--bg-panel-alt': '#0e222c',
            '--amber':        '#22d3ee',
            '--amber-dim':    '#06b6d4',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#ecfeff',
            '--text-mid':     '#67e8f9',
            '--border':       'rgba(34,211,238,0.25)',
            '--line':         'rgba(34,211,238,0.20)',
        },
        light: {
            '--bg-deep':      '#ecfeff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#cffafe',
            '--amber':        '#0891b2',
            '--amber-dim':    '#0e7490',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#164e63',
            '--text-mid':     '#0e7490',
            '--border':       '#a5f3fc',
            '--line':         '#a5f3fc',
        },
    },

    olive: {
        name: 'Olive', icon: '🫒',
        dark: {
            '--bg-deep':      '#0c1008',
            '--bg-panel':     '#141a0c',
            '--bg-panel-alt': '#1c2410',
            '--amber':        '#a3e635',
            '--amber-dim':    '#84cc16',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#f7fee7',
            '--text-mid':     '#bef264',
            '--border':       'rgba(163,230,53,0.25)',
            '--line':         'rgba(163,230,53,0.20)',
        },
        light: {
            '--bg-deep':      '#f7fce1',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#ecfccb',
            '--amber':        '#4d7c0f',
            '--amber-dim':    '#3f6212',
            '--up':           '#c62828',
            '--down':         '#2e7d32',
            '--text-hi':      '#1a2e05',
            '--text-mid':     '#4d7c0f',
            '--border':       '#bef264',
            '--line':         '#bef264',
        },
    },

    violet: {
        name: 'Violet', icon: '🌌',
        dark: {
            '--bg-deep':      '#0f0b1a',
            '--bg-panel':     '#180f28',
            '--bg-panel-alt': '#221536',
            '--amber':        '#c084fc',
            '--amber-dim':    '#a855f7',
            '--up':           '#f87171',
            '--down':         '#4ade80',
            '--text-hi':      '#faf5ff',
            '--text-mid':     '#d8b4fe',
            '--border':       'rgba(192,132,252,0.25)',
            '--line':         'rgba(192,132,252,0.20)',
        },
        light: {
            '--bg-deep':      '#faf5ff',
            '--bg-panel':     '#ffffff',
            '--bg-panel-alt': '#f3e8ff',
            '--amber':        '#7c3aed',
            '--amber-dim':    '#6d28d9',
            '--up':           '#dc2626',
            '--down':         '#16a34a',
            '--text-hi':      '#2e1065',
            '--text-mid':     '#6d28d9',
            '--border':       '#e9d5ff',
            '--line':         '#e9d5ff',
        },
    },

};

export const DEFAULT_BASE: BaseMode = 'dark';
export const DEFAULT_ACCENT = 'amber';
