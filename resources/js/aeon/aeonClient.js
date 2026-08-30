// Aeon AI Copilot API Client for DBEDC Guardian
// Handles CSRF authentication, plain JSON turns, SSE streaming, and generative form submission.

function xsrf() {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  if (m) return decodeURIComponent(m[1]);
  const meta = document.head.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/**
 * Send a plain JSON chat message turn.
 */
export async function sendAeonMessage({ message, conversationId }) {
  const res = await fetch('/aeon/message', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: JSON.stringify({
      message,
      conversation_id: conversationId ?? null,
      context: { page: window.location.pathname },
    }),
  });

  if (!res.ok) {
    throw new Error(`Aeon request failed (HTTP ${res.status})`);
  }

  return res.json();
}

/**
 * Send a chat message with live Server-Sent Events (SSE) reasoning narration.
 */
export async function sendAeonMessageStream({ message, conversationId, onStage }) {
  const res = await fetch('/aeon/message/stream', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/event-stream',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: JSON.stringify({
      message,
      conversation_id: conversationId ?? null,
      context: { page: window.location.pathname },
    }),
  });

  if (!res.ok || !res.body) {
    throw new Error(`Aeon stream connection failed (HTTP ${res.status})`);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let done = null;

  const handleBlock = (raw) => {
    const lines = raw.split('\n');
    let event = 'message';
    let data = '';

    lines.forEach((line) => {
      if (line.startsWith('event:')) event = line.slice(6).trim();
      else if (line.startsWith('data:')) data += line.slice(5).trim();
    });

    if (!data) return;

    let payload;
    try {
      payload = JSON.parse(data);
    } catch {
      return;
    }

    if (event === 'stage' && onStage) {
      onStage(payload.label || '');
    }
    if (event === 'done') {
      done = payload;
    }
    if (event === 'error') {
      throw new Error(payload.message || 'Aeon stream error');
    }
  };

  while (true) {
    const { value, done: eof } = await reader.read();
    if (eof) break;

    buffer += decoder.decode(value, { stream: true });
    let idx;
    while ((idx = buffer.indexOf('\n\n')) !== -1) {
      const raw = buffer.slice(0, idx);
      buffer = buffer.slice(idx + 2);
      if (raw.trim()) {
        handleBlock(raw);
      }
    }
  }

  if (!done) {
    throw new Error('Aeon stream ended without reply payload');
  }

  return done;
}

/**
 * Record thumbs up/down feedback on an assistant turn.
 */
export async function sendAeonFeedback({ messageId, value }) {
  const res = await fetch(`/aeon/messages/${messageId}/feedback`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: JSON.stringify({ value }),
  });

  return res.ok;
}

/**
 * Submit an interactive generative form to the real Guardian endpoint.
 */
export async function submitAeonForm({ action, method = 'post', values }) {
  const verb = (method || 'post').toUpperCase();
  const body = {};

  Object.entries(values || {}).forEach(([k, v]) => {
    if (v === '' || v === null || v === undefined) return;
    body[k] = v;
  });

  const httpMethod = verb === 'GET' ? 'GET' : 'POST';
  if (['PUT', 'PATCH', 'DELETE'].includes(verb)) {
    body._method = verb;
  }

  let res;
  try {
    res = await fetch(action, {
      method: httpMethod,
      credentials: 'same-origin',
      redirect: 'manual',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify(body),
    });
  } catch {
    return { ok: false, status: 0, errors: { _: 'Network error — please check connection.' } };
  }

  // 3xx redirect is Laravel's post-redirect-get success indicator
  if (res.type === 'opaqueredirect' || (res.status >= 300 && res.status < 400)) {
    return { ok: true, status: res.status || 302, errors: {} };
  }

  if (res.ok) {
    return { ok: true, status: res.status, errors: {} };
  }

  if (res.status === 422) {
    let errors = {};
    try {
      const data = await res.json();
      errors = data.errors || {};
    } catch {
      // Keep empty
    }
    return { ok: false, status: 422, errors };
  }

  if (res.status === 403) {
    return { ok: false, status: 403, errors: { _: 'Permission denied for this operation.' } };
  }

  if (res.status === 419) {
    return { ok: false, status: 419, errors: { _: 'Session expired. Please refresh the page.' } };
  }

  return { ok: false, status: res.status, errors: { _: `Submission failed (HTTP ${res.status})` } };
}
