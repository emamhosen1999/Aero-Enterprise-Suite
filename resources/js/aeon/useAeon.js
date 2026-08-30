import { useCallback, useRef, useState } from 'react';
import { sendAeonMessage, sendAeonMessageStream, sendAeonFeedback, fetchAeonConversation } from './aeonClient.js';

function readInitialUsage() {
  try {
    const el = document.querySelector('[data-page]');
    return el ? (JSON.parse(el.dataset.page)?.props?.aeon?.usage ?? null) : null;
  } catch {
    return null;
  }
}

/**
 * State management hook for Aeon Copilot.
 */
export function useAeon() {
  const [messages, setMessages] = useState([]);
  const [conversationId, setConversationId] = useState(null);
  const [isOpen, setIsOpen] = useState(false);
  const [sending, setSending] = useState(false);
  const [stage, setStage] = useState('');
  const [usage, setUsage] = useState(readInitialUsage);
  const idRef = useRef(1);
  const animatedRef = useRef(new Set());

  const open = useCallback(() => setIsOpen(true), []);
  const close = useCallback(() => setIsOpen(false), []);
  const toggle = useCallback(() => setIsOpen((v) => !v), []);
  const hasAnimated = useCallback((id) => animatedRef.current.has(id), []);
  const markAnimated = useCallback((id) => { animatedRef.current.add(id); }, []);

  const newChat = useCallback(() => {
    setConversationId(null);
    setMessages([]);
    setStage('');
  }, []);

  const selectConversation = useCallback(async (id) => {
    try {
      const data = await fetchAeonConversation(id);
      if (data) {
        setConversationId(data.id);
        const mapped = (data.messages || []).map((m) => ({
          id: m.id,
          dbId: m.id,
          role: m.role,
          blocks: m.blocks || [{ type: 'text', text: m.content || '' }],
          feedback: m.feedback ?? null,
        }));
        setMessages(mapped);
      }
    } catch {
      // Fallback
    }
  }, []);

  const send = useCallback(async (text) => {
    const trimmed = (text || '').trim();
    if (!trimmed || sending) return;

    setMessages((m) => [
      ...m,
      { id: idRef.current++, role: 'user', blocks: [{ type: 'text', text: trimmed }] },
    ]);
    setSending(true);
    setStage('');

    try {
      let data;
      try {
        data = await sendAeonMessageStream({
          message: trimmed,
          conversationId,
          onStage: setStage,
        });
      } catch {
        // Fallback to standard JSON endpoint if SSE stream fails
        data = await sendAeonMessage({ message: trimmed, conversationId });
      }

      setConversationId(data.conversation_id);
      if (data.usage !== undefined) {
        setUsage(data.usage);
      }

      setMessages((m) => [
        ...m,
        {
          id: idRef.current++,
          dbId: data.reply?.id ?? null,
          role: 'assistant',
          blocks: data.reply?.blocks ?? [{ type: 'text', text: data.reply?.content || 'Done.' }],
          feedback: null,
        },
      ]);
    } catch {
      setMessages((m) => [
        ...m,
        {
          id: idRef.current++,
          role: 'assistant',
          blocks: [{ type: 'text', text: 'Aeon encountered an issue processing your request. Please try again.' }],
        },
      ]);
    } finally {
      setSending(false);
      setStage('');
    }
  }, [conversationId, sending]);

  const feedback = useCallback(async (id, value) => {
    const msg = messages.find((m) => m.id === id);
    if (!msg || !msg.dbId) return;

    const next = msg.feedback === value ? 0 : value;
    setMessages((m) => m.map((x) => (x.id === id ? { ...x, feedback: next === 0 ? null : next } : x)));
    await sendAeonFeedback({ messageId: msg.dbId, value: next });
  }, [messages]);

  return {
    messages,
    conversationId,
    isOpen,
    open,
    close,
    toggle,
    newChat,
    selectConversation,
    send,
    sending,
    stage,
    usage,
    feedback,
    hasAnimated,
    markAnimated,
  };
}
