import React from 'react';
import { Head } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Badge, Table } from '@radix-ui/themes';
import { ClipboardDocumentCheckIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function ShiftHandoverLogs({ auth, shiftLogs }) {
    const logs = shiftLogs?.data || [
        { id: 1, shift_date: '2026-08-19', shift_type: 'morning', operator: { name: 'Emam Hosen' }, open_incidents_count: 2, handover_notes: 'All lanes running smooth. WIM Scale 1 calibrated at 11:00 AM.', equipment_exceptions: 'None', is_acknowledged: true },
        { id: 2, shift_date: '2026-08-18', shift_type: 'night', operator: { name: 'TMC Duty Officer' }, open_incidents_count: 0, handover_notes: 'No incidents during night shift. Heavy truck volume between 2 AM and 4 AM.', equipment_exceptions: 'None', is_acknowledged: true },
    ];

    return (
        <App auth={auth}>
            <Head title="Digital Shift Handover Logs" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Digital Shift Logbook & Operator Handover Audit
                        </Heading>
                        <Text size="2" color="gray">
                            TMC & O&M Shift Transfers: Shift Notes, Open Incident Counts & Equipment Exceptions
                        </Text>
                    </Box>
                </Flex>

                <Panel style={{ padding: 20 }}>
                    <Heading size="4" mb="3">Operator Shift Handover Trail</Heading>
                    <Table.Root variant="surface">
                        <Table.Header>
                            <Table.Row>
                                <Table.ColumnHeaderCell>Shift Date</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Shift Type</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Duty Operator</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Open Incidents</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Handover Notes</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Status</Table.ColumnHeaderCell>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {logs.map((log) => (
                                <Table.Row key={log.id}>
                                    <Table.RowHeaderCell>{log.shift_date}</Table.RowHeaderCell>
                                    <Table.Cell>
                                        <Badge color={log.shift_type === 'morning' ? 'amber' : log.shift_type === 'evening' ? 'blue' : 'indigo'}>
                                            {log.shift_type.toUpperCase()}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell><Text weight="bold">{log.operator?.name || 'Operator'}</Text></Table.Cell>
                                    <Table.Cell>{log.open_incidents_count}</Table.Cell>
                                    <Table.Cell>{log.handover_notes}</Table.Cell>
                                    <Table.Cell>
                                        <Badge color={log.is_acknowledged ? 'green' : 'amber'}>
                                            {log.is_acknowledged ? 'Acknowledged' : 'Pending'}
                                        </Badge>
                                    </Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table.Root>
                </Panel>
            </Box>
        </App>
    );
}
