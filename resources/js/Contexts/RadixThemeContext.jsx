import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';

const STORAGE_KEY = 'radix-theme-settings';

export const ACCENT_COLORS = [
  'gray', 'gold', 'bronze', 'brown', 'yellow', 'amber', 'orange',
  'tomato', 'red', 'ruby', 'crimson', 'pink', 'plum', 'purple',
  'violet', 'iris', 'indigo', 'blue', 'cyan', 'teal', 'jade',
  'green', 'grass', 'lime', 'mint', 'sky',
];

export const GRAY_COLORS = ['auto', 'gray', 'mauve', 'slate', 'sage', 'olive', 'sand'];

export const RADIUS_OPTIONS = ['none', 'small', 'medium', 'large', 'full'];

export const SCALING_OPTIONS = ['90%', '95%', '100%', '105%', '110%'];

export const PANEL_BACKGROUNDS = ['solid', 'translucent'];

/*
 * The ten design languages, plus 'none' (stock Radix).
 * `id` is the value written to <html data-design="...">, and is also the
 * filename in resources/css/design/<id>.css.
 * `lockRadius` marks languages where corner radius is constitutive of the
 * style rather than decorative -- Brutalism is not Brutalism with rounded
 * corners, Claymorphism is not Claymorphism without them. For those two the
 * Radius control is disabled rather than silently ignored.
 */
export const DESIGN_LANGUAGES = [
  { id: 'none',           label: 'None',           blurb: 'Stock Radix Themes' },
  { id: 'skeuomorphism',  label: 'Skeuomorphism',  blurb: 'Physical materials, bevels, real textures' },
  { id: 'neomorphism',    label: 'Neomorphism',    blurb: 'Soft extruded surfaces, dual light source' },
  { id: 'glassmorphism',  label: 'Glassmorphism',  blurb: 'Frosted translucency over a lit backdrop' },
  { id: 'claymorphism',   label: 'Claymorphism',   blurb: 'Puffy 3D clay, deep radii', lockRadius: true },
  { id: 'minimalism',     label: 'Minimalism',     blurb: 'Whitespace, hairlines, near-no shadow' },
  { id: 'maximalism',     label: 'Maximalism',     blurb: 'Dense, saturated, layered, expressive' },
  { id: 'brutalism',      label: 'Brutalism',      blurb: 'Hard edges, raw borders, offset shadow', lockRadius: true },
  { id: 'liquidglass',    label: 'Liquid Glass',   blurb: 'Refractive glass with specular edges' },
  { id: 'bentogrid',      label: 'Bento Grid',     blurb: 'Tiled cells, tight gutters, clear bounds' },
  { id: 'spatialui',      label: 'Spatial UI',     blurb: 'Layered depth, parallax, elevation rank' },
];

export const DESIGN_LANGUAGE_IDS = DESIGN_LANGUAGES.map((d) => d.id);

export const FONT_FAMILIES = [
  { label: 'Auto (match design language)', value: 'auto' },
  { label: 'Space Grotesk', value: '"Space Grotesk", system-ui, sans-serif' },
  { label: 'Inter', value: 'Inter, system-ui, sans-serif' },
  { label: 'Roboto', value: 'Roboto, sans-serif' },
  { label: 'Outfit', value: 'Outfit, sans-serif' },
  { label: 'Nunito', value: 'Nunito, sans-serif' },
  { label: 'Exo 2', value: '"Exo 2", sans-serif' },
  { label: 'Josefin Sans', value: '"Josefin Sans", sans-serif' },
  { label: 'System UI', value: 'system-ui, sans-serif' },
];

const DEFAULT_SETTINGS = {
  accentColor: 'blue',
  grayColor: 'auto',
  radius: 'medium',
  scaling: '100%',
  appearance: 'light',
  panelBackground: 'solid',
  fontFamily: 'auto',
  customAccentHex: '',
  bgStyle: 'grid',
  designLanguage: 'none',
};
const RadixThemeContext = createContext(null);

export const useRadixTheme = () => {
  const ctx = useContext(RadixThemeContext);
  if (!ctx) throw new Error('useRadixTheme must be used within RadixThemeProvider');
  return ctx;
};

export const RadixThemeProvider = ({ children }) => {
  const [settings, setSettings] = useState(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        return { ...DEFAULT_SETTINGS, ...JSON.parse(stored) };
      }
    } catch (_) {}
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    return { ...DEFAULT_SETTINGS, appearance: prefersDark ? 'dark' : 'light' };
  });

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (_) {}
    applyFontFamily(settings.fontFamily);
    applyCustomAccent(settings.customAccentHex);
    syncAppearanceClass(settings.appearance);
    syncDesignLanguage(settings.designLanguage);
  }, [settings]);

  const updateSettings = useCallback((patch) => {
    setSettings((prev) => ({ ...prev, ...patch }));
  }, []);

  const resetSettings = useCallback(() => {
    setSettings(DEFAULT_SETTINGS);
  }, []);

  const toggleAppearance = useCallback(() => {
    setSettings((prev) => ({
      ...prev,
      appearance: prev.appearance === 'light' ? 'dark' : 'light',
    }));
  }, []);

  return (
    <RadixThemeContext.Provider value={{ settings, updateSettings, resetSettings, toggleAppearance }}>
      {children}
    </RadixThemeContext.Provider>
  );
};

function applyFontFamily(fontFamily) {
  // 'auto' clears the user override so --dl-font-body from the active design
  // language takes effect. Brutalism is not Brutalism in Inter.
  if (!fontFamily || fontFamily === 'auto') {
    document.documentElement.style.removeProperty('--custom-font-family');
    document.documentElement.style.removeProperty('--default-font-family');
    document.documentElement.style.removeProperty('--fontFamily');
    return;
  }
  if (fontFamily) {
    document.documentElement.style.setProperty('--default-font-family', fontFamily);
    document.documentElement.style.setProperty('--custom-font-family', fontFamily);
    document.documentElement.style.setProperty('--fontFamily', fontFamily);
  }
}

function applyCustomAccent(hex) {
  if (hex && /^#[0-9a-fA-F]{6}$/.test(hex)) {
    document.documentElement.style.setProperty('--accent-9', hex);
  } else {
    document.documentElement.style.removeProperty('--accent-9');
  }
}

function syncAppearanceClass(appearance) {
  const html = document.documentElement;
  if (appearance === 'dark') {
    html.classList.add('dark');
    html.classList.remove('light');
  } else {
    html.classList.add('light');
    html.classList.remove('dark');
  }
}

/*
 * Written to <html>, not to the <Theme> wrapper, so that React portals --
 * dialogs, popovers, tooltips, toasts -- inherit the language too. Those
 * render outside the Theme subtree, and skinning everything except them is
 * the most visible way a design system looks half-applied.
 */
function syncDesignLanguage(language) {
  const id = DESIGN_LANGUAGE_IDS.includes(language) ? language : 'none';
  document.documentElement.setAttribute('data-design', id);
}

export { RadixThemeContext };
