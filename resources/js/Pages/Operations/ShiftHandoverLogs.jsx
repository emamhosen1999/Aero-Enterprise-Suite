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

                <Panel p="0" style={{ overflow: 'hidden', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                    <Box p="4" style={{ borderBottom: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                        <Heading size="4" weight="bold">Operator Shift Handover Trail</Heading>
                    </Box>
                    <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
                        <Table.Root size="2" style={{ minWidth: 840, width: '100%' }}>
                            <Table.Header>
                                <Table.Row>
                                    <Table.ColumnHeaderCell style={{ minWidth: 120 }}>Shift Date</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Shift Type</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 160 }}>Duty Operator</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 120 }}>Open Incidents</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 260 }}>Handover Notes</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 120 }}>Status</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {logs.map((log) => (
                                    <Table.Row key={log.id} align="center">
                                        <Table.Cell><Text size="2" style={{ fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{log.shift_date}</Text></Table.Cell>
                                        <Table.Cell>
                                            <Badge color={log.shift_type === 'morning' ? 'amber' : log.shift_type === 'evening' ? 'blue' : 'indigo'} variant="soft" style={{ borderRadius: 999, fontWeight: 700 }}>
                                                {log.shift_type.toUpperCase()}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell><Text weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, whiteSpace: 'nowrap' }}>{log.operator?.name || 'Operator'}</Text></Table.Cell>
                                        <Table.Cell><Text size="2" style={{ fontVariantNumeric: 'tabular-nums' }}>{log.open_incidents_count}</Text></Table.Cell>
                                        <Table.Cell><Text size="2" style={{ display: 'block', maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{log.handover_notes}</Text></Table.Cell>
                                        <Table.Cell style={{ textAlign: 'right' }}>
                                            <Badge color={log.is_acknowledged ? 'green' : 'amber'} variant="soft" style={{ borderRadius: 999 }}>
                                                {log.is_acknowledged ? 'Acknowledged' : 'Pending'}
                                            </Badge>
                                        </Table.Cell>
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
