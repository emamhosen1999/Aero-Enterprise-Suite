import React from 'react';
import { Sparkles } from 'lucide-react';

/**
 * Fixed floating launcher button positioned bottom-right on every authenticated screen.
 */
export default function FloatingAeonButton({ onClick }) {
  return (
    <div className="fixed bottom-6 right-6 z-[9990]">
      <button
        type="button"
        onClick={onClick}
        className="group relative flex items-center justify-center w-12 h-12 rounded-full bg-[var(--accent-9,#22e3ff)] text-[var(--accent-contrast,#000)] shadow-lg hover:shadow-[0_0_24px_rgba(34,227,255,0.6)] hover:scale-105 active:scale-95 transition-all duration-200 cursor-pointer border border-white/20"
        title="Ask Aeon AI Copilot"
        aria-label="Open Aeon Assistant"
      >
        <Sparkles size={22} className="group-hover:rotate-12 transition-transform duration-300" />
        <span className="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-[var(--color-background,#0f172a)]" />
      </button>
    </div>
  );
}
