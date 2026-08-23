import React from 'react';
import { Box, Flex, Text, Badge } from '@radix-ui/themes';
import {
    PersonIcon,
    CheckCircledIcon,
    ClockIcon,
    GlobeIcon,
    LapTimerIcon
} from '@radix-ui/react-icons';
import { THEME_COLORS } from './mapConstants';

export const MapStatsRibbon = React.memo(({ stats, lastUpdateText, isPolling, secondsLeft }) => {
    const total = stats?.total || 0;
    const active = stats?.checkedIn ?? stats?.active ?? 0;
    const completed = stats?.completed || 0;
    const activePercent = total > 0 ? Math.round((active / total) * 100) : 0;

    return (
        <Box
            p="3"
            style={{
                background: 'linear-gradient(135deg, var(--gray-a2), var(--gray-a3))',
                borderBottom: '1px solid var(--gray-a4)',
            }}
        >
            <Flex justify="between" align="center" gap="3" wrap="wrap">
                {/* Metric Badges */}
                <Flex align="center" gap="3" wrap="wrap">
                    {/* Total On-Duty */}
                    <Flex
                        align="center"
                        gap="2"
                        px="3"
                        py="2"
                        style={{
                            borderRadius: 'var(--radius-3)',
                            background: 'var(--color-panel-solid, #ffffff)',
                            border: '1px solid var(--gray-a4)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                        }}
                    >
                        <Flex
                            align="center"
                            justify="center"
                            style={{
                                width: 28,
                                height: 28,
                                borderRadius: '50%',
                                background: 'var(--blue-a3)',
                                color: 'var(--blue-9)',
                            }}
                        >
                            <PersonIcon style={{ width: 16, height: 16 }} />
                        </Flex>
                        <Box>
                            <Flex align="baseline" gap="1">
                                <Text size="4" weight="bold" style={{ color: 'var(--gray-12)' }}>{total}</Text>
                                <Text size="1" color="gray">Officers</Text>
                            </Flex>
                            <Text size="1" color="gray" style={{ fontSize: 10, display: 'block', marginTop: -2 }}>
                                Total Tracked
                            </Text>
                        </Box>
                    </Flex>

                    {/* Active Live */}
                    <Flex
                        align="center"
                        gap="2"
                        px="3"
                        py="2"
                        style={{
                            borderRadius: 'var(--radius-3)',
                            background: 'var(--color-panel-solid, #ffffff)',
                            border: '1px solid var(--green-a5)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                        }}
                    >
                        <Flex
                            align="center"
                            justify="center"
                            style={{
                                width: 28,
                                height: 28,
                                borderRadius: '50%',
                                background: 'var(--green-a3)',
                                color: 'var(--green-9)',
                                position: 'relative'
                            }}
                        >
                            <ClockIcon style={{ width: 16, height: 16 }} />
                            {active > 0 && (
                                <span
                                    style={{
                                        position: 'absolute',
                                        top: 1,
                                        right: 1,
                                        width: 8,
                                        height: 8,
                                        borderRadius: '50%',
                                        background: THEME_COLORS.active,
                                        border: '1.5px solid white'
                                    }}
                                />
                            )}
                        </Flex>
                        <Box>
                            <Flex align="baseline" gap="1">
                                <Text size="4" weight="bold" style={{ color: 'var(--green-11)' }}>{active}</Text>
                                <Badge size="1" color="green" variant="soft" radius="full">
                                    {activePercent}%
                                </Badge>
                            </Flex>
                            <Text size="1" color="gray" style={{ fontSize: 10, display: 'block', marginTop: -2 }}>
                                Active On-Duty
                            </Text>
                        </Box>
                    </Flex>

                    {/* Completed Shifts */}
                    <Flex
                        align="center"
                        gap="2"
                        px="3"
                        py="2"
                        style={{
                            borderRadius: 'var(--radius-3)',
                            background: 'var(--color-panel-solid, #ffffff)',
                            border: '1px solid var(--gray-a4)',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                        }}
                    >
                        <Flex
                            align="center"
                            justify="center"
                            style={{
                                width: 28,
                                height: 28,
                                borderRadius: '50%',
                                background: 'var(--blue-a3)',
                                color: 'var(--blue-9)',
                            }}
                        >
                            <CheckCircledIcon style={{ width: 16, height: 16 }} />
                        </Flex>
                        <Box>
                            <Flex align="baseline" gap="1">
                                <Text size="4" weight="bold" style={{ color: 'var(--blue-11)' }}>{completed}</Text>
                                <Text size="1" color="gray">Completed</Text>
                            </Flex>
                            <Text size="1" color="gray" style={{ fontSize: 10, display: 'block', marginTop: -2 }}>
                                Finished Shifts
                            </Text>
                        </Box>
                    </Flex>
                </Flex>

                {/* Live Sync Status */}
                <Flex align="center" gap="2">
                    <Flex
                        align="center"
                        gap="2"
                        px="2"
                        py="1"
                        style={{
                            background: 'var(--gray-a3)',
                            borderRadius: 'var(--radius-2)',
                            border: '1px solid var(--gray-a4)'
                        }}
                    >
                        <Flex
                            align="center"
                            justify="center"
                            style={{
                                width: 8,
                                height: 8,
                                borderRadius: '50%',
                                background: isPolling ? THEME_COLORS.active : 'var(--gray-8)',
                                boxShadow: isPolling ? '0 0 8px #10b981' : 'none'
                            }}
                        />
                        <Text size="1" color="gray">
                            {isPolling ? `Live Sync (${secondsLeft}s)` : 'Polling Paused'}
                        </Text>
                    </Flex>

                    {lastUpdateText && (
                        <Text size="1" color="gray" style={{ fontSize: 11 }}>
                            Updated: {lastUpdateText}
                        </Text>
                    )}
                </Flex>
            </Flex>
        </Box>
    );
});

MapStatsRibbon.displayName = 'MapStatsRibbon';
export default MapStatsRibbon;
