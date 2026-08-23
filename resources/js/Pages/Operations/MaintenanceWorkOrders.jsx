import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Button, Badge, Table, TextField, Dialog, Select } from '@radix-ui/themes';
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
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                <Flex align="center" justify="between" mb="4">
                    <Box>
                        <Heading size="6" weight="bold" style={{ letterSpacing: '-0.02em' }}>
                            Routine & Preventive Maintenance Work Orders
                        </Heading>
                        <Text size="2" color="gray">
                            Highway Infrastructure Care: Pavement, Guardrails, Bridge Joints, Lighting & Signage
                        </Text>
                    </Box>
                    <Button color="blue" onClick={() => setOpenModal(true)}>
                        <PlusIcon width={16} height={16} /> Create Work Order
                    </Button>
                </Flex>

                <Panel style={{ padding: 20 }}>
                    <Heading size="4" mb="3">Active Maintenance Register</Heading>
                    <Table.Root variant="surface">
                        <Table.Header>
                            <Table.Row>
                                <Table.ColumnHeaderCell>WO #</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Title</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Category</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Location</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Priority</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Status</Table.ColumnHeaderCell>
                                <Table.ColumnHeaderCell>Assigned Crew</Table.ColumnHeaderCell>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {woData.map((wo) => (
                                <Table.Row key={wo.id}>
                                    <Table.RowHeaderCell style={{ fontFamily: 'monospace' }}>
                                        {wo.work_order_number}
                                    </Table.RowHeaderCell>
                                    <Table.Cell><Text weight="bold">{wo.title}</Text></Table.Cell>
                                    <Table.Cell>
                                        <Badge color="blue">{wo.category}</Badge>
                                    </Table.Cell>
                                    <Table.Cell>{wo.location}</Table.Cell>
                                    <Table.Cell>
                                        <Badge color={wo.priority === 'high' ? 'red' : 'orange'}>
                                            {wo.priority}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Badge color={wo.status === 'completed' ? 'green' : 'amber'}>
                                            {wo.status}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell>{wo.assigned_to}</Table.Cell>
                                </Table.Row>
                            ))}
                        </Table.Body>
                    </Table.Root>
                </Panel>

                {/* Create Work Order Modal */}
                <Dialog.Root open={openModal} onOpenChange={setOpenModal}>
                    <Dialog.Content style={{ maxWidth: 480 }}>
                        <Dialog.Title>Create Maintenance Work Order</Dialog.Title>
                        <form onSubmit={handleSubmit}>
                            <Box mb="3">
                                <Text size="2" weight="bold" mb="1" as="div">Work Order Title</Text>
                                <TextField.Root value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Guardrail repair near Kanchan Bridge" required />
                            </Box>
                            <Flex gap="3" mb="3">
                                <Box style={{ flex: 1 }}>
                                    <Text size="2" weight="bold" mb="1" as="div">Category</Text>
                                    <Select.Root value={category} onValueChange={setCategory}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="pavement">Pavement</Select.Item>
                                            <Select.Item value="guardrail">Guardrail</Select.Item>
                                            <Select.Item value="lighting">Lighting & ITS</Select.Item>
                                            <Select.Item value="drainage">Drainage</Select.Item>
                                            <Select.Item value="bridge">Bridge / Structure</Select.Item>
                                            <Select.Item value="signage">Signage & Marking</Select.Item>
                                        </Select.Content>
                                    </Select.Root>
                                </Box>
                                <Box style={{ flex: 1 }}>
                                    <Text size="2" weight="bold" mb="1" as="div">Priority</Text>
                                    <Select.Root value={priority} onValueChange={setPriority}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="low">Low</Select.Item>
                                            <Select.Item value="medium">Medium</Select.Item>
                                            <Select.Item value="high">High</Select.Item>
                                            <Select.Item value="emergency">Emergency</Select.Item>
                                        </Select.Content>
                                    </Select.Root>
                                </Box>
                            </Flex>
                            <Box mb="3">
                                <Text size="2" weight="bold" mb="1" as="div">Location / Chainage</Text>
                                <TextField.Root value={location} onChange={(e) => setLocation(e.target.value)} required />
                            </Box>
                            <Box mb="4">
                                <Text size="2" weight="bold" mb="1" as="div">Assigned Crew / Contractor</Text>
                                <TextField.Root value={assignedTo} onChange={(e) => setAssignedTo(e.target.value)} required />
                            </Box>
                            <Flex justify="end" gap="2">
                                <Button type="button" variant="soft" color="gray" onClick={() => setOpenModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" color="blue">
                                    Issue Work Order
                                </Button>
                            </Flex>
                        </form>
                    </Dialog.Content>
                </Dialog.Root>
            </Box>
        </App>
    );
}
