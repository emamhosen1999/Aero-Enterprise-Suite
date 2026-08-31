import React from 'react';

/**
 * Dependency-free, XSS-safe Markdown renderer for Aeon responses.
 * Uses vanilla CSS classes from aeon.css — zero Tailwind.
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
      out.push(<strong key={`${kp}-${k}`} style={{ fontWeight: 600, color: 'var(--aeon-cyan)' }}>{m[2]}</strong>);
    } else if (m[3] != null) {
      out.push(<em key={`${kp}-${k}`} style={{ fontStyle: 'italic' }}>{m[3]}</em>);
    } else if (m[4] != null) {
      out.push(
        <code
          key={`${kp}-${k}`}
          style={{
            padding: '2px 6px',
            borderRadius: 4,
            background: 'rgba(255,255,255,0.07)',
            fontSize: '12px',
            fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
            color: 'var(--aeon-cyan)',
          }}
        >
          {m[4]}
        </code>
      );
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

  const headingStyle = (level) => {
    if (level === 1) return { fontSize: 16, fontWeight: 700, color: 'var(--aeon-cyan)', marginTop: 8 };
    if (level === 2) return { fontSize: 14, fontWeight: 600, color: 'var(--aeon-cyan)', marginTop: 6 };
    return { fontSize: 12, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--aeon-text-secondary)' };
  };

  return (
    <div className="aeon-md">
      {blocks.map((b, i) => {
        if (b.type === 'h') {
          const Tag = `h${b.level}`;
          return <Tag key={i} style={headingStyle(b.level)}>{inline(b.text, i)}</Tag>;
        }
        if (b.type === 'ul') {
          return (
            <ul key={i} style={{ listStyle: 'disc', paddingLeft: 20, margin: '4px 0' }}>
              {b.items.map((it, j) => <li key={j} style={{ marginTop: j > 0 ? 4 : 0 }}>{inline(it, `${i}-${j}`)}</li>)}
            </ul>
          );
        }
        if (b.type === 'ol') {
          return (
            <ol key={i} style={{ listStyle: 'decimal', paddingLeft: 20, margin: '4px 0' }}>
              {b.items.map((it, j) => <li key={j} style={{ marginTop: j > 0 ? 4 : 0 }}>{inline(it, `${i}-${j}`)}</li>)}
            </ol>
          );
        }
        return <p key={i} style={{ margin: '4px 0' }}>{inline(b.text, i)}</p>;
      })}
    </div>
  );
}
