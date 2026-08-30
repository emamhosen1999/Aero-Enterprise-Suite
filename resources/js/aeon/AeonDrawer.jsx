import React, { useEffect, useRef, useState } from 'react';
import { Maximize2, Minimize2, X } from 'lucide-react';
import AeonCore from './AeonCore.jsx';
import AeonAura from './AeonAura.jsx';
import AeonConversation from './AeonConversation.jsx';

const STATUS = {
  idle: 'Online',
  listening: 'Listening…',
  thinking: 'Reasoning…',
  speaking: 'Responding…',
};

export default function AeonDrawer({
  isOpen,
  onClose,
  messages,
  sending,
  stage,
  usage,
  onSend,
  onAction,
  onFeedback,
  user,
  hasAnimated,
  markAnimated,
}) {
  const [expanded, setExpanded] = useState(false);
  const inputRef = useRef(null);
  const state = sending ? 'thinking' : 'listening';

  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e) => {
      if (e.key === 'Escape') onClose?.();
    };
    window.addEventListener('keydown', onKey);
    const timer = setTimeout(() => inputRef.current?.focus(), 60);
    return () => {
      window.removeEventListener('keydown', onKey);
      clearTimeout(timer);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[99999] flex justify-end" role="dialog" aria-label="Aeon Assistant" aria-modal="true">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        onClick={onClose}
      />

      {/* Drawer Panel */}
      <div
        className={`relative z-10 flex flex-col h-full bg-[var(--color-background,#090d16)] border-l border-[var(--gray-5,rgba(255,255,255,0.1))] shadow-2xl transition-all duration-300 ${
          expanded ? 'w-full md:w-[720px]' : 'w-full sm:w-[420px]'
        }`}
      >
        <AeonAura />

        {/* Header */}
        <header className="relative z-10 flex items-center justify-between px-4 py-3.5 border-b border-[var(--gray-4,rgba(255,255,255,0.08))] bg-[var(--gray-1,rgba(0,0,0,0.2))]">
          <div className="flex items-center gap-3">
            <AeonCore state={state} size={36} />
            <div>
              <div className="flex items-center gap-1.5 font-bold text-sm text-[var(--gray-12)]">
                <span>Aeon</span>
                <span className="px-1.5 py-0.2 rounded text-[10px] uppercase font-bold tracking-wider bg-[var(--accent-3,rgba(34,227,255,0.15))] text-[var(--accent-11,#22e3ff)] border border-[var(--accent-6)]">
                  Copilot
                </span>
              </div>
              <div className="flex items-center gap-1.5 text-[11px] text-[var(--gray-10)]">
                <span className={`w-1.5 h-1.5 rounded-full ${sending ? 'bg-amber-400 animate-ping' : 'bg-green-400'}`} />
                <span>{STATUS[state]}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setExpanded((v) => !v)}
              className="p-1.5 rounded-lg text-[var(--gray-10)] hover:text-[var(--gray-12)] hover:bg-[var(--gray-4)] transition-colors cursor-pointer"
              title={expanded ? 'Collapse' : 'Expand'}
            >
              {expanded ? <Minimize2 size={16} /> : <Maximize2 size={16} />}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="p-1.5 rounded-lg text-[var(--gray-10)] hover:text-[var(--gray-12)] hover:bg-[var(--gray-4)] transition-colors cursor-pointer"
              title="Close"
            >
              <X size={18} />
            </button>
          </div>
        </header>

        {/* Conversation Body */}
        <div className="relative z-10 flex-1 min-h-0">
          <AeonConversation
            messages={messages}
            sending={sending}
            stage={stage}
            usage={usage}
            onSend={onSend}
            onAction={onAction}
            onFeedback={onFeedback}
            user={user}
            hasAnimated={hasAnimated}
            markAnimated={markAnimated}
            inputRef={inputRef}
          />
        </div>
      </div>
    </div>
  );
}
