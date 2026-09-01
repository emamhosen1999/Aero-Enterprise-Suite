import React from 'react';
import { Head, router } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Button, Badge, Table, Card, Dialog } from '@radix-ui/themes';
import {
    WrenchScrewdriverIcon,
    ShieldCheckIcon,
    CurrencyDollarIcon,
    ComputerDesktopIcon,
    TruckIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    CheckCircleIcon,
    ClockIcon
} from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function OmDashboard({ auth, stats, recentIncidents, trafficSections, recentWorkOrders, vmsBoards }) {
    const defaultStats = stats || {
        today_toll_revenue: 485200.00,
        etc_vehicle_ratio: 78.4,
        active_incidents_count: 3,
        open_work_orders_count: 7,
        equipment_uptime_pct: 99.8,
        avg_patrol_response_min: 12.5,
    };

    const incidentsList = recentIncidents || [
        { id: 1, incident_number: 'INC-2026-001', title: 'Stalled Heavy Truck on Shoulder', chainage: 'Ch 14+200 SB', severity: 'minor', status: 'dispatched', dispatched_unit: 'Patrol Unit 2', reported_at: '10 mins ago' },
        { id: 2, incident_number: 'INC-2026-002', title: 'Debris on Main Carriageway', chainage: 'Ch 28+500 NB', severity: 'minor', status: 'on_scene', dispatched_unit: 'Patrol Unit 1', reported_at: '35 mins ago' },
        { id: 3, incident_number: 'INC-2026-003', title: 'Overloaded Tipper Vehicle Warning', chainage: 'Ch 39+800 SB', severity: 'major', status: 'detected', dispatched_unit: 'Weighbridge Unit 3', reported_at: '1 hour ago' },
    ];

    const workOrdersList = recentWorkOrders || [
        { id: 1, work_order_number: 'WO-90124', title: 'Guardrail Repair & Reflector Replacement', category: 'pavement', location: 'Ch 12+400', priority: 'medium', status: 'in_progress', assigned_to: 'Roadside Crew B' },
        { id: 2, work_order_number: 'WO-90125', title: 'Toll Plaza Lane 4 ETC Reader Calibration', category: 'lighting', location: 'Main Toll Plaza', priority: 'high', status: 'assigned', assigned_to: 'ITS Tech Team' },
        { id: 3, work_order_number: 'WO-90126', title: 'Expansion Joint Sealing at Kanchan Bridge', category: 'bridge', location: 'Ch 18+270', priority: 'high', status: 'pending', assigned_to: 'Bridge Maintenance Team' },
    ];

    return (
        <App auth={auth}>
            <Head title="O&M Overview — Operations & Maintenance" />
            <Flex justify="center" p={{ initial: '3', sm: '4', md: '5' }}>
                <Box style={{ width: '100%', maxWidth: 2000 }}>
                    <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '24px 20px' }}>
                        {/* ── Page Header ── */}
                        <Box mb="4">
                            <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                <Flex align="center" gap="3">
                                    <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <TruckIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                    </Box>
                                    <Box>
                                        <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Expressway Operations & Maintenance Command Center</Heading>
                                        <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>Dhaka Bypass Expressway (N-105) PPP · Live Toll, Traffic, Patrol & Maintenance Overview</Text>
                                    </Box>
                                </Flex>
                                <Flex gap="2" wrap="wrap">
                                    <Button color="blue" variant="soft" onClick={() => router.visit('/om/traffic-monitoring')} style={{ borderRadius: 10 }}>
                                        <ComputerDesktopIcon width={16} height={16} /> Traffic Control
                                    </Button>
                                    <Button color="indigo" onClick={() => router.visit('/om/incidents')} style={{ borderRadius: 10 }}>
                                        <ShieldCheckIcon width={16} height={16} /> Patrol Dispatch
                                    </Button>
                                </Flex>
                            </Flex>
                        </Box>

                        <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                        {/* Top KPI Cards */}
                        <Grid columns={{ initial: '1', sm: '2', md: '4' }} gap="3" mb="4">
                            <Panel tinted style={{ padding: '18px 16px', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                                <Flex align="center" justify="between" mb="2">
                                    <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11, letterSpacing: '0.05em', textTransform: 'uppercase' }}>
                                        Today's Toll Revenue
                                    </Text>
                                    <CurrencyDollarIcon width={20} height={20} style={{ color: 'var(--green-9)' }} />
                                </Flex>
                                <Heading size="6" style={{ color: 'var(--green-11)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>
                                    ৳ {Number(defaultStats.today_toll_revenue).toLocaleString()}
                                </Heading>
                                <Text size="1" color="gray" mt="1">
                                    ETC Ratio: <Text weight="bold" color="green">{defaultStats.etc_vehicle_ratio}%</Text>
                                </Text>
                            </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Flex align="center" justify="between" mb="2">
                            <Text size="1" weight="bold" color="gray" style={{ textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                                Active Incidents
                            </Text>
                            <ExclamationTriangleIcon width={20} height={20} style={{ color: 'var(--amber-9)' }} />
                        </Flex>
                        <Heading size="6" style={{ color: 'var(--amber-11)' }}>
                            {defaultStats.active_incidents_count}
                        </Heading>
                        <Text size="1" color="gray" mt="1">
                            Avg Patrol Response: <Text weight="bold">{defaultStats.avg_patrol_response_min} mins</Text>
                        </Text>
                    </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Flex align="center" justify="between" mb="2">
                            <Text size="1" weight="bold" color="gray" style={{ textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                                Ongoing Maintenance
                            </Text>
                            <WrenchScrewdriverIcon width={20} height={20} style={{ color: 'var(--blue-9)' }} />
                        </Flex>
                        <Heading size="6" style={{ color: 'var(--blue-11)' }}>
                            {defaultStats.open_work_orders_count} Work Orders
                        </Heading>
                        <Text size="1" color="gray" mt="1">
                            Pavement, Lighting & Bridges
                        </Text>
                    </Panel>

                    <Panel tinted style={{ padding: 18 }}>
                        <Flex align="center" justify="between" mb="2">
                            <Text size="1" weight="bold" color="gray" style={{ textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                                Equipment Uptime
                            </Text>
                            <ComputerDesktopIcon width={20} height={20} style={{ color: 'var(--indigo-9)' }} />
                        </Flex>
                        <Heading size="6" style={{ color: 'var(--indigo-11)' }}>
                            {defaultStats.equipment_uptime_pct}%
                        </Heading>
                        <Text size="1" color="gray" mt="1">
                            CCTV, VMS & WIM Sensors Online
                        </Text>
                    </Panel>
                </Grid>

                {/* Main Content Grid */}
                <Grid columns={{ initial: '1', md: '2' }} gap="5">
                    {/* Active Incidents & Emergency Patrol */}
                    <Panel style={{ padding: 20 }}>
                        <Flex align="center" justify="between" mb="3">
                            <Box>
                                <Heading size="3">Active Incidents & Patrol Dispatch</Heading>
                                <Text size="1" color="gray">Live emergency responses and motorway safety</Text>
                            </Box>
                            <Button size="1" variant="outline" onClick={() => router.visit('/om/incidents')}>
                                View All
                            </Button>
                        </Flex>
                        <Table.Root variant="surface">
                            <Table.Header>
                                <Table.Row>
                                    <Table.ColumnHeaderCell>Incident #</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Location</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Severity</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Dispatch Unit</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {incidentsList.map((inc) => (
                                    <Table.Row key={inc.id}>
                                        <Table.RowHeaderCell>
                                            <Text weight="bold">{inc.incident_number}</Text>
                                            <Text size="1" color="gray" as="div">{inc.title}</Text>
                                        </Table.RowHeaderCell>
                                        <Table.Cell>{inc.chainage}</Table.Cell>
                                        <Table.Cell>
                                            <Badge color={inc.severity === 'critical' ? 'red' : inc.severity === 'major' ? 'amber' : 'blue'}>
                                                {inc.severity}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Text size="2">{inc.dispatched_unit}</Text>
                                        </Table.Cell>
                                    </Table.Row>
                                ))}
                            </Table.Body>
                        </Table.Root>
                    </Panel>

                    {/* Maintenance Work Orders */}
                    <Panel style={{ padding: 20 }}>
                        <Flex align="center" justify="between" mb="3">
                            <Box>
                                <Heading size="3">Routine & Preventive Maintenance</Heading>
                                <Text size="1" color="gray">Active infrastructure work orders</Text>
                            </Box>
                            <Button size="1" variant="outline" onClick={() => router.visit('/om/work-orders')}>
                                View All
                            </Button>
                        </Flex>
                        <Table.Root variant="surface">
                            <Table.Header>
                                <Table.Row>
                                    <Table.ColumnHeaderCell>WO #</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Title</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Priority</Table.ColumnHeaderCell>
                                    <Table.ColumnHeaderCell>Assigned Crew</Table.ColumnHeaderCell>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {workOrdersList.map((wo) => (
                                    <Table.Row key={wo.id}>
                                        <Table.RowHeaderCell>
                                            <Text weight="bold">{wo.work_order_number}</Text>
                                        </Table.RowHeaderCell>
                                        <Table.Cell>
                                            <Text size="2">{wo.title}</Text>
                                            <Text size="1" color="gray" as="div">{wo.location}</Text>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Badge color={wo.priority === 'high' ? 'red' : 'orange'}>
                                                {wo.priority}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell>
                                            <Text size="2">{wo.assigned_to}</Text>
                                        </Table.Cell>
                                    </Table.Row>
                                ))}
                            </Table.Body>
                        </Table.Root>
                    </Panel>
                </Grid>
                    </Panel>
                </Box>
            </Flex>
        </App>
    );
}
