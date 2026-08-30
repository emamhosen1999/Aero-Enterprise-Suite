import React, { useEffect, useState } from 'react';
import { Card, Flex, Box, Text, Heading, Badge, Button, Table, Avatar } from '@radix-ui/themes';
import { Search, ChevronRight, CheckCircle2, AlertTriangle, XCircle, Info } from 'lucide-react';
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
    <svg className="aeon-spark w-full h-16" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" aria-label="trend">
      <defs>
        <linearGradient id="aeonSparkGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="var(--accent-9, #22e3ff)" stopOpacity="0.35" />
          <stop offset="1" stopColor="var(--accent-9, #22e3ff)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={a} fill="url(#aeonSparkGrad)" />
      <path d={d} fill="none" stroke="var(--accent-9, #22e3ff)" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={xs(last)} cy={ys(points[last])} r="3.5" fill="var(--accent-9, #22e3ff)" />
    </svg>
  );
}

function StatusCell({ value }) {
  const text = String(value || '');
  const lower = text.toLowerCase();

  if (lower.includes('closed') || lower.includes('resolved') || lower.includes('approved') || lower.includes('online') || lower.includes('success')) {
    return (
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 8px', borderRadius: '9999px', fontSize: '11px', background: 'rgba(34,197,94,0.12)', color: '#22c55e', border: '1px solid rgba(34,197,94,0.25)', fontWeight: 500 }}>
        <CheckCircle2 size={11} /> {text}
      </span>
    );
  }

  if (lower.includes('open') || lower.includes('pending') || lower.includes('review') || lower.includes('active')) {
    return (
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 8px', borderRadius: '9999px', fontSize: '11px', background: 'rgba(245,158,11,0.12)', color: '#f59e0b', border: '1px solid rgba(245,158,11,0.25)', fontWeight: 500 }}>
        <AlertTriangle size={11} /> {text}
      </span>
    );
  }

  if (lower.includes('rejected') || lower.includes('fail') || lower.includes('error') || lower.includes('absent')) {
    return (
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 8px', borderRadius: '9999px', fontSize: '11px', background: 'rgba(239,68,68,0.12)', color: '#ef4444', border: '1px solid rgba(239,68,68,0.25)', fontWeight: 500 }}>
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
    <div className="my-2.5 overflow-hidden rounded-xl border border-[var(--gray-4,rgba(255,255,255,0.08))] bg-[var(--gray-2,rgba(255,255,255,0.02))]">
      {rows.length > 4 && (
        <div style={{ padding: '6px 10px', borderBottom: '1px solid var(--gray-4, rgba(255,255,255,0.06))', display: 'flex', alignItems: 'center', gap: '6px', background: 'rgba(0,0,0,0.2)' }}>
          <Search size={12} color="var(--gray-9)" />
          <input
            type="text"
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            placeholder="Filter table rows…"
            style={{ background: 'transparent', border: 'none', color: '#fff', fontSize: '11px', outline: 'none', width: '100%' }}
          />
        </div>
      )}
      <div style={{ overflowX: 'auto' }}>
        <Table.Root size="1" variant="surface">
          {columns.length ? (
            <Table.Header>
              <Table.Row>
                {columns.map((c, i) => (
                  <Table.ColumnHeaderCell key={i} className="text-xs font-bold text-[var(--accent-11,#22e3ff)] py-2">
                    {c}
                  </Table.ColumnHeaderCell>
                ))}
              </Table.Row>
            </Table.Header>
          ) : null}
          <Table.Body>
            {filteredRows.map((row, i) => (
              <Table.Row key={i} className="hover:bg-[var(--gray-3,rgba(255,255,255,0.04))] transition-colors">
                {(Array.isArray(row) ? row : [row]).map((cell, j) => (
                  <Table.Cell key={j} className="text-xs py-2 text-[var(--gray-12)]">
                    <StatusCell value={cell} />
                  </Table.Cell>
                ))}
              </Table.Row>
            ))}
          </Table.Body>
        </Table.Root>
      </div>
    </div>
  );
}

function Block({ block, onAction, animate, onAnimated }) {
  switch (block.type) {
    case 'stats':
      return (
        <div className="grid grid-cols-2 gap-2 my-2.5">
          {(block.items || []).map((s, i) => (
            <Card key={i} size="1" variant="surface" className="p-3 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl hover:border-[var(--accent-6,rgba(34,227,255,0.3))] transition-all">
              <Text size="1" color="gray" weight="medium" className="truncate">{s.k}</Text>
              <Heading size="4" className="mt-1 font-mono tracking-tight text-[var(--accent-11,#22e3ff)]">{s.v}</Heading>
              {s.d ? (
                <Badge size="1" color={s.dir === 'down' ? 'red' : 'green'} variant="soft" className="mt-1">
                  {s.d}
                </Badge>
              ) : null}
            </Card>
          ))}
        </div>
      );

    case 'chart':
      return (
        <Card size="1" variant="surface" className="p-3.5 my-2.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl">
          <Flex justify="between" align="center" className="mb-2">
            <Text size="2" weight="bold">{block.title || 'Trend'}</Text>
            {block.value && <Badge size="1" variant="outline" color="cyan">{block.value}</Badge>}
          </Flex>
          <Spark points={block.points || []} />
        </Card>
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
        <Card size="1" variant="surface" className="p-3.5 my-2.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl">
          {block.title && <Text size="2" weight="bold" className="mb-3 block">{block.title}</Text>}
          <Flex gap="4" align="center">
            <div
              className="relative w-20 h-20 rounded-full flex items-center justify-center shrink-0 shadow-inner"
              style={{ background: `conic-gradient(${stops})` }}
            >
              <div className="w-13 h-13 rounded-full bg-[var(--color-background,#0f172a)] flex items-center justify-center font-bold text-xs">
                {total}
              </div>
            </div>
            <div className="space-y-1.5 flex-1 min-w-0">
              {items.map((it, i) => (
                <Flex key={i} justify="between" align="center" className="text-xs">
                  <Flex align="center" gap="2" className="min-w-0">
                    <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ background: colors[i % colors.length] }} />
                    <span className="truncate text-[var(--gray-11)]">{it.label}</span>
                  </Flex>
                  <span className="font-mono font-medium ml-2">{it.value}</span>
                </Flex>
              ))}
            </div>
          </Flex>
        </Card>
      );
    }

    case 'bar': {
      const items = block.items || [];
      const max = Math.max(1, ...items.map((it) => Number(it.value) || 0));

      return (
        <Card size="1" variant="surface" className="p-3.5 my-2.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl">
          {block.title && <Text size="2" weight="bold" className="mb-3 block">{block.title}</Text>}
          <div className="space-y-2">
            {items.map((it, i) => (
              <div key={i} className="text-xs">
                <Flex justify="between" className="mb-1">
                  <span className="truncate text-[var(--gray-11)]">{it.label}</span>
                  <span className="font-mono font-semibold">{it.value}</span>
                </Flex>
                <div className="w-full bg-[var(--gray-4,rgba(255,255,255,0.1))] h-2 rounded-full overflow-hidden">
                  <div
                    className="bg-[var(--accent-9,#22e3ff)] h-full rounded-full transition-all duration-500"
                    style={{ width: `${Math.round(((Number(it.value) || 0) / max) * 100)}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </Card>
      );
    }

    case 'table':
      return <InteractiveTable columns={block.columns || []} rows={block.rows || []} />;

    case 'entityCard': {
      const title = block.title || '';
      return (
        <Card size="1" variant="surface" className="p-3.5 my-2.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl hover:border-[var(--accent-6,rgba(34,227,255,0.3))] transition-all">
          <Flex align="center" gap="3" className="mb-3">
            <Avatar fallback={title.slice(0, 2).toUpperCase()} size="2" radius="full" color="cyan" />
            <Box className="min-w-0">
              <Text size="2" weight="bold" className="truncate block text-[var(--accent-11,#22e3ff)]">{title}</Text>
              {block.subtitle && <Text size="1" color="gray" className="truncate block">{block.subtitle}</Text>}
            </Box>
          </Flex>
          {block.fields?.length ? (
            <div className="grid grid-cols-2 gap-2 text-xs border-t border-[var(--gray-4,rgba(255,255,255,0.08))] pt-2.5">
              {block.fields.map((f, i) => (
                <div key={i}>
                  <span className="text-[var(--gray-10)] block text-[11px]">{f.k}</span>
                  <span className="font-medium text-[var(--gray-12)] truncate block">{f.v}</span>
                </div>
              ))}
            </div>
          ) : null}
        </Card>
      );
    }

    case 'chips':
      return (
        <Flex gap="1.5" wrap="wrap" className="my-2">
          {(block.items || []).map((c, i) => (
            <Button
              key={i}
              size="1"
              variant="soft"
              color={block.variant === 'source' ? 'gray' : 'cyan'}
              className="rounded-full text-xs cursor-pointer"
              onClick={block.variant === 'source' ? undefined : () => onAction?.({ kind: 'chip', value: c })}
            >
              {typeof c === 'string' ? c : c.label}
            </Button>
          ))}
        </Flex>
      );

    case 'action': {
      const isNav = block.kind === 'navigate';
      return (
        <Card size="1" variant="surface" className="p-3.5 my-2.5 border border-[var(--accent-7,rgba(34,227,255,0.3))] bg-[var(--accent-2,rgba(34,227,255,0.04))] rounded-xl">
          <Badge size="1" color="cyan" variant="solid" className="mb-2">
            {isNav ? 'Navigation Action' : 'Action Required'}
          </Badge>
          <Text size="2" weight="bold" className="block text-[var(--gray-12)]">{block.title}</Text>
          {block.desc && <Text size="1" color="gray" className="mt-1 block">{block.desc}</Text>}
          <Flex gap="2" className="mt-3">
            <Button
              size="2"
              color="cyan"
              variant="solid"
              className="cursor-pointer font-medium"
              onClick={() => onAction?.({ kind: 'confirm', block })}
            >
              {block.confirm_label || (isNav ? 'Open Page →' : 'Confirm Action →')}
            </Button>
          </Flex>
        </Card>
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
    <div className="aeon-blocks space-y-1">
      {blocks.map((block, i) => (
        <Block key={i} block={block} onAction={onAction} animate={animate} onAnimated={onAnimated} />
      ))}
    </div>
  );
}
