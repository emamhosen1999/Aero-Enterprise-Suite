import React from 'react';
import { Box, Flex, Grid, Skeleton, Separator, Table } from '@radix-ui/themes';
import { Panel } from '@/Components/ui/Panel';

/**
 * Basic Loading Skeleton
 */
export function LoadingSkeleton({
    variant = 'text',
    lines = 3,
    width,
    height,
    className = '',
    style = {},
}) {
    switch (variant) {
        case 'avatar':
            return (
                <Skeleton
                    style={{
                        width: width || 40,
                        height: height || 40,
                        borderRadius: '50%',
                        flexShrink: 0,
                        ...style,
                    }}
                    className={className}
                />
            );

        case 'card':
            return (
                <Panel
                    tinted
                    style={{
                        borderRadius: 16,
                        border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                        padding: 16,
                        height: height || 'auto',
                        ...style,
                    }}
                    className={className}
                >
                    <Flex align="center" gap="3">
                        <Skeleton style={{ width: 44, height: 44, borderRadius: 12 }} />
                        <Box style={{ flex: 1 }}>
                            <Skeleton style={{ width: '40%', height: 14, marginBottom: 8 }} />
                            <Skeleton style={{ width: '65%', height: 22 }} />
                        </Box>
                    </Flex>
                </Panel>
            );

        case 'table':
            return <TableLoadingSkeleton lines={lines} />;

        case 'text':
        default:
            return (
                <Flex direction="column" gap="2" style={style} className={className}>
                    {Array.from({ length: lines }).map((_, index) => (
                        <Skeleton
                            key={index}
                            style={{
                                width: width || (index === lines - 1 ? '60%' : '100%'),
                                height: height || 16,
                                borderRadius: 6,
                            }}
                        />
                    ))}
                </Flex>
            );
    }
}

/**
 * Modern Page Loading Skeleton
 * Accurately mirrors standard page structure: Header + Stat Cards + Filters + Table + Pagination
 */
export function PageLoadingSkeleton({
    hasStats = true,
    hasFilters = true,
    hasHeader = true,
    hasTabs = false,
    statsCount = 4,
    tableRows = 6,
    tableCols = 5,
}) {
    return (
        <Flex justify="center" p={{ initial: '3', sm: '4', md: '5' }}>
            <Box style={{ width: '100%', maxWidth: 2000 }}>
                <Panel
                    tinted
                    style={{
                        borderRadius: 16,
                        border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                        padding: '24px 20px',
                    }}
                >
                    {/* Header Skeleton */}
                    {hasHeader && (
                        <Box mb="4">
                            <Flex
                                direction={{ initial: 'column', sm: 'row' }}
                                align={{ initial: 'start', sm: 'center' }}
                                justify="between"
                                gap="4"
                            >
                                <Flex align="center" gap="3">
                                    <Skeleton style={{ width: 46, height: 46, borderRadius: 12 }} />
                                    <Box>
                                        <Skeleton style={{ width: 200, height: 26, marginBottom: 6, borderRadius: 6 }} />
                                        <Skeleton style={{ width: 280, height: 16, borderRadius: 4 }} />
                                    </Box>
                                </Flex>

                                <Flex gap="2" align="center">
                                    <Skeleton style={{ width: 100, height: 36, borderRadius: 12 }} />
                                    <Skeleton style={{ width: 130, height: 36, borderRadius: 12 }} />
                                </Flex>
                            </Flex>
                        </Box>
                    )}

                    <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                    {/* Tabs Placeholder */}
                    {hasTabs && (
                        <Flex gap="2" mb="4">
                            {Array.from({ length: 4 }).map((_, i) => (
                                <Skeleton key={i} style={{ width: 110, height: 36, borderRadius: 10 }} />
                            ))}
                        </Flex>
                    )}

                    {/* Stats Cards Skeleton */}
                    {hasStats && (
                        <Box mb="4">
                            <StatsCardsLoadingSkeleton count={statsCount} />
                        </Box>
                    )}

                    {/* Filters Skeleton */}
                    {hasFilters && (
                        <Flex gap="3" wrap="wrap" mb="4" align="center">
                            <Skeleton style={{ flex: 1, minWidth: 240, height: 38, borderRadius: 10 }} />
                            <Skeleton style={{ width: 160, height: 38, borderRadius: 10 }} />
                            <Skeleton style={{ width: 140, height: 38, borderRadius: 10 }} />
                            <Skeleton style={{ width: 100, height: 38, borderRadius: 10 }} />
                        </Flex>
                    )}

                    {/* Table Skeleton */}
                    <TableLoadingSkeleton rows={tableRows} columns={tableCols} />
                </Panel>
            </Box>
        </Flex>
    );
}

/**
 * Table Loading Skeleton
 */
export function TableLoadingSkeleton({ rows = 6, columns = 5, lines }) {
    const rowCount = lines || rows;
    return (
        <Box>
            <Box
                style={{
                    overflowX: 'auto',
                    WebkitOverflowScrolling: 'touch',
                    borderRadius: 16,
                    border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                    background: 'var(--aero-surface, var(--color-background))',
                }}
            >
                <Table.Root size="2" style={{ minWidth: 840, width: '100%' }}>
                    <Table.Header
                        style={{
                            background: 'var(--aero-surface, var(--color-background))',
                            boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))',
                        }}
                    >
                        <Table.Row>
                            {Array.from({ length: columns }).map((_, i) => (
                                <Table.ColumnHeaderCell key={i} style={{ padding: '12px 16px' }}>
                                    <Skeleton style={{ width: i === 0 ? '70%' : i === columns - 1 ? '40%' : '60%', height: 14, borderRadius: 4 }} />
                                </Table.ColumnHeaderCell>
                            ))}
                        </Table.Row>
                    </Table.Header>
                    <Table.Body>
                        {Array.from({ length: rowCount }).map((_, rowIndex) => (
                            <Table.Row key={rowIndex} align="center">
                                {Array.from({ length: columns }).map((_, colIndex) => (
                                    <Table.Cell key={colIndex} style={{ padding: '14px 16px' }}>
                                        {colIndex === 0 ? (
                                            <Flex align="center" gap="2">
                                                <Skeleton style={{ width: 28, height: 28, borderRadius: '50%', flexShrink: 0 }} />
                                                <Skeleton style={{ width: 140, height: 16, borderRadius: 4 }} />
                                            </Flex>
                                        ) : colIndex === columns - 1 ? (
                                            <Flex justify="end" gap="2">
                                                <Skeleton style={{ width: 28, height: 28, borderRadius: 6 }} />
                                                <Skeleton style={{ width: 28, height: 28, borderRadius: 6 }} />
                                            </Flex>
                                        ) : (
                                            <Skeleton
                                                style={{
                                                    width: colIndex === 1 ? '75%' : colIndex === 2 ? '50%' : '65%',
                                                    height: 15,
                                                    borderRadius: 4,
                                                }}
                                            />
                                        )}
                                    </Table.Cell>
                                ))}
                            </Table.Row>
                        ))}
                    </Table.Body>
                </Table.Root>
            </Box>

            {/* Pagination Placeholder */}
            <Flex justify="between" align="center" p="3" mt="2" wrap="wrap" gap="2">
                <Skeleton style={{ width: 160, height: 16, borderRadius: 4 }} />
                <Flex gap="2" align="center">
                    <Skeleton style={{ width: 110, height: 32, borderRadius: 8 }} />
                    <Skeleton style={{ width: 180, height: 32, borderRadius: 8 }} />
                </Flex>
            </Flex>
        </Box>
    );
}

/**
 * KPI Stat Cards Grid Loading Skeleton
 */
export function StatsCardsLoadingSkeleton({ count = 4 }) {
    return (
        <Grid columns={{ initial: '1', sm: '2', md: String(count) }} gap="3">
            {Array.from({ length: count }).map((_, index) => (
                <Panel
                    key={index}
                    tinted
                    style={{
                        borderRadius: 16,
                        border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                        padding: '18px 16px',
                        background: 'var(--aero-surface, var(--color-background))',
                    }}
                >
                    <Flex align="center" justify="between">
                        <Box style={{ flex: 1 }}>
                            <Skeleton style={{ width: '55%', height: 13, marginBottom: 8, borderRadius: 4 }} />
                            <Skeleton style={{ width: '40%', height: 26, borderRadius: 6 }} />
                        </Box>
                        <Skeleton style={{ width: 44, height: 44, borderRadius: 12, flexShrink: 0 }} />
                    </Flex>
                </Panel>
            ))}
        </Grid>
    );
}

/**
 * Card Grid Loading Skeleton
 */
export function CardGridLoadingSkeleton({ count = 6 }) {
    return (
        <Grid columns={{ initial: '1', sm: '2', md: '3' }} gap="4">
            {Array.from({ length: count }).map((_, index) => (
                <Panel
                    key={index}
                    tinted
                    style={{
                        borderRadius: 16,
                        border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                        padding: 20,
                        background: 'var(--aero-surface, var(--color-background))',
                    }}
                >
                    <Flex align="center" gap="3" mb="3">
                        <Skeleton style={{ width: 44, height: 44, borderRadius: 12 }} />
                        <Box style={{ flex: 1 }}>
                            <Skeleton style={{ width: '60%', height: 16, marginBottom: 6 }} />
                            <Skeleton style={{ width: '40%', height: 13 }} />
                        </Box>
                    </Flex>
                    <Skeleton style={{ width: '100%', height: 40, borderRadius: 8, marginBottom: 12 }} />
                    <Flex justify="between" align="center">
                        <Skeleton style={{ width: 70, height: 22, borderRadius: 999 }} />
                        <Skeleton style={{ width: 80, height: 28, borderRadius: 8 }} />
                    </Flex>
                </Panel>
            ))}
        </Grid>
    );
}

export default LoadingSkeleton;
