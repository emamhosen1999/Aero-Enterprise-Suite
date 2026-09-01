import React from 'react';
import { Head } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Badge, Table } from '@radix-ui/themes';
import { ComputerDesktopIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function EquipmentFacilities({ auth, equipment }) {
    const eqList = equipment || [
        { id: 1, equipment_code: 'CCTV-CH00', name: 'High Definition PTZ Surveillance Camera', category: 'cctv', location: 'Ch 0+000 Interchange', status: 'online', uptime_pct: 99.90, last_ping_at: '2026-08-19 15:32:00' },
        { id: 2, equipment_code: 'VMS-CH18', name: 'Variable Message Board Matrix', category: 'vms', location: 'Ch 18+400 Kanchan Bridge', status: 'online', uptime_pct: 99.80, last_ping_at: '2026-08-19 15:32:00' },
        { id: 3, equipment_code: 'WIM-PLAZA01', name: 'High-Speed Weigh-in-Motion Scale', category: 'wim', location: 'Main Toll Plaza Entry', status: 'online', uptime_pct: 99.50, last_ping_at: '2026-08-19 15:31:45' },
        { id: 4, equipment_code: 'GEN-PLAZA-MAIN', name: '500kVA Diesel Generator Backup System', category: 'generator', location: 'Toll Plaza Central Power Substation', status: 'online', uptime_pct: 100.00, last_ping_at: '2026-08-19 15:30:00' },
    ];

    return (
        <App auth={auth}>
            <Head title="Equipment & Facilities Status" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Expressway Equipment & Hardware Asset Health
                        </Heading>
                        <Text size="2" color="gray">
                            Live Uptime Monitoring: CCTV Cameras, VMS Display Panels, WIM Scales & Power Generators
                        </Text>
                    </Box>
                </Flex>

                <Panel p="0" style={{ overflow: 'hidden', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                    <Box p="4" style={{ borderBottom: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))' }}>
                        <Heading size="4" weight="bold">Hardware Infrastructure Health Matrix</Heading>
                    </Box>
                    <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
                        <Table.Root size="2" style={{ minWidth: 880, width: '100%' }}>
                            <Table.Header>
                                <Table.Row>
                                    <Table.ColumnHeaderCell style={{ minWidth: 130 }}>Asset Code</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 220 }}>Equipment Name</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Category</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 160 }}>Location</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Status</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ minWidth: 100 }}>Uptime %</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 150 }}>Last Ping</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {eqList.map((eq) => (
                                    <Table.Row key={eq.id} align="center">
                                        <Table.Cell style={{ fontFamily: 'monospace', fontWeight: 600 }}>
                                            {eq.equipment_code}
                                        </Table.Cell>
                                        <Table.Cell><Text weight="bold" style={{ display: 'block', maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{eq.name}</Text></Table.Cell>
                                        <Table.Cell>
                                            <Badge color="indigo" variant="soft" style={{ borderRadius: 999 }}>{eq.category.toUpperCase()}</Badge>
                                        </Table.Cell>
                                        <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{eq.location}</Text></Table.Cell>
                                        <Table.Cell>
                                            <Badge color={eq.status === 'online' ? 'green' : 'red'} variant="soft" style={{ borderRadius: 999 }}>
                                                {eq.status}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Text weight="bold" color="green" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{eq.uptime_pct}%</Text>
                                        </Table.Cell>
                                        <Table.Cell style={{ textAlign: 'right', color: 'var(--gray-10)', fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' }}>{eq.last_ping_at}</Table.Cell>
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
