import React, { useMemo, useState } from 'react';
import { submitAeonForm } from './aeonClient.js';

export default function AeonForm({ block, onAction }) {
  const initial = useMemo(() => {
    const v = {};
    (block.fields || []).forEach((f) => {
      v[f.name] = f.value ?? (f.type === 'toggle' ? false : '');
    });
    return v;
  }, [block]);

  const [values, setValues] = useState(initial);
  const [errors, setErrors] = useState({});
  const [state, setState] = useState('idle'); // idle | sending | done
  const [banner, setBanner] = useState('');

  const set = (name, val) => setValues((p) => ({ ...p, [name]: val }));

  const submit = async (e) => {
    e.preventDefault();
    if (state === 'sending') return;

    setState('sending');
    setErrors({});
    setBanner('');

    const res = await submitAeonForm({
      action: block.action,
      method: block.method,
      values,
    });

    if (res.ok) {
      setState('done');
      return;
    }

    setState('idle');
    setErrors(res.errors || {});
    if (res.errors && res.errors._) {
      setBanner(res.errors._);
    } else if (Object.keys(res.errors || {}).length) {
      setBanner('Please review the highlighted fields below.');
    } else {
      setBanner("Couldn't submit form — please try again.");
    }
  };

  if (state === 'done') {
    return (
      <div className="aeon-form-success">
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
          <div
            style={{
              width: 32,
              height: 32,
              borderRadius: '50%',
              background: 'rgba(34,197,94,0.2)',
              color: '#4ade80',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontWeight: 'bold',
              fontSize: 16,
              flexShrink: 0,
            }}
          >
            ✓
          </div>
          <div>
            <div style={{ fontSize: 14, fontWeight: 700, color: '#4ade80' }}>Successfully Submitted</div>
            <div style={{ fontSize: 11, color: 'var(--aeon-text-secondary)' }}>
              Saved to Guardian with full authorization & audit logging.
            </div>
          </div>
        </div>
        <button
          type="button"
          onClick={() => onAction?.({ kind: 'navigate', block: { route: block.action || '/' } })}
          style={{
            padding: '8px 16px',
            borderRadius: 8,
            fontSize: 12,
            fontWeight: 600,
            border: 'none',
            background: 'rgba(34,197,94,0.15)',
            color: '#4ade80',
            cursor: 'pointer',
            fontFamily: 'inherit',
          }}
        >
          View Records →
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={submit} style={{ margin: '12px 0' }}>
      <div className="aeon-form-card">
        <div className="aeon-form-header">
          <span
            style={{
              fontSize: 10,
              fontWeight: 700,
              textTransform: 'uppercase',
              letterSpacing: '0.06em',
              padding: '3px 10px',
              borderRadius: 9999,
              background: 'rgba(34,227,255,0.1)',
              color: 'var(--aeon-cyan)',
              border: '1px solid rgba(34,227,255,0.25)',
            }}
          >
            {block.kind?.toUpperCase() || 'FORM'}
          </span>
          <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--aeon-text-primary)' }}>
            {block.title}
          </span>
        </div>

        {banner && (
          <div
            style={{
              padding: '8px 12px',
              borderRadius: 8,
              background: 'rgba(239,68,68,0.08)',
              border: '1px solid rgba(239,68,68,0.2)',
              color: '#f87171',
              fontSize: 12,
              marginBottom: 12,
            }}
          >
            {banner}
          </div>
        )}

        <div className="aeon-form-fields">
          {(block.fields || []).map((f) => (
            <div key={f.name} className="aeon-form-field">
              <div className="aeon-form-label">
                <span>
                  {f.label} {f.required && <span style={{ color: '#f87171' }}>*</span>}
                </span>
                {errors[f.name] && (
                  <span style={{ fontSize: 11, color: '#f87171' }}>
                    {Array.isArray(errors[f.name]) ? errors[f.name][0] : String(errors[f.name])}
                  </span>
                )}
              </div>

              <FieldControl
                field={f}
                value={values[f.name]}
                onChange={(val) => set(f.name, val)}
              />
            </div>
          ))}
        </div>

        <div className="aeon-form-footer">
          <span style={{ fontSize: 11, color: 'var(--aeon-text-muted)', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {block.note || 'Secured endpoint'}
          </span>
          <button
            type="submit"
            disabled={state === 'sending'}
            style={{
              padding: '8px 18px',
              borderRadius: 8,
              fontSize: 12,
              fontWeight: 600,
              border: 'none',
              background: state === 'sending' ? 'rgba(34,227,255,0.3)' : 'linear-gradient(135deg, var(--aeon-cyan), var(--aeon-violet))',
              color: '#fff',
              cursor: state === 'sending' ? 'not-allowed' : 'pointer',
              fontFamily: 'inherit',
              boxShadow: '0 2px 12px rgba(34,227,255,0.2)',
            }}
          >
            {state === 'sending' ? 'Submitting…' : (block.submit_label || 'Submit')}
          </button>
        </div>
      </div>
    </form>
  );
}

const fieldInputStyle = {
  width: '100%',
  padding: '8px 12px',
  borderRadius: 8,
  fontSize: 12,
  background: 'rgba(255,255,255,0.04)',
  border: '1px solid rgba(255,255,255,0.1)',
  color: 'var(--aeon-text-primary)',
  outline: 'none',
  fontFamily: 'inherit',
  transition: 'border-color 0.15s ease',
  boxSizing: 'border-box',
};

function FieldControl({ field, value, onChange }) {
  switch (field.type) {
    case 'select':
      return (
        <select
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          style={{ ...fieldInputStyle, appearance: 'none', cursor: 'pointer' }}
        >
          <option value="">Select an option…</option>
          {(field.options || []).map((o) => (
            <option key={String(o.value)} value={String(o.value)}>
              {o.label}
            </option>
          ))}
        </select>
      );

    case 'textarea':
      return (
        <textarea
          rows={3}
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          style={{ ...fieldInputStyle, resize: 'vertical', minHeight: 60 }}
        />
      );

    case 'toggle':
      return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 0' }}>
          <button
            type="button"
            onClick={() => onChange(!value)}
            style={{
              width: 36,
              height: 20,
              borderRadius: 10,
              border: 'none',
              background: value ? 'var(--aeon-cyan)' : 'rgba(255,255,255,0.15)',
              cursor: 'pointer',
              position: 'relative',
              transition: 'background 0.2s',
            }}
          >
            <span
              style={{
                position: 'absolute',
                top: 2,
                left: value ? 18 : 2,
                width: 16,
                height: 16,
                borderRadius: '50%',
                background: '#fff',
                transition: 'left 0.2s',
              }}
            />
          </button>
          <span style={{ fontSize: 12, color: 'var(--aeon-text-secondary)' }}>{value ? 'Yes' : 'No'}</span>
        </div>
      );

    case 'date':
      return (
        <input
          type="date"
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          style={fieldInputStyle}
        />
      );

    case 'number':
      return (
        <input
          type="number"
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          style={fieldInputStyle}
        />
      );

    default:
      return (
        <input
          type={field.type || 'text'}
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          style={fieldInputStyle}
        />
      );
  }
}
