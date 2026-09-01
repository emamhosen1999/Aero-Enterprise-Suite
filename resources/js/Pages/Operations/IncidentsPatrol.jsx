import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Button, Badge, Table, TextField, Dialog, Select } from '@radix-ui/themes';
import { ShieldCheckIcon, PlusIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function IncidentsPatrol({ auth, metrics, incidents }) {
    const [openModal, setOpenModal] = useState(false);
    const [title, setTitle] = useState('');
    const [chainage, setChainage] = useState('Ch 18+400');
    const [direction, setDirection] = useState('northbound');
    const [severity, setSeverity] = useState('minor');
    const [unit, setUnit] = useState('Patrol Unit 1');

    const incidentData = incidents?.data || [
        { id: 1, incident_number: 'INC-2026-001', title: 'Stalled Heavy Truck on Shoulder', chainage: 'Ch 14+200', direction: 'southbound', severity: 'minor', status: 'dispatched', dispatched_unit: 'Patrol Unit 2', response_time_minutes: 12, reported_at: '2026-08-19 14:10:00' },
        { id: 2, incident_number: 'INC-2026-002', title: 'Debris on Main Carriageway', chainage: 'Ch 28+500', direction: 'northbound', severity: 'minor', status: 'on_scene', dispatched_unit: 'Patrol Unit 1', response_time_minutes: 8, reported_at: '2026-08-19 14:45:00' },
        { id: 3, incident_number: 'INC-2026-003', title: 'Overloaded Tipper Vehicle Warning', chainage: 'Ch 39+800', direction: 'southbound', severity: 'major', status: 'detected', dispatched_unit: 'Weighbridge Unit 3', response_time_minutes: 5, reported_at: '2026-08-19 15:20:00' },
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        router.post('/om/incidents', {
            title, chainage, direction, severity, dispatched_unit: unit
        }, {
            onSuccess: () => {
                setOpenModal(false);
                setTitle('');
            }
        });
    };

    return (
        <App auth={auth}>
            <Head title="Incidents & Emergency Patrol" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Incidents & Emergency Patrol Dispatch
                        </Heading>
                        <Text size="2" color="gray">
                            Highway Incident Response SLAs, Motorway Patrol Units & Roadside SOS Dispatch
                        </Text>
                    </Box>
                    <Button color="indigo" onClick={() => setOpenModal(true)}>
                        <PlusIcon width={16} height={16} /> Report & Dispatch Patrol
                    </Button>
                </Flex>

                <Grid columns={{ initial: '1', sm: '3' }} gap="4" mb="5">
                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Active Incidents</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--amber-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.active_incidents || 3}</Heading>
                    </Panel>
                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Cleared Today</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--green-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.cleared_today || 6}</Heading>
                    </Panel>
                    <Panel tinted style={{ padding: '20px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                        <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Avg SLA Response Time</Text>
                        <Heading size="6" mt="1" style={{ color: 'var(--blue-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.avg_response_time || '11.8 mins'}</Heading>
                    </Panel>
                </Grid>

                <Panel p="0" style={{ overflow: 'hidden', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                    <Box p="4" style={{ borderBottom: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                        <Heading size="4" weight="bold">Incident Logbook & Dispatch Status</Heading>
                    </Box>
                    <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
                        <Table.Root size="2" style={{ minWidth: 920, width: '100%' }}>
                            <Table.Header>
                                <Table.Row>
                                    <Table.ColumnHeaderCell style={{ minWidth: 140 }}>Incident #</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 220 }}>Title</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 160 }}>Location</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Severity</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Status</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 150 }}>Dispatched Unit</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 120 }}>Response Time</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {incidentData.map((inc) => (
                                    <Table.Row key={inc.id} align="center">
                                        <Table.Cell style={{ fontFamily: 'monospace', fontWeight: 600 }}>
                                            {inc.incident_number}
                                        </Table.Cell>
                                        <Table.Cell><Text weight="bold" style={{ display: 'block', maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{inc.title}</Text></Table.Cell>
                                        <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{inc.chainage} ({inc.direction})</Text></Table.Cell>
                                        <Table.Cell>
                                            <Badge color={inc.severity === 'critical' ? 'red' : inc.severity === 'major' ? 'amber' : 'blue'} variant="soft" style={{ borderRadius: 999 }}>
                                                {inc.severity}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Badge color={inc.status === 'cleared' ? 'green' : 'orange'} variant="soft" style={{ borderRadius: 999 }}>
                                                {inc.status}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{inc.dispatched_unit}</Text></Table.Cell>
                                        <Table.Cell style={{ textAlign: 'right', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{inc.response_time_minutes} mins</Table.Cell>
                                    </Table.Row>
                                ))}
                            </Table.Body>
                        </Table.Root>
                    </Box>
                </Panel>

                {/* Dispatch Modal */}
                <Dialog.Root open={openModal} onOpenChange={setOpenModal}>
                    <Dialog.Content style={{ maxWidth: 480 }}>
                        <Dialog.Title>Report New Incident & Dispatch Patrol</Dialog.Title>
                        <form onSubmit={handleSubmit}>
                            <Box mb="3">
                                <Text size="2" weight="bold" mb="1" as="div">Incident Description</Text>
                                <TextField.Root value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Broken vehicle blocking right lane" required />
                            </Box>
                            <Box mb="3">
                                <Text size="2" weight="bold" mb="1" as="div">Chainage Location</Text>
                                <TextField.Root value={chainage} onChange={(e) => setChainage(e.target.value)} required />
                            </Box>
                            <Flex gap="3" mb="3">
                                <Box style={{ flex: 1 }}>
                                    <Text size="2" weight="bold" mb="1" as="div">Direction</Text>
                                    <Select.Root value={direction} onValueChange={setDirection}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="northbound">Northbound</Select.Item>
                                            <Select.Item value="southbound">Southbound</Select.Item>
                                            <Select.Item value="both">Both Directions</Select.Item>
                                        </Select.Content>
                                    </Select.Root>
                                </Box>
                                <Box style={{ flex: 1 }}>
                                    <Text size="2" weight="bold" mb="1" as="div">Severity</Text>
                                    <Select.Root value={severity} onValueChange={setSeverity}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="minor">Minor</Select.Item>
                                            <Select.Item value="major">Major</Select.Item>
                                            <Select.Item value="critical">Critical</Select.Item>
                                        </Select.Content>
                                    </Select.Root>
                                </Box>
                            </Flex>
                            <Box mb="4">
                                <Text size="2" weight="bold" mb="1" as="div">Assign Patrol Unit</Text>
                                <TextField.Root value={unit} onChange={(e) => setUnit(e.target.value)} required />
                            </Box>
                            <Flex justify="end" gap="2">
                                <Button type="button" variant="soft" color="gray" onClick={() => setOpenModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" color="indigo">
                                    Dispatch Patrol Unit
                                </Button>
                            </Flex>
                        </form>
                    </Dialog.Content>
                </Dialog.Root>
            </Box>
        </App>
    );
}
