import React, { useMemo, useState } from 'react';
import { Card, Flex, Box, Text, Button, TextField, TextArea, Select, Switch, Badge, Callout } from '@radix-ui/themes';
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
      <Card size="1" variant="surface" className="p-4 my-3 bg-green-950/20 border border-green-500/30 rounded-xl">
        <Flex align="center" gap="3" className="mb-3">
          <div className="w-8 h-8 rounded-full bg-green-500/20 text-green-400 flex items-center justify-center font-bold">
            ✓
          </div>
          <Box>
            <Text size="2" weight="bold" className="text-green-300">Successfully Submitted</Text>
            <Text size="1" color="gray" className="block">Saved to Guardian with full authorization & audit logging.</Text>
          </Box>
        </Flex>
        <Button
          size="2"
          color="green"
          variant="soft"
          className="cursor-pointer font-medium"
          onClick={() => onAction?.({ kind: 'navigate', block: { route: block.action || '/' } })}
        >
          View Records →
        </Button>
      </Card>
    );
  }

  return (
    <form onSubmit={submit} className="my-3">
      <Card size="1" variant="surface" className="p-4 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl">
        <Flex justify="between" align="center" className="mb-3">
          <Badge size="1" color="cyan" variant="soft">{block.kind?.toUpperCase() || 'FORM'}</Badge>
          <Text size="2" weight="bold" className="text-[var(--gray-12)]">{block.title}</Text>
        </Flex>

        {banner && (
          <Callout.Root color="red" size="1" className="mb-3">
            <Callout.Text size="1">{banner}</Callout.Text>
          </Callout.Root>
        )}

        <div className="space-y-3">
          {(block.fields || []).map((f) => (
            <div key={f.name} className="space-y-1">
              <Flex justify="between">
                <Text size="1" weight="medium" className="text-[var(--gray-11)]">
                  {f.label} {f.required && <span className="text-red-400">*</span>}
                </Text>
                {errors[f.name] && (
                  <Text size="1" color="red" className="text-[11px]">
                    {Array.isArray(errors[f.name]) ? errors[f.name][0] : String(errors[f.name])}
                  </Text>
                )}
              </Flex>

              <FieldControl
                field={f}
                value={values[f.name]}
                onChange={(val) => set(f.name, val)}
              />
            </div>
          ))}
        </div>

        <Flex justify="between" align="center" className="mt-4 pt-3 border-t border-[var(--gray-4,rgba(255,255,255,0.08))]">
          <Text size="1" color="gray" className="text-[11px] truncate max-w-[200px]">
            {block.note || 'Secured endpoint'}
          </Text>
          <Button
            type="submit"
            size="2"
            color="cyan"
            variant="solid"
            disabled={state === 'sending'}
            className="cursor-pointer font-medium"
          >
            {state === 'sending' ? 'Submitting…' : (block.submit_label || 'Submit')}
          </Button>
        </Flex>
      </Card>
    </form>
  );
}

function FieldControl({ field, value, onChange }) {
  switch (field.type) {
    case 'select':
      return (
        <Select.Root value={value ? String(value) : undefined} onValueChange={onChange}>
          <Select.Trigger placeholder="Select an option…" className="w-full text-xs" />
          <Select.Content position="popper">
            {(field.options || []).map((o) => (
              <Select.Item key={String(o.value)} value={String(o.value)}>
                {o.label}
              </Select.Item>
            ))}
          </Select.Content>
        </Select.Root>
      );

    case 'textarea':
      return (
        <TextArea
          size="1"
          rows={3}
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          className="text-xs"
        />
      );

    case 'toggle':
      return (
        <Flex align="center" gap="2" className="py-1">
          <Switch checked={!!value} onCheckedChange={onChange} size="1" color="cyan" />
          <Text size="1" color="gray">{value ? 'Yes' : 'No'}</Text>
        </Flex>
      );

    case 'date':
      return (
        <input
          type="date"
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          className="w-full px-2.5 py-1.5 rounded-md text-xs bg-[var(--gray-3,rgba(255,255,255,0.06))] border border-[var(--gray-5,rgba(255,255,255,0.12))] text-[var(--gray-12)] focus:outline-none focus:border-[var(--accent-9)]"
        />
      );

    case 'number':
      return (
        <TextField.Root
          size="1"
          type="number"
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          className="text-xs"
        />
      );

    default:
      return (
        <TextField.Root
          size="1"
          type={field.type || 'text'}
          value={value ?? ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`Enter ${field.label.toLowerCase()}…`}
          className="text-xs"
        />
      );
  }
}
