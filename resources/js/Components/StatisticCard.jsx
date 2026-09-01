import { Panel } from '@/Components/ui/Panel';
import React from 'react';
import { Flex, Box, Text, Skeleton } from '@radix-ui/themes';

const parseColor = (colorClass) => {
  if (!colorClass) return 'gray';
  const c = colorClass.toLowerCase();
  if (c.includes('green') || c.includes('success')) return 'green';
  if (c.includes('red') || c.includes('danger')) return 'red';
  if (c.includes('blue') || c.includes('info') || c.includes('primary')) return 'blue';
  if (c.includes('amber') || c.includes('warning') || c.includes('orange')) return 'amber';
  if (c.includes('purple')) return 'purple';
  if (c.includes('pink')) return 'pink';
  return 'gray';
};

export default function StatisticCard({
  title,
  value,
  icon,
  color,
  description,
  isLoading = false,
}) {
  const radixColor = parseColor(color);

  return (
    <Panel tinted style={{ flex: '1 1 200px', minWidth: 200, borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '16px' }}>
      <Flex align="center" justify="between" mb="2">
        <Text size="1" style={{ textTransform: 'uppercase', letterSpacing: '0.06em', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 700, color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11 }}>
          {title}
        </Text>
        {icon && (
          <Box style={{
            padding: 6,
            borderRadius: 10,
            background: `var(--${radixColor}-a3)`,
            border: `1px solid var(--${radixColor}-a5)`,
            color: `var(--${radixColor}-9)`,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}>
            {icon}
          </Box>
        )}
      </Flex>
      {isLoading ? (
        <Flex direction="column" gap="1">
          <Skeleton style={{ width: 52, height: 28, borderRadius: 6 }} />
          <Skeleton style={{ width: 100, height: 12, borderRadius: 4 }} />
        </Flex>
      ) : (
        <Flex direction="column" gap="1">
          <Text size="6" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, fontVariantNumeric: 'tabular-nums', color: 'var(--gray-12)', letterSpacing: '-0.02em', lineHeight: 1.1 }}>
            {value ?? '\u2014'}
          </Text>
          {description && (
            <Text size="1" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>
              {description}
            </Text>
          )}
        </Flex>
      )}
    </Panel>
  );
}
