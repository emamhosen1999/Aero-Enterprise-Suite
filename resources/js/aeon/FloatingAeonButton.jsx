import React from 'react';
import { Sparkles } from 'lucide-react';
import { Tooltip } from '@radix-ui/themes';

/**
 * Floating launcher button positioned bottom-right across all authenticated screens.
 */
export default function FloatingAeonButton({ onClick }) {
  return (
    <div
      style={{
        position: 'fixed',
        bottom: '24px',
        right: '24px',
        zIndex: 9999,
      }}
    >
      <Tooltip content="Ask Aeon Copilot (AI)" side="left">
        <button
          type="button"
          onClick={onClick}
          aria-label="Open Aeon AI Copilot"
          className="group relative flex items-center justify-center cursor-pointer transition-all duration-300 hover:scale-110 active:scale-95"
          style={{
            width: '46px',
            height: '46px',
            borderRadius: '50%',
            background: 'linear-gradient(135deg, var(--accent-9, #22e3ff) 0%, #8c6bff 100%)',
            color: '#ffffff',
            boxShadow: '0 6px 24px rgba(34, 227, 255, 0.5), 0 2px 6px rgba(0, 0, 0, 0.3)',
            border: '2px solid rgba(255, 255, 255, 0.4)',
          }}
        >
          <Sparkles size={22} className="group-hover:rotate-12 transition-transform duration-300 drop-shadow" />
          <span
            className="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full animate-pulse"
            style={{
              background: '#22c55e',
              border: '2px solid var(--color-background, #090d16)',
            }}
          />
        </button>
      </Tooltip>
    </div>
  );
}
