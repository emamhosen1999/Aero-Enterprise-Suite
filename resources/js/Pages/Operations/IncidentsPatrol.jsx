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
            <Flex justify="center" p="4">
                <Box style={{ width: '100%', maxWidth: 2000 }}>
                    <Panel>
                        {/* ── Page Header ── */}
                        <Box mb="4">
                            <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                <Flex align="center" gap="3">
                                    <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <ShieldCheckIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                    </Box>
                                    <Box>
                                        <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Incidents & Emergency Patrol Dispatch</Heading>
                                        <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>Highway Incident Response SLAs, Motorway Patrol Units & Roadside SOS Dispatch</Text>
                                    </Box>
                                </Flex>
                                <Button color="blue" onClick={() => setOpenModal(true)} style={{ borderRadius: 12, fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 600 }}>
                                    <PlusIcon width={16} height={16} /> Report & Dispatch Patrol
                                </Button>
                            </Flex>
                        </Box>

                        <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                        <Grid columns={{ initial: '1', sm: '3' }} gap="3" mb="4">
                            <Panel tinted style={{ padding: '18px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                                <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Active Incidents</Text>
                                <Heading size="6" mt="1" style={{ color: 'var(--amber-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.active_incidents || 3}</Heading>
                            </Panel>
                            <Panel tinted style={{ padding: '18px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                                <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Cleared Today</Text>
                                <Heading size="6" mt="1" style={{ color: 'var(--green-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.cleared_today || 6}</Heading>
                            </Panel>
                            <Panel tinted style={{ padding: '18px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                                <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>Avg SLA Response Time</Text>
                                <Heading size="6" mt="1" style={{ color: 'var(--blue-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{metrics?.avg_response_time || '11.8 mins'}</Heading>
                            </Panel>
                        </Grid>

                        <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                            <Table.Root size="2" style={{ minWidth: 920, width: '100%' }}>
                                <Table.Header style={{
                                    position: 'sticky',
                                    top: 0,
                                    zIndex: 2,
                                    background: 'var(--aero-surface, var(--color-background))',
                                    backdropFilter: 'blur(8px)',
                                    boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))'
                                }}>
                                    <Table.Row>
                                        <Table.ColumnHeaderCell style={{ minWidth: 140, background: 'inherit' }}>Incident #</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 220, background: 'inherit' }}>Title</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 160, background: 'inherit' }}>Location</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}>Severity</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}>Status</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 150, background: 'inherit' }}>Dispatched Unit</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 120, background: 'inherit' }}>Response Time</Table.ColumnHeaderCell>
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
                </Box>
            </Flex>

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
                            <Button type="submit" color="blue">
                                Dispatch Patrol Unit
                            </Button>
                        </Flex>
                    </form>
                </Dialog.Content>
            </Dialog.Root>
        </App>
    );
}
