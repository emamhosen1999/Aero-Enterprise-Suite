import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Button, Badge, Table, TextField, Dialog } from '@radix-ui/themes';
import {
    ComputerDesktopIcon,
    ExclamationTriangleIcon,
    RadioIcon,
    CheckCircleIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function TrafficMonitoring({ auth, trafficSections, vmsMessages, overloadAlerts }) {
    const [updatingVms, setUpdatingVms] = useState(null);
    const [msg1, setMsg1] = useState('');
    const [msg2, setMsg2] = useState('');
    const [type, setType] = useState('info');

    const sections = trafficSections || [
        { id: 1, section_code: 'CH_0_10', section_name: 'Joydevpur to Bhulta (Ch 0+000 - Ch 10+000)', vehicle_count_per_hour: 1840, avg_speed_kmh: 78.5, density_status: 'free_flow', overspeed_count: 12, overload_count: 1 },
        { id: 2, section_code: 'CH_10_20', section_name: 'Bhulta to Kanchan Bridge (Ch 10+000 - Ch 20+000)', vehicle_count_per_hour: 2420, avg_speed_kmh: 68.2, density_status: 'moderate', overspeed_count: 24, overload_count: 4 },
        { id: 3, section_code: 'CH_20_35', section_name: 'Kanchan Bridge to Debogram (Ch 20+000 - Ch 35+000)', vehicle_count_per_hour: 1950, avg_speed_kmh: 74.0, density_status: 'free_flow', overspeed_count: 8, overload_count: 2 },
        { id: 4, section_code: 'CH_35_48', section_name: 'Debogram to Madanpur N-1 (Ch 35+000 - Ch 48+000)', vehicle_count_per_hour: 2890, avg_speed_kmh: 52.0, density_status: 'congested', overspeed_count: 35, overload_count: 9 },
    ];

    const vmsList = vmsMessages || [
        { id: 1, vms_code: 'VMS-CH05', location: 'Ch 5+200 (Northbound)', message_line1: 'DRIVE SAFELY - SPEED LIMIT 80 KM/H', message_line2: 'ETC LANES OPEN AT TOLL PLAZA', type: 'info', is_active: true },
        { id: 2, vms_code: 'VMS-CH18', location: 'Ch 18+400 (Kanchan Bridge)', message_line1: 'CAUTION: ROADWORK ON RIGHT LANE', message_line2: 'REDUCE SPEED TO 40 KM/H', type: 'warning', is_active: true },
        { id: 3, vms_code: 'VMS-CH36', location: 'Ch 36+100 (Southbound)', message_line1: 'EXPRESSWAY CLEAR TO MADANPUR INTERCHANGE', message_line2: 'HAVE A SAFE JOURNEY', type: 'info', is_active: true },
    ];

    const handleUpdateVms = (e) => {
        e.preventDefault();
        if (!updatingVms) return;
        router.post('/om/vms-messages', {
            id: updatingVms.id,
            message_line1: msg1,
            message_line2: msg2,
            type: type
        }, {
            onSuccess: () => setUpdatingVms(null)
        });
    };

    return (
        <App auth={auth}>
            <Head title="Traffic Monitoring Center (TMC / ITS)" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4" wrap="wrap" gap="3">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Traffic Monitoring Center (TMC / ITS Suite)
                        </Heading>
                        <Text size="2" color="gray">
                            Live Expressway Vehicle Flow, Speed Sensors, VMS Board Broadcast & WIM Overload Detection
                        </Text>
                    </Box>
                    <Button variant="outline" color="gray" onClick={() => router.reload()}>
                        <ArrowPathIcon width={16} height={16} /> Refresh Live Feed
                    </Button>
                </Flex>

                {/* Section Density Matrix */}
                <Heading size="4" mb="3">Expressway Section Flow Rates (Ch 0+000 - Ch 48+000)</Heading>
                <Grid columns={{ initial: '1', sm: '2', md: '4' }} gap="4" mb="5">
                    {sections.map((sec) => (
                        <Panel key={sec.id} tinted style={{ padding: 16 }}>
                            <Flex align="center" justify="between" mb="2">
                                <Badge color={sec.density_status === 'free_flow' ? 'green' : sec.density_status === 'moderate' ? 'amber' : 'red'}>
                                    {sec.density_status.replace('_', ' ').toUpperCase()}
                                </Badge>
                                <Text size="1" color="gray" style={{ fontFamily: 'monospace' }}>{sec.section_code}</Text>
                            </Flex>
                            <Heading size="4" mb="1">{sec.section_name}</Heading>
                            <Flex justify="between" align="baseline" mt="2">
                                <Text size="2" color="gray">Flow: <Text weight="bold" color="blue">{sec.vehicle_count_per_hour} veh/h</Text></Text>
                                <Text size="2" color="gray">Speed: <Text weight="bold">{sec.avg_speed_kmh} km/h</Text></Text>
                            </Flex>
                            <Flex justify="between" mt="2" style={{ borderTop: '1px solid var(--gray-a4)', paddingTop: 8 }}>
                                <Text size="1" color="gray">Overspeed: <Text color="red" weight="bold">{sec.overspeed_count}</Text></Text>
                                <Text size="1" color="gray">Overload WIM: <Text color="amber" weight="bold">{sec.overload_count}</Text></Text>
                            </Flex>
                        </Panel>
                    ))}
                </Grid>

                {/* VMS Live Control & Message Broadcast */}
                <Panel style={{ padding: 20, marginBottom: 24 }}>
                    <Flex align="center" justify="between" mb="3">
                        <Box>
                            <Heading size="4">Variable Message Signs (VMS) Live Controller</Heading>
                            <Text size="1" color="gray">Broadcast driver alerts and speed advisories to dynamic LED boards</Text>
                        </Box>
                    </Flex>
                    <Table.Root variant="surface">
                        <Table.Header>
                            <Table.Row>
                                <Table.ColumnHeaderCell>Board Code</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Location</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Active Display Line 1</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Display Line 2</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Type</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Action</Table.ColumnHeaderCell>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {vmsList.map((board) => (
                                <Table.Row key={board.id}>
                                    <Table.RowHeaderCell style={{ fontFamily: 'monospace' }}>
                                        {board.vms_code}
                                    </Table.RowHeaderCell>
                                    <Table.TableCell>{board.location}</Table.TableCell>
                                    <Table.TableCell>
                                        <Text weight="bold" color="blue">{board.message_line1}</Text>
                                    </Table.TableCell>
                                    <Table.TableCell>
                                        <Text color="gray">{board.message_line2 || '—'}</Text>
                                    </Table.TableCell>
                                    <Table.TableCell>
                                        <Badge color={board.type === 'emergency' ? 'red' : board.type === 'warning' ? 'amber' : 'blue'}>
                                            {board.type}
                                        </Badge>
                                    </Table.TableCell>
                                    <Table.TableCell>
                                        <Button size="1" variant="soft" onClick={() => {
                                            setUpdatingVms(board);
                                            setMsg1(board.message_line1);
                                            setMsg2(board.message_line2 || '');
                                            setType(board.type);
                                        }}>
                                            Edit Broadcast
                                        </Button>
                                    </Table.TableCell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table.Root>
                </Panel>

                {/* Edit VMS Modal */}
                {updatingVms && (
                    <Dialog.Root open={!!updatingVms} onOpenChange={() => setUpdatingVms(null)}>
                        <Dialog.Content style={{ maxWidth: 450 }}>
                            <Dialog.Title>Broadcast VMS Alert ({updatingVms.vms_code})</Dialog.Title>
                            <form onSubmit={handleUpdateVms}>
                                <Box mb="3">
                                    <Text size="2" weight="bold" mb="1" as="div">Display Line 1</Text>
                                    <TextField.Root value={msg1} onChange={(e) => setMsg1(e.target.value)} required />
                                </Box>
                                <Box mb="3">
                                    <Text size="2" weight="bold" mb="1" as="div">Display Line 2 (Optional)</Text>
                                    <TextField.Root value={msg2} onChange={(e) => setMsg2(e.target.value)} />
                                </Box>
                                <Flex justify="end" gap="2" mt="4">
                                    <Button type="button" variant="soft" color="gray" onClick={() => setUpdatingVms(null)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" color="blue">
                                        Update VMS Broadcast
                                    </Button>
                                </Flex>
                            </form>
                        </Dialog.Content>
                    </Dialog.Root>
                )}
            </Box>
        </App>
    );
}
