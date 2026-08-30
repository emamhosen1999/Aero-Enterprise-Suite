import React, { useEffect } from 'react';
import { Sparkles } from 'lucide-react';
import { Tooltip } from '@radix-ui/themes';

/**
 * Fixed floating launcher button positioned bottom-right on every authenticated screen.
 * Supports hotkey Ctrl+J / Cmd+J to toggle.
 */
export default function FloatingAeonButton({ onClick }) {
  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'j' || e.key === 'J')) {
        e.preventDefault();
        onClick?.();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClick]);

  return (
    <div className="aeon-fab-container">
      <Tooltip content="Aeon Copilot (Ctrl + J)" side="left">
        <button
          type="button"
          onClick={onClick}
          aria-label="Open Aeon AI Copilot"
          className="aeon-fab"
        >
          <Sparkles size={22} className="drop-shadow" />
          <span className="aeon-fab-badge animate-pulse" />
        </button>
      </Tooltip>
    </div>
  );
}
