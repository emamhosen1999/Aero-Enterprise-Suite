import React from 'react';
import { Flex, Box, Text, Skeleton, Badge } from '@radix-ui/themes';

const parseColor = (color) => {
    if (!color) return 'blue';
    const c = String(color).toLowerCase();
    if (c.includes('green') || c.includes('success') || c.includes('jade')) return 'green';
    if (c.includes('red') || c.includes('danger') || c.includes('crimson') || c.includes('ruby')) return 'red';
    if (c.includes('amber') || c.includes('warning') || c.includes('orange')) return 'amber';
    if (c.includes('indigo')) return 'indigo';
    if (c.includes('violet') || c.includes('purple')) return 'violet';
    if (c.includes('teal') || c.includes('cyan')) return 'teal';
    if (c.includes('pink') || c.includes('plum')) return 'plum';
    if (c.includes('blue') || c.includes('info') || c.includes('primary')) return 'blue';
    return 'gray';
};

export default function StatisticCard({
    title,
    label,
    value,
    icon: IconOrElement,
    color = 'blue',
    description,
    subtitle,
    trend,
    change,
    badge,
    isLoading = false,
    variant = 'card', // 'card' | 'pill'
    onClick,
    active = false,
    style,
    className,
}) {
    const displayTitle = title || label;
    const displayDesc = description || subtitle;
    const displayTrend = trend || change;
    const radixColor = parseColor(color);
    const isClickable = Boolean(onClick);

    // Compact Pill Variant
    if (variant === 'pill') {
        return (
            <Flex
                align="center"
                gap="2"
                px="3"
                py="2"
                onClick={onClick}
                className={className}
                style={{
                    background: active
                        ? `var(--${radixColor}-a3)`
                        : 'var(--aero-surface, var(--color-background))',
                    border: active
                        ? `1px solid var(--${radixColor}-8)`
                        : '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                    borderRadius: 12,
                    cursor: isClickable ? 'pointer' : 'default',
                    transition: 'all 0.15s ease',
                    userSelect: 'none',
                    ...style,
                }}
            >
                {IconOrElement && (
                    <Box style={{ color: `var(--${radixColor}-9)`, display: 'flex', alignItems: 'center' }}>
                        {React.isValidElement(IconOrElement) ? IconOrElement : <IconOrElement width={14} height={14} />}
                    </Box>
                )}
                {isLoading ? (
                    <Skeleton style={{ width: 32, height: 18, borderRadius: 4 }} />
                ) : (
                    <Text
                        weight="bold"
                        size="2"
                        style={{
                            fontFamily: `'Space Grotesk', system-ui, sans-serif`,
                            fontVariantNumeric: 'tabular-nums',
                            color: active ? `var(--${radixColor}-11)` : 'var(--gray-12)',
                        }}
                    >
                        {value ?? 0}
                    </Text>
                )}
                {displayTitle && (
                    <Text size="1" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>
                        {displayTitle}
                    </Text>
                )}
            </Flex>
        );
    }

    // Standard Full Card Variant
    return (
        <Box
            onClick={onClick}
            className={className}
            style={{
                flex: '1 1 220px',
                minWidth: 200,
                borderRadius: 14,
                border: active
                    ? `1px solid var(--${radixColor}-8)`
                    : '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                background: active
                    ? `var(--${radixColor}-a2)`
                    : 'var(--aero-surface, var(--color-background))',
                padding: '16px 20px',
                cursor: isClickable ? 'pointer' : 'default',
                transition: 'border-color 0.15s ease, background-color 0.15s ease, transform 0.1s ease',
                ...style,
            }}
        >
            <Flex align="center" justify="between" mb="2">
                <Text
                    size="1"
                    style={{
                        textTransform: 'uppercase',
                        letterSpacing: '0.06em',
                        fontFamily: `'Space Grotesk', system-ui, sans-serif`,
                        fontWeight: 700,
                        color: 'var(--aero-color-subtle, var(--gray-9))',
                        fontSize: 11,
                    }}
                >
                    {displayTitle}
                </Text>
                <Flex align="center" gap="2">
                    {badge && (
                        <Badge size="1" color={radixColor} variant="soft" style={{ borderRadius: 999 }}>
                            {badge}
                        </Badge>
                    )}
                    {IconOrElement && (
                        <Box
                            style={{
                                padding: 7,
                                borderRadius: 10,
                                background: `var(--${radixColor}-a3)`,
                                border: `1px solid var(--${radixColor}-a5)`,
                                color: `var(--${radixColor}-9)`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            {React.isValidElement(IconOrElement)
                                ? IconOrElement
                                : <IconOrElement width={18} height={18} />}
                        </Box>
                    )}
                </Flex>
            </Flex>

            {isLoading ? (
                <Flex direction="column" gap="2" mt="1">
                    <Skeleton style={{ width: 64, height: 32, borderRadius: 6 }} />
                    <Skeleton style={{ width: 120, height: 14, borderRadius: 4 }} />
                </Flex>
            ) : (
                <Flex direction="column" gap="1">
                    <Flex align="baseline" gap="2">
                        <Text
                            size="6"
                            weight="bold"
                            style={{
                                fontFamily: `'Space Grotesk', system-ui, sans-serif`,
                                fontWeight: 800,
                                fontVariantNumeric: 'tabular-nums',
                                color: 'var(--gray-12)',
                                letterSpacing: '-0.02em',
                                lineHeight: 1.15,
                            }}
                        >
                            {value ?? '\u2014'}
                        </Text>
                        {displayTrend && (
                            <Text
                                size="1"
                                weight="medium"
                                style={{
                                    fontVariantNumeric: 'tabular-nums',
                                    color: String(displayTrend).includes('-')
                                        ? 'var(--red-9)'
                                        : 'var(--green-9)',
                                }}
                            >
                                {displayTrend}
                            </Text>
                        )}
                    </Flex>
                    {displayDesc && (
                        <Text size="1" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', marginTop: 2 }}>
                            {displayDesc}
                        </Text>
                    )}
                </Flex>
            )}
        </Box>
    );
}
