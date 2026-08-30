import React, { useEffect, useRef, useState } from 'react';
import { Flex, Box, Text, Avatar, IconButton } from '@radix-ui/themes';
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
      color="indigo"
    />
  );
}

export default function AeonConversation({
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
  inputRef,
}) {
  const [draft, setDraft] = useState('');
  const streamRef = useRef(null);

  useEffect(() => {
    const el = streamRef.current;
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

  return (
    <div className="flex flex-col h-full min-h-0">
      {/* Stream Area */}
      <div ref={streamRef} className="flex-1 overflow-y-auto p-4 space-y-4 aeon-scrollbar">
        {messages.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center py-8 space-y-4">
            <AeonCore state="idle" size={64} />
            <div>
              <Text size="3" weight="bold" className="block text-[var(--gray-12)]">
                Welcome to Aeon Copilot
              </Text>
              <Text size="2" color="gray" className="max-w-xs mt-1 block leading-relaxed">
                Your intelligent operations & QC assistant across DBEDC Guardian.
              </Text>
            </div>

            <div className="w-full max-w-sm space-y-2 pt-2 text-left">
              {SUGGESTIONS.map((s) => (
                <button
                  type="button"
                  key={s.text}
                  onClick={() => onSend(s.text)}
                  className="w-full p-2.5 rounded-xl text-xs flex items-center gap-2.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] hover:bg-[var(--accent-3,rgba(34,227,255,0.08))] border border-[var(--gray-4,rgba(255,255,255,0.08))] hover:border-[var(--accent-6)] transition-all cursor-pointer text-[var(--gray-12)] text-left"
                >
                  <span className="text-base shrink-0">{s.icon}</span>
                  <span className="truncate">{s.text}</span>
                </button>
              ))}
            </div>
          </div>
        ) : (
          messages.map((m, i) => {
            const key = m.id ?? i;
            const animate = m.role !== 'user' && i === messages.length - 1 && !(hasAnimated ? hasAnimated(key) : false);

            return (
              <div
                key={key}
                className={`flex gap-3 text-sm ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}
              >
                {m.role !== 'user' && (
                  <div className="w-7 h-7 rounded-full bg-[var(--accent-3,rgba(34,227,255,0.15))] text-[var(--accent-11,#22e3ff)] flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 border border-[var(--accent-6)]">
                    ✦
                  </div>
                )}

                <div
                  className={`max-w-[85%] rounded-2xl p-3.5 ${
                    m.role === 'user'
                      ? 'bg-[var(--accent-9,#22e3ff)] text-[var(--accent-contrast,#000)] rounded-br-xs font-medium'
                      : 'bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] text-[var(--gray-12)] rounded-bl-xs shadow-sm'
                  }`}
                >
                  <BlockRenderer
                    blocks={m.blocks}
                    onAction={onAction}
                    animate={animate}
                    onAnimated={() => markAnimated?.(key)}
                  />

                  {m.role !== 'user' && m.dbId && onFeedback && (
                    <Flex gap="2" className="mt-2.5 pt-2 border-t border-[var(--gray-4,rgba(255,255,255,0.06))] text-xs text-[var(--gray-10)]">
                      <button
                        type="button"
                        onClick={() => onFeedback(m.id, 1)}
                        className={`p-1 rounded hover:bg-[var(--gray-4)] transition-colors cursor-pointer flex items-center gap-1 ${m.feedback === 1 ? 'text-[var(--accent-11)] font-semibold' : ''}`}
                        title="Helpful"
                      >
                        <ThumbsUp size={13} />
                      </button>
                      <button
                        type="button"
                        onClick={() => onFeedback(m.id, -1)}
                        className={`p-1 rounded hover:bg-[var(--gray-4)] transition-colors cursor-pointer flex items-center gap-1 ${m.feedback === -1 ? 'text-red-400 font-semibold' : ''}`}
                        title="Not helpful"
                      >
                        <ThumbsDown size={13} />
                      </button>
                    </Flex>
                  )}
                </div>

                {m.role === 'user' && (
                  <div className="shrink-0 mt-0.5">
                    <UserAvatar user={user} />
                  </div>
                )}
              </div>
            );
          })
        )}

        {sending && (
          <div className="flex gap-3 text-sm justify-start">
            <div className="w-7 h-7 rounded-full bg-[var(--accent-3,rgba(34,227,255,0.15))] text-[var(--accent-11,#22e3ff)] flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 border border-[var(--accent-6)] animate-pulse">
              ✦
            </div>
            <div className="bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] text-[var(--gray-11)] rounded-2xl rounded-bl-xs p-3 flex items-center gap-2 text-xs">
              <span className="flex space-x-1">
                <span className="w-1.5 h-1.5 rounded-full bg-[var(--accent-9)] animate-bounce" style={{ animationDelay: '0ms' }} />
                <span className="w-1.5 h-1.5 rounded-full bg-[var(--accent-9)] animate-bounce" style={{ animationDelay: '150ms' }} />
                <span className="w-1.5 h-1.5 rounded-full bg-[var(--accent-9)] animate-bounce" style={{ animationDelay: '300ms' }} />
              </span>
              <span>{stage || 'Aeon is reasoning…'}</span>
            </div>
          </div>
        )}
      </div>

      {/* Composer Area */}
      <form onSubmit={submit} className="p-3 border-t border-[var(--gray-4,rgba(255,255,255,0.08))] bg-[var(--gray-1,rgba(0,0,0,0.15))]">
        <div className="flex items-center gap-2 bg-[var(--gray-3,rgba(255,255,255,0.04))] border border-[var(--gray-5,rgba(255,255,255,0.12))] rounded-xl px-3 py-1.5 focus-within:border-[var(--accent-9)] transition-colors">
          <input
            ref={inputRef}
            type="text"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            placeholder="Ask Aeon anything… (e.g. NCR count, leave request)"
            disabled={sending}
            className="flex-1 bg-transparent border-none text-xs text-[var(--gray-12)] focus:outline-none placeholder:text-[var(--gray-9)]"
          />
          <button
            type="submit"
            disabled={sending || !draft.trim()}
            className="w-7 h-7 rounded-lg bg-[var(--accent-9,#22e3ff)] text-[var(--accent-contrast,#000)] flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed hover:opacity-90 transition-opacity cursor-pointer shrink-0"
          >
            <Send size={14} />
          </button>
        </div>

        <Flex justify="between" align="center" className="mt-2 px-1 text-[11px] text-[var(--gray-9)]">
          <span>Enterprise Guarded Copilot</span>
          {usage && (
            <span>
              {usage.remaining !== undefined && `${Math.max(0, usage.remaining)} tokens left today`}
            </span>
          )}
        </Flex>
      </form>
    </div>
  );
}
