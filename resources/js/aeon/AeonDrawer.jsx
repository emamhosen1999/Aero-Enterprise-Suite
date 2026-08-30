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
    const timer = setTimeout(() => inputRef.current?.focus(), 80);
    return () => {
      window.removeEventListener('keydown', onKey);
      clearTimeout(timer);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <>
      {/* Dimmed Backdrop */}
      <div
        className="aeon-drawer-backdrop"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Slide-Over Drawer Panel */}
      <aside
        className={`aeon-drawer-panel ${expanded ? 'is-expanded' : ''}`}
        role="dialog"
        aria-label="Aeon AI Copilot"
        aria-modal="true"
      >
        <AeonAura />

        {/* Header */}
        <header className="aeon-header">
          <div className="aeon-header-brand">
            <AeonCore state={state} size={34} />
            <div>
              <div className="aeon-header-title">
                <span>Aeon</span>
                <span className="aeon-header-badge">Copilot</span>
              </div>
              <div className="aeon-header-status">
                <span className={`aeon-status-dot ${sending ? 'is-thinking' : ''}`} />
                <span>{STATUS[state]}</span>
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
            <button
              type="button"
              onClick={() => setExpanded((v) => !v)}
              className="aeon-icon-btn"
              title={expanded ? 'Collapse Drawer' : 'Expand Drawer'}
              aria-label={expanded ? 'Collapse' : 'Expand'}
            >
              {expanded ? <Minimize2 size={16} /> : <Maximize2 size={16} />}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="aeon-icon-btn"
              title="Close Aeon"
              aria-label="Close"
            >
              <X size={18} />
            </button>
          </div>
        </header>

        {/* Conversation Body */}
        <div className="aeon-conversation-wrap">
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
      </aside>
    </>
  );
}
