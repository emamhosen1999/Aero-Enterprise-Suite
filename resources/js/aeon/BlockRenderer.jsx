import React, { useEffect, useState } from 'react';
import { Search, CheckCircle2, AlertTriangle, XCircle } from 'lucide-react';
import Markdown from './Markdown.jsx';
import AeonForm from './AeonForm.jsx';

function TypewriterText({ text, onAnimated }) {
  const full = text ?? '';
  const reduce = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const [n, setN] = useState(reduce ? full.length : 0);
  const done = n >= full.length;

  useEffect(() => {
    onAnimated?.();
    if (reduce) {
      setN(full.length);
      return;
    }
    let i = 0;
    const id = setInterval(() => {
      i += 2;
      setN(i);
      if (i >= full.length) clearInterval(id);
    }, 12);

    return () => clearInterval(id);
  }, [full]);

  return (
    <div className={`aeon-typing ${done ? 'is-done' : ''}`}>
      <Markdown text={full.slice(0, n)} />
    </div>
  );
}

function Spark({ points = [] }) {
  if (!points.length) return null;
  const w = 320, h = 64, min = Math.min(...points), max = Math.max(...points) || 1;
  const span = max - min || 1;
  const xs = (i) => i * (w / (points.length - 1 || 1));
  const ys = (v) => h - 6 - ((v - min) / span) * (h - 16);

  let d = '', a = `M0 ${h}`;
  points.forEach((v, i) => {
    const x = xs(i), y = ys(v);
    d += (i ? ' L' : 'M') + x + ' ' + y;
    a += ` L${x} ${y}`;
  });
  a += ` L${w} ${h} Z`;
  const last = points.length - 1;

  return (
    <svg className="aeon-spark" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" aria-label="trend">
      <defs>
        <linearGradient id="aeonSparkGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="var(--aeon-cyan, #22e3ff)" stopOpacity="0.35" />
          <stop offset="1" stopColor="var(--aeon-cyan, #22e3ff)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={a} fill="url(#aeonSparkGrad)" />
      <path d={d} fill="none" stroke="var(--aeon-cyan, #22e3ff)" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={xs(last)} cy={ys(points[last])} r="3.5" fill="var(--aeon-cyan, #22e3ff)" />
    </svg>
  );
}

function StatusCell({ value }) {
  const text = String(value || '');
  const lower = text.toLowerCase();

  if (lower.includes('closed') || lower.includes('resolved') || lower.includes('approved') || lower.includes('online') || lower.includes('success')) {
    return (
      <span className="aeon-status-badge is-success">
        <CheckCircle2 size={11} /> {text}
      </span>
    );
  }

  if (lower.includes('open') || lower.includes('pending') || lower.includes('review') || lower.includes('active')) {
    return (
      <span className="aeon-status-badge is-warning">
        <AlertTriangle size={11} /> {text}
      </span>
    );
  }

  if (lower.includes('rejected') || lower.includes('fail') || lower.includes('error') || lower.includes('absent')) {
    return (
      <span className="aeon-status-badge is-error">
        <XCircle size={11} /> {text}
      </span>
    );
  }

  return <span>{text}</span>;
}

function InteractiveTable({ columns = [], rows = [] }) {
  const [filter, setFilter] = useState('');
  const filteredRows = rows.filter((row) => {
    if (!filter) return true;
    const s = Array.isArray(row) ? row.join(' ') : String(row);
    return s.toLowerCase().includes(filter.toLowerCase());
  });

  return (
    <div className="aeon-table-wrap">
      {rows.length > 4 && (
        <div className="aeon-table-filter">
          <Search size={12} color="var(--aeon-text-muted)" />
          <input
            type="text"
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            placeholder="Filter table rows…"
          />
        </div>
      )}
      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
          {columns.length > 0 && (
            <thead>
              <tr>
                {columns.map((c, i) => (
                  <th
                    key={i}
                    style={{
                      padding: '8px 12px',
                      textAlign: 'left',
                      fontSize: '11px',
                      fontWeight: 700,
                      color: 'var(--aeon-cyan)',
                      borderBottom: '1px solid var(--aeon-border-glass-strong)',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
          )}
          <tbody>
            {filteredRows.map((row, i) => (
              <tr
                key={i}
                style={{
                  borderBottom: '1px solid rgba(255,255,255,0.03)',
                  transition: 'background 0.15s',
                }}
                onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.03)')}
                onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
              >
                {(Array.isArray(row) ? row : [row]).map((cell, j) => (
                  <td
                    key={j}
                    style={{
                      padding: '8px 12px',
                      color: 'var(--aeon-text-primary)',
                      fontSize: '12px',
                    }}
                  >
                    <StatusCell value={cell} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function Block({ block, onAction, animate, onAnimated }) {
  switch (block.type) {
    case 'stats':
      return (
        <div className="aeon-stats-grid">
          {(block.items || []).map((s, i) => (
            <div key={i} className="aeon-stat-card">
              <div className="aeon-stat-label">{s.k}</div>
              <div className="aeon-stat-value">{s.v}</div>
              {s.d && (
                <span className={`aeon-stat-delta ${s.dir === 'down' ? 'is-down' : 'is-up'}`}>
                  {s.d}
                </span>
              )}
            </div>
          ))}
        </div>
      );

    case 'chart':
      return (
        <div className="aeon-chart-card">
          <div className="aeon-chart-header">
            <span className="aeon-chart-title">{block.title || 'Trend'}</span>
            {block.value && <span className="aeon-chart-badge">{block.value}</span>}
          </div>
          <Spark points={block.points || []} />
        </div>
      );

    case 'donut': {
      const items = block.items || [];
      const total = items.reduce((s, it) => s + (Number(it.value) || 0), 0) || 1;
      const colors = ['#22e3ff', '#8c6bff', '#ff66c4', '#37e2a0', '#ffc24b', '#4dd0e1', '#94a3b8'];
      let acc = 0;
      const stops = items.map((it, i) => {
        const start = (acc / total) * 100;
        acc += Number(it.value) || 0;
        const end = (acc / total) * 100;
        return `${colors[i % colors.length]} ${start}% ${end}%`;
      }).join(', ');

      return (
        <div className="aeon-donut-card">
          {block.title && <div className="aeon-donut-title">{block.title}</div>}
          <div className="aeon-donut-layout">
            <div className="aeon-donut-ring" style={{ background: `conic-gradient(${stops})` }}>
              <div className="aeon-donut-center">{total}</div>
            </div>
            <div className="aeon-donut-legend">
              {items.map((it, i) => (
                <div key={i} className="aeon-donut-legend-item">
                  <div className="aeon-donut-legend-label">
                    <span className="aeon-donut-legend-dot" style={{ background: colors[i % colors.length] }} />
                    <span className="aeon-donut-legend-text">{it.label}</span>
                  </div>
                  <span className="aeon-donut-legend-value">{it.value}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      );
    }

    case 'bar': {
      const items = block.items || [];
      const max = Math.max(1, ...items.map((it) => Number(it.value) || 0));

      return (
        <div className="aeon-bar-card">
          {block.title && <div className="aeon-bar-title">{block.title}</div>}
          <div className="aeon-bar-items">
            {items.map((it, i) => (
              <div key={i}>
                <div className="aeon-bar-item-header">
                  <span className="aeon-bar-item-label">{it.label}</span>
                  <span className="aeon-bar-item-value">{it.value}</span>
                </div>
                <div className="aeon-bar-track">
                  <div
                    className="aeon-bar-fill"
                    style={{ width: `${Math.round(((Number(it.value) || 0) / max) * 100)}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      );
    }

    case 'table':
      return <InteractiveTable columns={block.columns || []} rows={block.rows || []} />;

    case 'entityCard': {
      const title = block.title || '';
      return (
        <div className="aeon-entity-card">
          <div className="aeon-entity-header">
            <div
              style={{
                width: 36,
                height: 36,
                borderRadius: '50%',
                background: 'linear-gradient(135deg, rgba(34,227,255,0.15), rgba(140,107,255,0.1))',
                color: 'var(--aeon-cyan)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: 13,
                fontWeight: 700,
                flexShrink: 0,
                border: '1px solid rgba(34,227,255,0.25)',
              }}
            >
              {title.slice(0, 2).toUpperCase()}
            </div>
            <div style={{ minWidth: 0 }}>
              <div className="aeon-entity-title">{title}</div>
              {block.subtitle && <div className="aeon-entity-subtitle">{block.subtitle}</div>}
            </div>
          </div>
          {block.fields?.length > 0 && (
            <div className="aeon-entity-fields">
              {block.fields.map((f, i) => (
                <div key={i}>
                  <span className="aeon-entity-field-label">{f.k}</span>
                  <span className="aeon-entity-field-value">{f.v}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      );
    }

    case 'chips':
      return (
        <div className="aeon-chips">
          {(block.items || []).map((c, i) => (
            <button
              key={i}
              type="button"
              onClick={block.variant === 'source' ? undefined : () => onAction?.({ kind: 'chip', value: c })}
              style={{
                padding: '4px 12px',
                borderRadius: '9999px',
                fontSize: '11px',
                fontWeight: 500,
                border: '1px solid rgba(34,227,255,0.25)',
                background: 'rgba(34,227,255,0.08)',
                color: block.variant === 'source' ? 'var(--aeon-text-secondary)' : 'var(--aeon-cyan)',
                cursor: block.variant === 'source' ? 'default' : 'pointer',
                transition: 'all 0.15s ease',
                fontFamily: 'inherit',
              }}
            >
              {typeof c === 'string' ? c : c.label}
            </button>
          ))}
        </div>
      );

    case 'action': {
      const isNav = block.kind === 'navigate';
      return (
        <div className="aeon-action-card">
          <div className="aeon-action-badge">
            {isNav ? 'Navigation Action' : 'Action Required'}
          </div>
          <div className="aeon-action-title">{block.title}</div>
          {block.desc && <div className="aeon-action-desc">{block.desc}</div>}
          <div className="aeon-action-buttons">
            <button
              type="button"
              onClick={() => onAction?.({ kind: 'confirm', block })}
              style={{
                padding: '8px 18px',
                borderRadius: '8px',
                fontSize: '12px',
                fontWeight: 600,
                border: 'none',
                background: 'linear-gradient(135deg, var(--aeon-cyan), var(--aeon-violet))',
                color: '#fff',
                cursor: 'pointer',
                transition: 'all 0.2s ease',
                boxShadow: '0 2px 12px rgba(34,227,255,0.2)',
                fontFamily: 'inherit',
              }}
            >
              {block.confirm_label || (isNav ? 'Open Page →' : 'Confirm Action →')}
            </button>
          </div>
        </div>
      );
    }

    case 'form':
      return <AeonForm block={block} onAction={onAction} />;

    case 'text':
    default:
      return animate
        ? <TypewriterText text={block.text ?? ''} onAnimated={onAnimated} />
        : <Markdown text={block.text ?? ''} />;
  }
}

export default function BlockRenderer({ blocks = [], onAction, animate = false, onAnimated }) {
  return (
    <div className="aeon-blocks">
      {blocks.map((block, i) => (
        <Block key={i} block={block} onAction={onAction} animate={animate} onAnimated={onAnimated} />
      ))}
    </div>
  );
}
