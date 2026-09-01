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
            <Flex justify="center" p={{ initial: '3', sm: '4', md: '5' }}>
                <Box style={{ width: '100%', maxWidth: 2000 }}>
                    <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '24px 20px' }}>
                        {/* ── Page Header ── */}
                        <Box mb="4">
                            <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                <Flex align="center" gap="3">
                                    <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <ClipboardDocumentCheckIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                    </Box>
                                    <Box>
                                        <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Digital Shift Logbook & Operator Handover Audit</Heading>
                                        <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>TMC & O&M Shift Transfers: Shift Notes, Open Incident Counts & Equipment Exceptions</Text>
                                    </Box>
                                </Flex>
                            </Flex>
                        </Box>

                        <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

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
                                        <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}>Shift Date</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}>Shift Type</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 160, background: 'inherit' }}>Duty Operator</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}>Open Incidents</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 260, background: 'inherit' }}>Handover Notes</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 120, background: 'inherit' }}>Status</Table.ColumnHeaderCell>
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
            </Flex>
        </App>
    );
}
