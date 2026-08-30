import React, { useEffect, useRef } from 'react';

/**
 * Living Aeon Canvas Orb that dynamically reacts to agent states:
 * idle (breathing) · listening (stable ring) · thinking (particle swirl) · speaking (pulse).
 * Dynamically reacts to Radix Theme accent palette.
 */
export default function AeonCore({ state = 'listening', size = 44 }) {
  const ref = useRef(null);
  const stateRef = useRef(state);
  stateRef.current = state;

  useEffect(() => {
    const canvas = ref.current;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    ctx.scale(dpr, dpr);

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const readAccent = () => {
      const v = getComputedStyle(document.documentElement).getPropertyValue('--accent-9').trim();
      return v || '#22e3ff';
    };

    const hexToRgb = (h) => {
      h = (h || '#22e3ff').replace('#', '');
      if (h.length === 3) h = h.split('').map((c) => c + c).join('');
      const n = parseInt(h, 16);
      if (isNaN(n)) return [34, 227, 255];
      return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    };

    const C0 = hexToRgb(readAccent());
    const V = [140, 107, 255]; // Violet aura companion

    const particles = Array.from({ length: 20 }, () => ({
      a: Math.random() * 6.28,
      r: 0.35 + Math.random() * 0.3,
      s: 0.005 + Math.random() * 0.02,
      o: Math.random(),
    }));

    let raf;
    const half = size / 2;

    const draw = (t) => {
      const st = stateRef.current;
      const C = C0;
      ctx.clearRect(0, 0, size, size);
      ctx.save();
      ctx.translate(half, half);
      const unit = size / 44;
      const speed = st === 'thinking' ? 2.5 : st === 'speaking' ? 1.6 : st === 'listening' ? 1 : 0.5;
      const breathe = 1 + Math.sin(t / (reduce ? 1e9 : 700)) * (st === 'idle' ? 0.06 : 0.03);

      // Glow layers
      for (let k = 3; k >= 1; k--) {
        const rr = (14 + k * 7) * unit * breathe;
        const g = ctx.createRadialGradient(0, 0, rr * 0.2, 0, 0, rr);
        g.addColorStop(0, `rgba(${C[0]},${C[1]},${C[2]},${0.2 / k})`);
        g.addColorStop(1, `rgba(${V[0]},${V[1]},${V[2]},0)`);
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(0, 0, rr, 0, 6.2832);
        ctx.fill();
      }

      // Orbital Rings
      const rot = reduce ? 0 : (t / 1000) * speed;
      for (let s = 0; s < 3; s++) {
        ctx.beginPath();
        const seg = st === 'listening' ? 5.4 : 4.2;
        ctx.arc(0, 0, (12 + s * 3) * unit, rot + s * 2.1, rot + s * 2.1 + seg);
        ctx.strokeStyle = s % 2 ? `rgba(${V[0]},${V[1]},${V[2]},0.7)` : `rgba(${C[0]},${C[1]},${C[2]},0.9)`;
        ctx.lineWidth = (2.2 - s * 0.5) * unit;
        ctx.lineCap = 'round';
        ctx.stroke();
      }

      // Core nucleus
      const ng = ctx.createRadialGradient(-3 * unit, -3 * unit, 1, 0, 0, 9 * unit);
      ng.addColorStop(0, '#ffffff');
      ng.addColorStop(0.5, `rgba(${C[0]},${C[1]},${C[2]},0.95)`);
      ng.addColorStop(1, `rgba(${V[0]},${V[1]},${V[2]},0.5)`);
      ctx.fillStyle = ng;
      ctx.beginPath();
      ctx.arc(0, 0, 8.5 * unit * breathe, 0, 6.2832);
      ctx.fill();

      // Swirling particles in thinking state
      if (st === 'thinking' && !reduce) {
        particles.forEach((p) => {
          p.a += p.s * 2;
          const x = Math.cos(p.a) * p.r * size;
          const y = Math.sin(p.a) * p.r * size;
          ctx.fillStyle = `rgba(${C[0]},${C[1]},${C[2]},${0.3 + p.o * 0.5})`;
          ctx.beginPath();
          ctx.arc(x, y, 1.2 * unit, 0, 6.28);
          ctx.fill();
        });
      }

      ctx.restore();
      if (!reduce) raf = requestAnimationFrame(draw);
    };

    if (reduce) draw(0);
    else raf = requestAnimationFrame(draw);

    return () => cancelAnimationFrame(raf);
  }, [size]);

  return <canvas ref={ref} className="aeon-core select-none" style={{ width: size, height: size }} aria-hidden="true" />;
}
