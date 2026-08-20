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

                <Panel style={{ padding: 20 }}>
                    <Heading size="4" mb="3">Hardware Infrastructure Health Matrix</Heading>
                    <Table.Root variant="surface">
                        <Table.Header>
                            <Table.Row>
                                <Table.ColumnHeaderCell>Asset Code</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Equipment Name</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Category</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Location</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Status</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Uptime %</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Last Ping</Table.ColumnHeaderCell>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {eqList.map((eq) => (
                                <Table.Row key={eq.id}>
                                    <Table.RowHeaderCell style={{ fontFamily: 'monospace' }}>
                                        {eq.equipment_code}
                                    </Table.RowHeaderCell>
                                    <Table.TableCell><Text weight="bold">{eq.name}</Text></Table.TableCell>
                                    <Table.TableCell>
                                        <Badge color="indigo">{eq.category.toUpperCase()}</Badge>
                                    </Table.TableCell>
                                    <Table.TableCell>{eq.location}</Table.TableCell>
                                    <Table.TableCell>
                                        <Badge color={eq.status === 'online' ? 'green' : 'red'}>
                                            {eq.status}
                                        </Badge>
                                    </Table.TableCell>
                                    <Table.TableCell>
                                        <Text weight="bold" color="green">{eq.uptime_pct}%</Text>
                                    </Table.TableCell>
                                    <Table.TableCell style={{ color: 'var(--gray-10)' }}>{eq.last_ping_at}</Table.TableCell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table.Root>
                </Panel>
            </Box>
        </App>
    );
}
