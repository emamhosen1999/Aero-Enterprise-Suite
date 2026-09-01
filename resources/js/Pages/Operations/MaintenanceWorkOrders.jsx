import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Button, Badge, Table, TextField, Dialog, Select, Separator } from '@radix-ui/themes';
import { WrenchScrewdriverIcon, PlusIcon } from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import { Panel } from '@/Components/ui/Panel';

export default function MaintenanceWorkOrders({ auth, workOrders }) {
    const [openModal, setOpenModal] = useState(false);
    const [title, setTitle] = useState('');
    const [category, setCategory] = useState('pavement');
    const [location, setLocation] = useState('Ch 12+400');
    const [priority, setPriority] = useState('medium');
    const [assignedTo, setAssignedTo] = useState('Roadside Crew A');

    const woData = workOrders?.data || [
        { id: 1, work_order_number: 'WO-90124', title: 'Guardrail Repair & Reflector Replacement', category: 'pavement', location: 'Ch 12+400 - Ch 13+100', priority: 'medium', status: 'in_progress', assigned_to: 'Roadside Crew B' },
        { id: 2, work_order_number: 'WO-90125', title: 'Toll Plaza Lane 4 ETC Reader Calibration', category: 'lighting', location: 'Main Toll Plaza', priority: 'high', status: 'assigned', assigned_to: 'ITS Tech Team' },
        { id: 3, work_order_number: 'WO-90126', title: 'Expansion Joint Sealing at Kanchan Bridge', category: 'bridge', location: 'Ch 18+270', priority: 'high', status: 'pending', assigned_to: 'Bridge Maintenance Team' },
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        router.post('/om/work-orders', {
            title, category, location, priority, assigned_to: assignedTo
        }, {
            onSuccess: () => {
                setOpenModal(false);
                setTitle('');
            }
        });
    };

    return (
        <App auth={auth}>
            <Head title="Maintenance Work Orders" />
            <Flex justify="center" p="4">
                <Box style={{ width: '100%', maxWidth: 2000 }}>
                    <Panel>
                        {/* ── Page Header ── */}
                        <Box mb="4">
                            <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                <Flex align="center" gap="3">
                                    <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <WrenchScrewdriverIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                    </Box>
                                    <Box>
                                        <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Routine & Preventive Maintenance Work Orders</Heading>
                                        <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>Highway Infrastructure Care: Pavement, Guardrails, Bridge Joints, Lighting & Signage</Text>
                                    </Box>
                                </Flex>
                                <Button color="blue" onClick={() => setOpenModal(true)} style={{ borderRadius: 12, fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 600 }}>
                                    <PlusIcon width={16} height={16} /> Create Work Order
                                </Button>
                            </Flex>
                        </Box>

                        <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                        <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                            <Table.Root size="2" style={{ minWidth: 880, width: '100%' }}>
                                <Table.Header style={{
                                    position: 'sticky',
                                    top: 0,
                                    zIndex: 2,
                                    background: 'var(--aero-surface, var(--color-background))',
                                    backdropFilter: 'blur(8px)',
                                    boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))'
                                }}>
                                    <Table.Row>
                                        <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}>WO #</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 240, background: 'inherit' }}>Title</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}>Category</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 150, background: 'inherit' }}>Location</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 100, background: 'inherit' }}>Priority</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}>Status</Table.ColumnHeaderCell>
                                        <Table.ColumnHeaderCell style={{ textAlign: 'right', minWidth: 150, background: 'inherit' }}>Assigned Crew</Table.ColumnHeaderCell>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {woData.map((wo) => (
                                        <Table.Row key={wo.id} align="center">
                                            <Table.Cell style={{ fontFamily: 'monospace', fontWeight: 600 }}>
                                                {wo.work_order_number}
                                            </Table.Cell>
                                            <Table.Cell><Text weight="bold" style={{ display: 'block', maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{wo.title}</Text></Table.Cell>
                                            <Table.Cell>
                                                <Badge color="blue" variant="soft" style={{ borderRadius: 999 }}>{wo.category}</Badge>
                                            </Table.Cell>
                                            <Table.Cell><Text size="2" style={{ whiteSpace: 'nowrap' }}>{wo.location}</Text></Table.Cell>
                                            <Table.Cell>
                                                <Badge color={wo.priority === 'high' ? 'red' : wo.priority === 'medium' ? 'orange' : 'gray'} variant="soft" style={{ borderRadius: 999 }}>
                                                    {wo.priority}
                                                </Badge>
                                            </Table.Cell>
                                            <Table.Cell>
                                                <Badge color={wo.status === 'completed' ? 'green' : wo.status === 'in_progress' ? 'blue' : 'amber'} variant="soft" style={{ borderRadius: 999 }}>
                                                    {wo.status}
                                                </Badge>
                                            </Table.Cell>
                                            <Table.Cell style={{ textAlign: 'right' }}>
                                                <Text size="2" style={{ whiteSpace: 'nowrap' }}>{wo.assigned_to}</Text>
                                            </Table.Cell>
                                        </Table.Row>
                                    ))}
                                </Table.Body>
                            </Table.Root>
                        </Box>

                        {/* Create Modal */}
                        <Dialog.Root open={openModal} onOpenChange={setOpenModal}>
                            <Dialog.Content style={{ maxWidth: 480 }}>
                                <Dialog.Title>Create Maintenance Work Order</Dialog.Title>
                                <Dialog.Description size="2" mb="4">
                                    Issue a new routine, corrective, or emergency highway maintenance ticket.
                                </Dialog.Description>
                                <form onSubmit={handleSubmit}>
                                    <Flex direction="column" gap="3">
                                        <label>
                                            <Text as="div" size="2" mb="1" weight="bold">Title / Task</Text>
                                            <TextField.Root placeholder="e.g. Guardrail Replacement at Kanchan" value={title} onChange={(e) => setTitle(e.target.value)} required />
                                        </label>
                                        <Grid columns="2" gap="3">
                                            <label>
                                                <Text as="div" size="2" mb="1" weight="bold">Category</Text>
                                                <Select.Root value={category} onValueChange={setCategory}>
                                                    <Select.Trigger style={{ width: '100%' }} />
                                                    <Select.Content>
                                                        <Select.Item value="pavement">Pavement & Surfacing</Select.Item>
                                                        <Select.Item value="bridge">Bridge & Culverts</Select.Item>
                                                        <Select.Item value="lighting">Lighting & Electrification</Select.Item>
                                                        <Select.Item value="drainage">Drainage & Slope</Select.Item>
                                                        <Select.Item value="signage">Traffic Signs & VMS</Select.Item>
                                                    </Select.Content>
                                                </Select.Root>
                                            </label>
                                            <label>
                                                <Text as="div" size="2" mb="1" weight="bold">Priority</Text>
                                                <Select.Root value={priority} onValueChange={setPriority}>
                                                    <Select.Trigger style={{ width: '100%' }} />
                                                    <Select.Content>
                                                        <Select.Item value="low">Low</Select.Item>
                                                        <Select.Item value="medium">Medium</Select.Item>
                                                        <Select.Item value="high">High Emergency</Select.Item>
                                                    </Select.Content>
                                                </Select.Root>
                                            </label>
                                        </Grid>
                                        <label>
                                            <Text as="div" size="2" mb="1" weight="bold">Location (Chainage)</Text>
                                            <TextField.Root placeholder="e.g. Ch 12+400 Northbound" value={location} onChange={(e) => setLocation(e.target.value)} required />
                                        </label>
                                        <label>
                                            <Text as="div" size="2" mb="1" weight="bold">Assigned Maintenance Crew</Text>
                                            <TextField.Root placeholder="e.g. Roadside Civil Crew B" value={assignedTo} onChange={(e) => setAssignedTo(e.target.value)} required />
                                        </label>
                                        <Flex gap="3" mt="4" justify="end">
                                            <Dialog.Close>
                                                <Button variant="soft" color="gray">Cancel</Button>
                                            </Dialog.Close>
                                            <Button type="submit" color="blue">Issue Ticket</Button>
                                        </Flex>
                                    </Flex>
                                </form>
                            </Dialog.Content>
                        </Dialog.Root>
                    </Panel>
                </Box>
            </Flex>
        </App>
    );
}
