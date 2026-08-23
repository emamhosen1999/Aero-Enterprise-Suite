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
                    <Panel tinted style={{ padding: 18 }}>
                        <Text size="1" color="gray" weight="bold" style={{ textTransform: 'uppercase' }}>Today's Revenue</Text>
                        <Heading size="6" style={{ color: 'var(--green-11)' }}>৳ {Number(defaultSummary.total_revenue_today).toLocaleString()}</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Text size="1" color="gray" weight="bold" style={{ textTransform: 'uppercase' }}>ETC Collection Share</Text>
                        <Heading size="6" style={{ color: 'var(--blue-11)' }}>{defaultSummary.etc_percentage}%</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Text size="1" color="gray" weight="bold" style={{ textTransform: 'uppercase' }}>Cash Toll Share</Text>
                        <Heading size="6" style={{ color: 'var(--amber-11)' }}>{defaultSummary.cash_percentage}%</Heading>
                    </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Text size="1" color="gray" weight="bold" style={{ textTransform: 'uppercase' }}>Total Vehicles Processed</Text>
                        <Heading size="6">{Number(defaultSummary.total_transactions_today).toLocaleString()}</Heading>
                    </Panel>
                </Grid>

                <Panel style={{ padding: 20 }}>
                    <Heading size="4" mb="3">Live Toll Transaction Stream</Heading>
                    <Table.Root variant="surface">
                        <Table.Header>
                            <Table.Row>
                                <Table.ColumnHeaderCell>Plaza</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Lane</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Vehicle Class</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Payment Method</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Toll Amount</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Time</Table.ColumnHeaderCell>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {records.map((rec) => (
                                <Table.Row key={rec.id}>
                                    <Table.RowHeaderCell>{rec.plaza_name}</Table.RowHeaderCell>
                                    <Table.Cell>{rec.lane_id}</Table.Cell>
                                    <Table.Cell>{rec.vehicle_class}</Table.Cell>
                                    <Table.Cell>
                                        <Badge color={rec.payment_method === 'etc' ? 'green' : 'amber'}>
                                            {rec.payment_method.toUpperCase()}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Text weight="bold">৳ {Number(rec.amount).toFixed(2)}</Text>
                                    </Table.Cell>
                                    <Table.Cell style={{ color: 'var(--gray-10)' }}>{rec.transacted_at}</Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table.Root>
                </Panel>
            </Box>
        </App>
    );
}
