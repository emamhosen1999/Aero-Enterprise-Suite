import React from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Embedded AI Usage Card reflecting daily token allowance.
 * Pure vanilla CSS — no Tailwind or Radix UI Themes.
 */
export default function AiUsageCard({ variant = 'card' }) {
  let aeon;
  try {
    aeon = usePage().props?.aeon;
  } catch {
    return null;
  }
  if (!aeon?.available || !aeon?.usage) return null;

  const u = aeon.usage;
  const limit = u.limit || 500000;
  const used = u.used || 0;
  const pct = Math.min(100, Math.round((used / Math.max(1, limit)) * 100));
  const remaining = Math.max(0, limit - used);

  const body = (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <span
          style={{
            fontSize: 10,
            fontWeight: 700,
            padding: '3px 10px',
            borderRadius: 9999,
            background: 'rgba(34,227,255,0.1)',
            color: 'var(--aeon-cyan, #22e3ff)',
            border: '1px solid rgba(34,227,255,0.25)',
          }}
        >
          ✦ Aeon AI Copilot
        </span>
        <span style={{ fontSize: 11, color: 'var(--aeon-text-secondary, #94a3b8)' }}>
          {u.model || 'Gemini'}
        </span>
      </div>
      <div>
        <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--aeon-cyan, #22e3ff)', fontVariantNumeric: 'tabular-nums' }}>
          {remaining.toLocaleString()} tokens remaining today
        </div>
        <div
          style={{
            width: '100%',
            height: 4,
            borderRadius: 9999,
            background: 'rgba(255,255,255,0.08)',
            marginTop: 8,
            overflow: 'hidden',
          }}
        >
          <div
            style={{
              height: '100%',
              borderRadius: 9999,
              background: 'linear-gradient(90deg, var(--aeon-cyan, #22e3ff), var(--aeon-violet, #8c6bff))',
              width: `${pct}%`,
              transition: 'width 0.5s ease',
            }}
          />
        </div>
      </div>
    </div>
  );

  if (variant === 'row') {
    return <div style={{ padding: '8px 0' }}>{body}</div>;
  }

  return (
    <div
      style={{
        padding: 14,
        background: 'var(--aeon-bg-surface, rgba(255,255,255,0.03))',
        border: '1px solid var(--aeon-border-glass, rgba(255,255,255,0.08))',
        borderRadius: 12,
      }}
    >
      {body}
    </div>
  );
}
