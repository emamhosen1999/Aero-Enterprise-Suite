import React from 'react';
import { Flex, Grid, Box } from '@radix-ui/themes';
import StatisticCard from './StatisticCard';

export default function StatsCards({
    stats = [],
    isLoading = false,
    variant = 'card', // 'card' | 'pill'
    columns = { initial: '1', sm: '2', md: '4' },
    activeKey = null,
    onStatClick = null,
    gap = '4',
    mb = '4',
    style,
    className,
}) {
    if (!stats || stats.length === 0) return null;

    if (variant === 'pill') {
        return (
            <Flex
                wrap="wrap"
                gap={gap}
                align="center"
                mb={mb}
                className={className}
                style={style}
            >
                {stats.map((s, i) => {
                    const key = s.key || s.id || s.title || s.label || i;
                    const isActive = activeKey !== null && String(activeKey) === String(key);
                    return (
                        <StatisticCard
                            key={key}
                            variant="pill"
                            title={s.title || s.label}
                            value={s.value}
                            icon={s.icon}
                            color={s.color}
                            isLoading={isLoading || s.isLoading}
                            active={isActive || s.active}
                            onClick={onStatClick ? () => onStatClick(s, key) : s.onClick}
                        />
                    );
                })}
            </Flex>
        );
    }

    return (
        <Box mb={mb} className={className} style={style}>
            <Grid columns={columns} gap={gap}>
                {stats.map((s, i) => {
                    const key = s.key || s.id || s.title || s.label || i;
                    const isActive = activeKey !== null && String(activeKey) === String(key);
                    return (
                        <StatisticCard
                            key={key}
                            variant="card"
                            title={s.title || s.label}
                            value={s.value}
                            icon={s.icon}
                            color={s.color}
                            description={s.description || s.subtitle}
                            trend={s.trend || s.change}
                            badge={s.badge}
                            isLoading={isLoading || s.isLoading}
                            active={isActive || s.active}
                            onClick={onStatClick ? () => onStatClick(s, key) : s.onClick}
                        />
                    );
                })}
            </Grid>
        </Box>
    );
}
