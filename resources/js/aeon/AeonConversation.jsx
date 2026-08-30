import React, { useEffect, useRef, useState, useCallback } from 'react';
import { Flex, Box, Text, Avatar } from '@radix-ui/themes';
import { Send, ThumbsUp, ThumbsDown, Mic, MicOff, Copy, Check } from 'lucide-react';
import AeonCore from './AeonCore.jsx';
import BlockRenderer from './BlockRenderer.jsx';

const DOMAIN_CATEGORIES = [
  { id: 'all', label: 'All Topics' },
  { id: 'qc', label: 'Expressway & QC' },
  { id: 'ops', label: 'Operations & TMC' },
  { id: 'hrm', label: 'HRM & Attendance' },
  { id: 'finance', label: 'Petty Cash & Vouchers' },
];

const SUGGESTIONS = {
  all: [
    { icon: '⚠️', text: 'How many open NCRs require inspection?' },
    { icon: '📊', text: 'Break down daily attendance by status today' },
    { icon: '🛣️', text: 'Show Dhaka Bypass Expressway alignment summary' },
    { icon: '💰', text: 'What is our current petty cash balance?' },
  ],
  qc: [
    { icon: '🔍', text: 'Show open NCRs awaiting contractor action' },
    { icon: '📋', text: 'List recent Site Objections by discipline' },
    { icon: '🏗️', text: 'What is the RFI first-pass approval rate?' },
    { icon: '📜', text: 'Show Site Instructions issued this month' },
  ],
  ops: [
    { icon: '🚨', text: 'Are there any active traffic incidents right now?' },
    { icon: '📍', text: 'Where is Chainage Ch 14+200 located?' },
    { icon: '🚗', text: 'Show toll plaza ETC lane efficiency' },
    { icon: '🚓', text: 'What is the TMC emergency patrol status?' },
  ],
  hrm: [
    { icon: '👥', text: 'How many employees are present on site today?' },
    { icon: '⏰', text: 'Check my biometric check-in punch for today' },
    { icon: '📅', text: 'What is my remaining annual leave balance?' },
    { icon: '📟', text: 'Show status of biometric ADMS sync devices' },
  ],
  finance: [
    { icon: '💵', text: 'What is the monthly petty cash budget status?' },
    { icon: '📑', text: 'Show pending petty cash voucher approvals' },
    { icon: '📈', text: 'Break down expenses by department' },
    { icon: '🧾', text: 'List recent cash disbursements' },
  ],
};

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
  const [activeCategory, setActiveCategory] = useState('all');
  const [copiedId, setCopiedId] = useState(null);
  const [isRecording, setIsRecording] = useState(false);
  const scrollRef = useRef(null);
  const recognitionRef = useRef(null);

  useEffect(() => {
    const el = scrollRef.current;
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }, [messages, sending, stage]);

  // Voice speech-to-text integration
  useEffect(() => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      const recog = new SpeechRecognition();
      recog.continuous = false;
      recog.interimResults = false;
      recog.lang = 'en-US';

      recog.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        if (transcript) {
          setDraft((prev) => (prev ? `${prev} ${transcript}` : transcript));
        }
        setIsRecording(false);
      };

      recog.onerror = () => setIsRecording(false);
      recog.onend = () => setIsRecording(false);

      recognitionRef.current = recog;
    }
  }, []);

  const toggleRecording = () => {
    if (!recognitionRef.current) return;
    if (isRecording) {
      recognitionRef.current.stop();
      setIsRecording(false);
    } else {
      try {
        recognitionRef.current.start();
        setIsRecording(true);
      } catch {
        setIsRecording(false);
      }
    }
  };

  const copyMessage = useCallback((id, text) => {
    navigator.clipboard.writeText(text || '');
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  }, []);

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
              Your intelligent operations, quality control, TMC, and HRM copilot across DBEDC Guardian.
            </div>

            {/* Domain category filter tabs */}
            <div className="aeon-domain-tabs">
              {DOMAIN_CATEGORIES.map((cat) => (
                <button
                  type="button"
                  key={cat.id}
                  onClick={() => setActiveCategory(cat.id)}
                  className={`aeon-tab-pill ${activeCategory === cat.id ? 'is-active' : ''}`}
                >
                  {cat.label}
                </button>
              ))}
            </div>

            {/* Suggestions list */}
            <div className="aeon-quick-prompts">
              {(SUGGESTIONS[activeCategory] || SUGGESTIONS.all).map((s) => (
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

            const msgText = m.blocks?.map((b) => b.text || b.title || '').join('\n') || m.content || '';

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
                        width: '28px',
                        height: '28px',
                        borderRadius: '50%',
                        background: 'var(--accent-3, rgba(34,227,255,0.15))',
                        color: 'var(--accent-11, #22e3ff)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '13px',
                        fontWeight: 'bold',
                        flexShrink: 0,
                        marginTop: '2px',
                        border: '1px solid var(--accent-6, rgba(34,227,255,0.35))',
                        boxShadow: '0 0 10px rgba(34,227,255,0.2)',
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

                      <div
                        style={{
                          marginTop: '10px',
                          paddingTop: '8px',
                          borderTop: '1px solid var(--gray-4, rgba(255,255,255,0.06))',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'space-between',
                          fontSize: '11px',
                          color: 'var(--gray-9, #94a3b8)',
                        }}
                      >
                        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                          <button
                            type="button"
                            onClick={() => copyMessage(key, msgText)}
                            className="aeon-icon-btn"
                            title="Copy response"
                          >
                            {copiedId === key ? (
                              <span style={{ color: '#22c55e', display: 'flex', alignItems: 'center', gap: '3px' }}>
                                <Check size={12} /> Copied
                              </span>
                            ) : (
                              <span style={{ display: 'flex', alignItems: 'center', gap: '3px' }}>
                                <Copy size={12} /> Copy
                              </span>
                            )}
                          </button>

                          {m.dbId && onFeedback && (
                            <>
                              <button
                                type="button"
                                onClick={() => onFeedback(m.id, 1)}
                                className="aeon-icon-btn"
                                style={{
                                  color: m.feedback === 1 ? 'var(--accent-11, #22e3ff)' : 'inherit',
                                }}
                                title="Helpful"
                              >
                                <ThumbsUp size={12} style={{ marginRight: '3px' }} /> Helpful
                              </button>
                              <button
                                type="button"
                                onClick={() => onFeedback(m.id, -1)}
                                className="aeon-icon-btn"
                                style={{
                                  color: m.feedback === -1 ? '#f87171' : 'inherit',
                                }}
                                title="Not helpful"
                              >
                                <ThumbsDown size={12} />
                              </button>
                            </>
                          )}
                        </div>

                        <span style={{ fontSize: '9.5px', color: 'var(--gray-8, #64748b)' }}>
                          Verified Guarded AI
                        </span>
                      </div>
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
                  width: '28px',
                  height: '28px',
                  borderRadius: '50%',
                  background: 'var(--accent-3, rgba(34,227,255,0.15))',
                  color: 'var(--accent-11, #22e3ff)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '13px',
                  fontWeight: 'bold',
                  flexShrink: 0,
                  border: '1px solid var(--accent-6, rgba(34,227,255,0.35))',
                  animation: 'aeonPulse 1.5s infinite',
                }}
              >
                ✦
              </div>
              <div className="aeon-stage-banner">
                <span className="aeon-status-dot is-thinking" />
                <span>{stage || 'Aeon is reasoning across expressway data…'}</span>
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
            placeholder={isRecording ? 'Listening to your voice…' : 'Ask Aeon anything… (e.g. NCRs, chainage, attendance)'}
            disabled={sending}
            className="aeon-textarea"
          />

          {/* Voice Speech-to-Text button */}
          {typeof window !== 'undefined' && (window.SpeechRecognition || window.webkitSpeechRecognition) && (
            <button
              type="button"
              onClick={toggleRecording}
              className={`aeon-mic-btn ${isRecording ? 'is-recording' : ''}`}
              title={isRecording ? 'Stop recording' : 'Dictate with Voice'}
              aria-label="Voice Dictation"
            >
              {isRecording ? <MicOff size={15} /> : <Mic size={15} />}
            </button>
          )}

          <button
            type="submit"
            disabled={sending || !draft.trim()}
            className="aeon-send-btn"
            title="Send prompt (Enter)"
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
