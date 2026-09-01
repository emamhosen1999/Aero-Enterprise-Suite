import React from 'react';
import { Head } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Badge, Table } from '@radix-ui/themes';
import { CurrencyDollarIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';
import StatsCards from '@/Components/StatsCards';

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

    const statItems = [
        { key: 'revenue', title: "Today's Revenue", value: `৳ ${Number(defaultSummary.total_revenue_today).toLocaleString()}`, color: 'green', icon: <CurrencyDollarIcon /> },
        { key: 'etc', title: 'ETC Collection Share', value: `${defaultSummary.etc_percentage}%`, color: 'blue' },
        { key: 'cash', title: 'Cash Toll Share', value: `${defaultSummary.cash_percentage}%`, color: 'amber' },
        { key: 'vehicles', title: 'Total Vehicles Processed', value: Number(defaultSummary.total_transactions_today).toLocaleString(), color: 'indigo' },
    ];

    return (
        <App auth={auth}>
            <Head title="Toll Operations & Revenue" />
            <Flex justify="center" p="4">
                <Box style={{ width: '100%', maxWidth: 2000 }}>
                    <Panel>
                        {/* ── Page Header ── */}
                        <Box mb="4">
                            <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                <Flex align="center" gap="3">
                                    <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <CurrencyDollarIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                    </Box>
                                    <Box>
                                        <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Toll Operations & Electronic Toll Collection (ETC)</Heading>
                                        <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>Dhaka Bypass Toll Revenue, Lane Collection Rates & ETC System Throughput</Text>
                                    </Box>
                                </Flex>
                            </Flex>
                        </Box>

                        <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                        <StatsCards stats={statItems} columns={{ initial: '1', sm: '2', md: '4' }} mb="4" />

                        <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
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
            </Flex>
        </App>
    );
}
