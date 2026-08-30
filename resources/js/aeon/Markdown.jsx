import React from 'react';

/**
 * Dependency-free, XSS-safe Markdown renderer for Aeon responses.
 */
function inline(str, kp) {
  const out = [];
  let rest = str;
  let k = 0;
  const re = /(\*\*([^*]+)\*\*|\*([^*\n]+)\*|`([^`]+)`)/;

  while (rest.length) {
    const m = re.exec(rest);
    if (!m) {
      out.push(rest);
      break;
    }
    if (m.index > 0) {
      out.push(rest.slice(0, m.index));
    }
    if (m[2] != null) {
      out.push(<strong key={`${kp}-${k}`} className="font-semibold text-[var(--accent-11,#22e3ff)]">{m[2]}</strong>);
    } else if (m[3] != null) {
      out.push(<em key={`${kp}-${k}`} className="italic">{m[3]}</em>);
    } else if (m[4] != null) {
      out.push(<code key={`${kp}-${k}`} className="px-1.5 py-0.5 rounded bg-[var(--gray-3,rgba(255,255,255,0.08))] text-xs font-mono text-[var(--accent-11,#22e3ff)]">{m[4]}</code>);
    }
    rest = rest.slice(m.index + m[0].length);
    k += 1;
  }

  return out;
}

export default function Markdown({ text = '' }) {
  const lines = String(text).split('\n');
  const blocks = [];
  let list = null;

  const flush = () => {
    if (list) {
      blocks.push(list);
      list = null;
    }
  };

  lines.forEach((raw) => {
    const t = raw.trim();
    let m;
    if (!t) {
      flush();
      return;
    }
    if ((m = /^(#{1,3})\s+(.*)$/.exec(t))) {
      flush();
      blocks.push({ type: 'h', level: m[1].length, text: m[2] });
      return;
    }
    if ((m = /^[-*]\s+(.*)$/.exec(t))) {
      if (!list || list.type !== 'ul') {
        flush();
        list = { type: 'ul', items: [] };
      }
      list.items.push(m[1]);
      return;
    }
    if ((m = /^\d+\.\s+(.*)$/.exec(t))) {
      if (!list || list.type !== 'ol') {
        flush();
        list = { type: 'ol', items: [] };
      }
      list.items.push(m[1]);
      return;
    }
    flush();
    blocks.push({ type: 'p', text: t });
  });
  flush();

  return (
    <div className="aeon-md space-y-2 text-[13.5px] leading-relaxed text-[var(--gray-12,#f1f5f9)]">
      {blocks.map((b, i) => {
        if (b.type === 'h') {
          const Tag = `h${b.level}`;
          const cls = b.level === 1 ? 'text-base font-bold text-[var(--accent-11,#22e3ff)] mt-2' : b.level === 2 ? 'text-sm font-semibold text-[var(--accent-11,#22e3ff)] mt-1.5' : 'text-xs font-semibold uppercase tracking-wider text-[var(--gray-11)]';
          return <Tag key={i} className={cls}>{inline(b.text, i)}</Tag>;
        }
        if (b.type === 'ul') {
          return (
            <ul key={i} className="list-disc pl-5 space-y-1 my-1">
              {b.items.map((it, j) => <li key={j}>{inline(it, `${i}-${j}`)}</li>)}
            </ul>
          );
        }
        if (b.type === 'ol') {
          return (
            <ol key={i} className="list-decimal pl-5 space-y-1 my-1">
              {b.items.map((it, j) => <li key={j}>{inline(it, `${i}-${j}`)}</li>)}
            </ol>
          );
        }
        return <p key={i} className="my-1">{inline(b.text, i)}</p>;
      })}
    </div>
  );
}
