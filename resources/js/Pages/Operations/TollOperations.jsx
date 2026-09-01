import React from 'react';
import { Head } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Badge, Table } from '@radix-ui/themes';
import { CurrencyDollarIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function TollOperations({ auth, summary, tollRecords }) {
    const defaultSummary = summary || {
        total_revenue_today: 485200.00,
        etc_percentage: 78.4,
        cash_percentage: 21.6,
        total_transactions_today: 3840,
    };

    const records = tollRecords?.data || [
        { id: 1, plaza_name: 'Main Toll Plaza (Ch 0+000)', lane_id: 'Lane 1 (ETC)', vehicle_class: 'Heavy Truck 3-Axle', payment_method: 'etc', amount: 350.00, transacted_at: '2026-08-19 15:30:12' },
        { id: 2, plaza_name: 'Main Toll Plaza (Ch 0+000)', lane_id: 'Lane 2 (ETC)', vehicle_class: 'Private Car / SUV', payment_method: 'etc', amount: 100.00, transacted_at: '2026-08-19 15:29:45' },
        { id: 3, plaza_name: 'Kanchan Interchange Toll', lane_id: 'Lane 3 (Cash)', vehicle_class: 'Medium Bus', payment_method: 'cash', amount: 220.00, transacted_at: '2026-08-19 15:28:10' },
        { id: 4, plaza_name: 'Main Toll Plaza (Ch 0+000)', lane_id: 'Lane 4 (ETC)', vehicle_class: 'Light Commercial Vehicle', payment_method: 'etc', amount: 150.00, transacted_at: '2026-08-19 15:27:02' },
    ];

    return (
        <App auth={auth}>
            <Head title="Toll Operations & Revenue" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Toll Operations & Electronic Toll Collection (ETC)
                        </Heading>
                        <Text size="2" color="gray">
                            Dhaka Bypass Toll Revenue, Lane Collection Rates & ETC System Throughput
                        </Text>
                    </Box>
                </Flex>

                <Grid columns={{ initial: '1', sm: '2', md: '4' }} gap="4" mb="5">
                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Today's Revenue</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--green-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>৳ {Number(defaultSummary.total_revenue_today).toLocaleString()}</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>ETC Collection Share</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--blue-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{defaultSummary.etc_percentage}%</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Cash Toll Share</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--amber-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{defaultSummary.cash_percentage}%</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Total Vehicles Processed</Text>
                        <Heading size="6" mt="1" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{Number(defaultSummary.total_transactions_today).toLocaleString()}</Heading>
                    </Panel>
                </Grid>

                <Panel p="0" style={{ overflow: 'hidden', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                    <Box p="4" style={{ borderBottom: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                        <Heading size="4" weight="bold">Live Toll Transaction Stream</Heading>
                    </Box>
                    <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
                        <Table.Root size="2" style={{ minWidth: 840, width: '100%' }}>
                            <Table.Header style={{
                                position: 'sticky',
                                top: 0,
                                zIndex: 2,
                                background: 'var(--aero-surface, var(--color-background))',
                                backdropFilter: 'blur(8px)',
                                boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))'
                            }}>
                                <Table.Row>
                                    <Table.ColumnHeaderCell style={{ minWidth: 180, background: 'inherit' }}>Plaza</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}>Lane</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 180, background: 'inherit' }}>Vehicle Class</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 130, background: 'inherit' }}>Payment Method</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}>Toll Amount</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 150, background: 'inherit' }}>Time</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {records.map((rec) => (
                                    <Table.Row key={rec.id} align="center">
                                        <Table.Cell><Text weight="bold" style={{ whiteSpace: 'nowrap' }}>{rec.plaza_name}</Text></Table.Cell>
                                        <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{rec.lane_id}</Text></Table.Cell>
                                        <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{rec.vehicle_class}</Text></Table.Cell>
                                        <Table.Cell>
                                            <Badge color={rec.payment_method === 'etc' ? 'green' : 'amber'} variant="soft" style={{ borderRadius: 999, fontWeight: 700 }}>
                                                {rec.payment_method.toUpperCase()}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Text weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>৳ {Number(rec.amount).toFixed(2)}</Text>
                                        </Table.Cell>
                                        <Table.Cell style={{ textAlign: 'right', color: 'var(--gray-10)', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{rec.transacted_at}</Table.Cell>
                                    </Table.Row>
                                ))}
                            </Table.Body>
                        </Table.Root>
                    </Box>
                </Panel>
            </Box>
        </App>
    );
}
