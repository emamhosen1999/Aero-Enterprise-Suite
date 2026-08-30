import React from 'react';
import { usePage } from '@inertiajs/react';
import { Card, Flex, Box, Text, Progress, Badge } from '@radix-ui/themes';

/**
 * Embedded AI Usage Card reflecting daily token allowance.
 */
export default function AiUsageCard({ variant = 'card' }) {
  const aeon = usePage().props?.aeon;
  if (!aeon?.available || !aeon?.usage) return null;

  const u = aeon.usage;
  const limit = u.limit || 500000;
  const used = u.used || 0;
  const pct = Math.min(100, Math.round((used / Math.max(1, limit)) * 100));
  const remaining = Math.max(0, limit - used);

  const body = (
    <div className="space-y-2">
      <Flex justify="between" align="center">
        <Badge size="1" color="cyan" variant="soft">✦ Aeon AI Copilot</Badge>
        <Text size="1" color="gray">{u.model || 'Gemini'}</Text>
      </Flex>
      <div>
        <Text size="2" weight="bold" className="block text-[var(--accent-11,#22e3ff)] font-mono">
          {remaining.toLocaleString()} tokens remaining today
        </Text>
        <Progress value={pct} color="cyan" size="1" className="mt-2" />
      </div>
    </div>
  );

  if (variant === 'row') {
    return <div className="py-2">{body}</div>;
  }

  return (
    <Card size="1" variant="surface" className="p-3.5 bg-[var(--gray-2,rgba(255,255,255,0.03))] border border-[var(--gray-4,rgba(255,255,255,0.08))] rounded-xl">
      {body}
    </Card>
  );
}
