import React, { useEffect, useRef, useState } from 'react';
import { Flex, Box, Text, Avatar } from '@radix-ui/themes';
import { Send, ThumbsUp, ThumbsDown } from 'lucide-react';
import AeonCore from './AeonCore.jsx';
import BlockRenderer from './BlockRenderer.jsx';

const SUGGESTIONS = [
  { icon: '⚠️', text: 'How many open NCRs require inspection?' },
  { icon: '📊', text: 'Break down daily attendance by status today' },
  { icon: '📋', text: 'Show recent site work objections' },
  { icon: '💰', text: 'What is our current petty cash summary?' },
];

function UserAvatar({ user }) {
  const name = user?.name || user?.full_name || 'You';
  return (
    <Avatar
      fallback={name.slice(0, 2).toUpperCase()}
      src={user?.avatar_url || user?.profile_photo_url}
      size="1"
      radius="full"
      color="cyan"
    />
  );
}

export default function AeonConversation({
  messages = [],
  sending,
  stage,
  usage,
  onSend,
  onAction,
  onFeedback,
  user,
  hasAnimated,
  markAnimated,
  inputRef,
}) {
  const [draft, setDraft] = useState('');
  const scrollRef = useRef(null);

  useEffect(() => {
    const el = scrollRef.current;
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }, [messages, sending, stage]);

  const submit = (e) => {
    e.preventDefault();
    if (!draft.trim() || sending) return;
    onSend(draft);
    setDraft('');
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      submit(e);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%', minHeight: 0 }}>
      {/* Messages Scroll Area */}
      <div ref={scrollRef} className="aeon-messages-scroll">
        {messages.length === 0 ? (
          <div className="aeon-welcome-card">
            <AeonCore state="idle" size={56} />
            <div className="aeon-welcome-title">Welcome to Aeon Copilot</div>
            <div className="aeon-welcome-subtitle">
              Your intelligent operations, quality control, and HR copilot across DBEDC Guardian.
            </div>

            <div className="aeon-quick-prompts">
              {SUGGESTIONS.map((s) => (
                <button
                  type="button"
                  key={s.text}
                  onClick={() => onSend(s.text)}
                  className="aeon-prompt-chip"
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span style={{ fontSize: '15px' }}>{s.icon}</span>
                    <span>{s.text}</span>
                  </span>
                  <span style={{ opacity: 0.5 }}>→</span>
                </button>
              ))}
            </div>
          </div>
        ) : (
          messages.map((m, i) => {
            const key = m.id ?? i;
            const animate =
              m.role !== 'user' &&
              i === messages.length - 1 &&
              !(hasAnimated ? hasAnimated(key) : false);

            return (
              <div
                key={key}
                className={`aeon-message ${m.role === 'user' ? 'is-user' : 'is-assistant'}`}
              >
                {m.role === 'user' ? (
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '8px', justifyContent: 'flex-end' }}>
                    <div className="aeon-user-bubble">
                      <BlockRenderer
                        blocks={m.blocks}
                        onAction={onAction}
                        animate={false}
                      />
                    </div>
                    <UserAvatar user={user} />
                  </div>
                ) : (
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '8px' }}>
                    <div
                      style={{
                        width: '26px',
                        height: '26px',
                        borderRadius: '50%',
                        background: 'var(--accent-3, rgba(34,227,255,0.15))',
                        color: 'var(--accent-11, #22e3ff)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '12px',
                        fontWeight: 'bold',
                        flexShrink: 0,
                        marginTop: '2px',
                        border: '1px solid var(--accent-6, rgba(34,227,255,0.3))',
                      }}
                    >
                      ✦
                    </div>
                    <div className="aeon-assistant-bubble">
                      <BlockRenderer
                        blocks={m.blocks}
                        onAction={onAction}
                        animate={animate}
                        onAnimated={() => markAnimated?.(key)}
                      />

                      {m.dbId && onFeedback && (
                        <div
                          style={{
                            marginTop: '10px',
                            paddingTop: '8px',
                            borderTop: '1px solid var(--gray-4, rgba(255,255,255,0.06))',
                            display: 'flex',
                            gap: '8px',
                            fontSize: '11px',
                            color: 'var(--gray-9, #94a3b8)',
                          }}
                        >
                          <button
                            type="button"
                            onClick={() => onFeedback(m.id, 1)}
                            className="aeon-icon-btn"
                            style={{
                              padding: '2px 6px',
                              color: m.feedback === 1 ? 'var(--accent-11, #22e3ff)' : 'inherit',
                            }}
                            title="Helpful"
                          >
                            <ThumbsUp size={12} style={{ marginRight: '4px' }} /> Helpful
                          </button>
                          <button
                            type="button"
                            onClick={() => onFeedback(m.id, -1)}
                            className="aeon-icon-btn"
                            style={{
                              padding: '2px 6px',
                              color: m.feedback === -1 ? '#f87171' : 'inherit',
                            }}
                            title="Not helpful"
                          >
                            <ThumbsDown size={12} />
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            );
          })
        )}

        {sending && (
          <div className="aeon-message is-assistant">
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <div
                style={{
                  width: '26px',
                  height: '26px',
                  borderRadius: '50%',
                  background: 'var(--accent-3, rgba(34,227,255,0.15))',
                  color: 'var(--accent-11, #22e3ff)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '12px',
                  fontWeight: 'bold',
                  flexShrink: 0,
                  border: '1px solid var(--accent-6, rgba(34,227,255,0.3))',
                }}
              >
                ✦
              </div>
              <div className="aeon-stage-banner">
                <span className="aeon-status-dot is-thinking" />
                <span>{stage || 'Aeon is reasoning…'}</span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Input Box Area */}
      <form onSubmit={submit} className="aeon-input-container">
        <div className="aeon-input-box">
          <input
            ref={inputRef}
            type="text"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ask Aeon anything… (e.g. NCRs, daily attendance, objections)"
            disabled={sending}
            className="aeon-textarea"
          />
          <button
            type="submit"
            disabled={sending || !draft.trim()}
            className="aeon-send-btn"
            title="Send message"
            aria-label="Send"
          >
            <Send size={15} />
          </button>
        </div>

        <div className="aeon-input-footer">
          <span>Enterprise Guarded Copilot</span>
          {usage && usage.remaining !== undefined && (
            <span>{Math.max(0, usage.remaining)} tokens left today</span>
          )}
        </div>
      </form>
    </div>
  );
}
