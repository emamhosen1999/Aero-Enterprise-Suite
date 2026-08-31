import React, { useEffect, useRef, useState } from 'react';
import { Maximize2, Minimize2, X, History, Plus, Download } from 'lucide-react';
import AeonCore from './AeonCore.jsx';
import AeonAura from './AeonAura.jsx';
import AeonConversation from './AeonConversation.jsx';
import { fetchAeonConversations } from './aeonClient.js';

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
  onNewChat,
  onSelectConversation,
  conversationId,
}) {
  const [expanded, setExpanded] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [historyList, setHistoryList] = useState([]);
  const inputRef = useRef(null);
  const state = sending ? 'thinking' : 'listening';

  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e) => {
      if (e.key === 'Escape') {
        if (showHistory) setShowHistory(false);
        else onClose?.();
      }
    };
    window.addEventListener('keydown', onKey);
    const timer = setTimeout(() => inputRef.current?.focus(), 80);
    return () => {
      window.removeEventListener('keydown', onKey);
      clearTimeout(timer);
    };
  }, [isOpen, onClose, showHistory]);

  const toggleHistory = async () => {
    if (!showHistory) {
      try {
        const list = await fetchAeonConversations();
        setHistoryList(list || []);
      } catch {
        setHistoryList([]);
      }
    }
    setShowHistory((v) => !v);
  };

  const exportChat = () => {
    if (!messages.length) return;
    const md = messages
      .map((m) => {
        const role = m.role === 'user' ? '### 👤 User' : '### ✦ Aeon Copilot';
        const text = m.blocks?.map((b) => b.text || b.title || JSON.stringify(b)).join('\n') || m.content || '';
        return `${role}\n\n${text}\n`;
      })
      .join('\n---\n\n');

    const blob = new Blob([`# DBEDC Guardian — Aeon Conversation Transcript\nGenerated: ${new Date().toLocaleString()}\n\n${md}`], {
      type: 'text/markdown;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `aeon-chat-${Date.now()}.md`;
    a.click();
    URL.revokeObjectURL(url);
  };

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

          <div style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
            <button
              type="button"
              onClick={toggleHistory}
              className={`aeon-icon-btn ${showHistory ? 'is-active' : ''}`}
              title="Conversation History"
              aria-label="History"
            >
              <History size={16} />
            </button>

            {onNewChat && (
              <button
                type="button"
                onClick={() => {
                  setShowHistory(false);
                  onNewChat?.();
                }}
                className="aeon-icon-btn"
                title="New Conversation"
                aria-label="New Chat"
              >
                <Plus size={16} />
              </button>
            )}

            {messages.length > 0 && (
              <button
                type="button"
                onClick={exportChat}
                className="aeon-icon-btn"
                title="Export Transcript (.md)"
                aria-label="Export"
              >
                <Download size={15} />
              </button>
            )}

            <button
              type="button"
              onClick={() => setExpanded((v) => !v)}
              className="aeon-icon-btn"
              title={expanded ? 'Collapse' : 'Expand'}
              aria-label={expanded ? 'Collapse' : 'Expand'}
            >
              {expanded ? <Minimize2 size={16} /> : <Maximize2 size={16} />}
            </button>

            <button
              type="button"
              onClick={onClose}
              className="aeon-icon-btn"
              title="Close (Esc)"
              aria-label="Close"
            >
              <X size={18} />
            </button>
          </div>
        </header>

        {/* History Flyout Sidebar */}
        {showHistory && (
          <div className="aeon-history-sidebar">
            <div style={{ padding: '12px 14px', borderBottom: '1px solid var(--aeon-border-glass-strong)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--aeon-text-primary)' }}>Recent Chats</span>
              {onNewChat && (
                <button
                  type="button"
                  onClick={() => {
                    setShowHistory(false);
                    onNewChat();
                  }}
                  className="aeon-icon-btn"
                  style={{ fontSize: 11, gap: 4 }}
                >
                  <Plus size={13} /> New
                </button>
              )}
            </div>
            <div style={{ flex: 1, overflowY: 'auto' }}>
              {historyList.length === 0 ? (
                <div style={{ padding: '24px 14px', textAlign: 'center', fontSize: 12, color: 'var(--aeon-text-muted)' }}>
                  No previous conversations
                </div>
              ) : (
                historyList.map((c) => (
                  <div
                    key={c.id}
                    onClick={() => {
                      setShowHistory(false);
                      onSelectConversation?.(c.id);
                    }}
                    className={`aeon-history-item ${c.id === conversationId ? 'is-active' : ''}`}
                  >
                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {c.title || 'Conversation #' + c.id}
                    </span>
                    <span style={{ fontSize: 10, color: 'var(--aeon-text-muted)', flexShrink: 0 }}>
                      {new Date(c.updated_at).toLocaleDateString([], { month: 'short', day: 'numeric' })}
                    </span>
                  </div>
                ))
              )}
            </div>
          </div>
        )}

        {/* Conversation Body — fills remaining space */}
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
