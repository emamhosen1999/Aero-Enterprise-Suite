import React, { useState, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Badge, Box, Button, Card, Flex, Grid, Heading, ScrollArea, Table, Tabs, Text, TextField } from '@radix-ui/themes';
import {
    ShieldCheckIcon,
    KeyIcon,
    UserGroupIcon,
    MagnifyingGlassIcon,
    LockClosedIcon,
} from '@heroicons/react/24/outline';
import App from '@/Layouts/App.jsx';
import PageHeader from '@/Components/PageHeader';
import { Panel } from '@/Components/ui/Panel';

const RoleManagement = ({ title, roles = [], permissions = [], permissionsGrouped = {}, role_has_permissions = [], enterprise_modules = [] }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedRole, setSelectedRole] = useState(roles[0]?.id || null);
    const [selectedTab, setSelectedTab] = useState('roles');

    const rolePermissionsMap = useMemo(() => {
        const map = new Map();
        role_has_permissions.forEach((rel) => {
            if (!map.has(rel.role_id)) {
                map.set(rel.role_id, new Set());
            }
            map.get(rel.role_id).add(rel.permission_id);
        });
        return map;
    }, [role_has_permissions]);

    const activeRole = useMemo(() => {
        return roles.find((r) => r.id === selectedRole) || roles[0];
    }, [roles, selectedRole]);

    const filteredPermissions = useMemo(() => {
        if (!searchTerm) return permissions;
        return permissions.filter((p) =>
            p.name.toLowerCase().includes(searchTerm.toLowerCase())
        );
    }, [permissions, searchTerm]);

    return (
        <App>
            <Head title={title || 'Enterprise Role Management'} />
            <Box p={{ initial: '3', md: '6' }}>
                <PageHeader
                    title={title || 'Enterprise Role & Permission Management'}
                    subtitle="View and manage system access roles, security scopes, and enterprise permissions"
                />

                <Tabs.Root value={selectedTab} onValueChange={setSelectedTab} style={{ marginTop: '16px' }}>
                    <Tabs.List>
                        <Tabs.Trigger value="roles">
                            <Flex align="center" gap="2">
                                <ShieldCheckIcon style={{ width: 16, height: 16 }} />
                                Roles & Access ({roles.length})
                            </Flex>
                        </Tabs.Trigger>
                        <Tabs.Trigger value="permissions">
                            <Flex align="center" gap="2">
                                <KeyIcon style={{ width: 16, height: 16 }} />
                                All Permissions ({permissions.length})
                            </Flex>
                        </Tabs.Trigger>
                        <Tabs.Trigger value="modules">
                            <Flex align="center" gap="2">
                                <UserGroupIcon style={{ width: 16, height: 16 }} />
                                Module Matrix
                            </Flex>
                        </Tabs.Trigger>
                    </Tabs.List>

                    <Box pt="4">
                        <Tabs.Content value="roles">
                            <Grid columns={{ initial: '1', md: '3' }} gap="4">
                                <Box>
                                    <Heading size="3" mb="3">System Roles</Heading>
                                    <Flex direction="column" gap="2">
                                        {roles.map((role) => {
                                            const permCount = rolePermissionsMap.get(role.id)?.size || 0;
                                            const isSelected = selectedRole === role.id;
                                            return (
                                                <Card
                                                    key={role.id}
                                                    style={{
                                                        cursor: 'pointer',
                                                        border: isSelected ? '2px solid var(--accent-9)' : undefined,
                                                        backgroundColor: isSelected ? 'var(--accent-2)' : undefined,
                                                    }}
                                                    onClick={() => setSelectedRole(role.id)}
                                                >
                                                    <Flex justify="between" align="center">
                                                        <Box>
                                                            <Text weight="bold" size="3">{role.name}</Text>
                                                            <Text as="div" size="1" color="gray">Guard: {role.guard_name || 'web'}</Text>
                                                        </Box>
                                                        <Badge color={isSelected ? 'indigo' : 'gray'}>
                                                            {permCount} Permissions
                                                        </Badge>
                                                    </Flex>
                                                </Card>
                                            );
                                        })}
                                    </Flex>
                                </Box>

                                <Box style={{ gridColumn: 'span 2' }}>
                                    {activeRole && (
                                        <Panel>
                                            <Box p="4">
                                                <Flex justify="between" align="center" mb="4">
                                                    <Box>
                                                        <Heading size="4">{activeRole.name}</Heading>
                                                        <Text size="2" color="gray">
                                                            Active security role with {rolePermissionsMap.get(activeRole.id)?.size || 0} granted capabilities.
                                                        </Text>
                                                    </Box>
                                                    <Badge color="green" size="2">Active Guard: {activeRole.guard_name}</Badge>
                                                </Flex>

                                                <TextField.Root
                                                    placeholder="Filter granted permissions..."
                                                    value={searchTerm}
                                                    onChange={(e) => setSearchTerm(e.target.value)}
                                                    mb="4"
                                                >
                                                    <TextField.Slot>
                                                        <MagnifyingGlassIcon style={{ width: 16, height: 16 }} />
                                                    </TextField.Slot>
                                                </TextField.Root>

                                                <ScrollArea style={{ maxHeight: 500 }}>
                                                    <Flex wrap="wrap" gap="2">
                                                        {permissions
                                                            .filter((p) => rolePermissionsMap.get(activeRole.id)?.has(p.id))
                                                            .filter((p) => !searchTerm || p.name.toLowerCase().includes(searchTerm.toLowerCase()))
                                                            .map((perm) => (
                                                                <Badge key={perm.id} color="indigo" variant="soft" size="2">
                                                                    <LockClosedIcon style={{ width: 12, height: 12, marginRight: 4 }} />
                                                                    {perm.name}
                                                                </Badge>
                                                            ))}
                                                    </Flex>
                                                </ScrollArea>
                                            </Box>
                                        </Panel>
                                    )}
                                </Box>
                            </Grid>
                        </Tabs.Content>

                        <Tabs.Content value="permissions">
                            <Panel>
                                <Box p="4">
                                    <TextField.Root
                                        placeholder="Search all permissions..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        mb="4"
                                    >
                                        <TextField.Slot>
                                            <MagnifyingGlassIcon style={{ width: 16, height: 16 }} />
                                        </TextField.Slot>
                                    </TextField.Root>

                                    <Table.Root variant="surface">
                                        <Table.Header>
                                            <Table.Row>
                                                <Table.ColumnHeaderCell>Permission Identifier</Table.ColumnHeaderCell>
                                                <Table.ColumnHeaderCell>Guard</Table.ColumnHeaderCell>
                                                <Table.ColumnHeaderCell>Assigned Roles</Table.ColumnHeaderCell>
                                            </Table.Row>
                                        </Table.Header>
                                        <Table.Body>
                                            {filteredPermissions.slice(0, 100).map((perm) => {
                                                const assignedRoles = roles.filter((r) => rolePermissionsMap.get(r.id)?.has(perm.id));
                                                return (
                                                    <Table.Row key={perm.id}>
                                                        <Table.Cell>
                                                            <Text weight="bold" size="2">{perm.name}</Text>
                                                        </Table.Cell>
                                                        <Table.Cell>
                                                            <Badge size="1" color="gray">{perm.guard_name}</Badge>
                                                        </Table.Cell>
                                                        <Table.Cell>
                                                            <Flex wrap="wrap" gap="1">
                                                                {assignedRoles.map((r) => (
                                                                    <Badge key={r.id} color="blue" size="1">{r.name}</Badge>
                                                                ))}
                                                            </Flex>
                                                        </Table.Cell>
                                                    </Table.Row>
                                                );
                                            })}
                                        </Table.Body>
                                    </Table.Root>
                                </Box>
                            </Panel>
                        </Tabs.Content>

                        <Tabs.Content value="modules">
                            <Panel>
                                <Box p="4">
                                    <Heading size="3" mb="3">Module Permission Hierarchy</Heading>
                                    <Grid columns={{ initial: '1', md: '2' }} gap="4">
                                        {Object.entries(permissionsGrouped).map(([moduleName, perms]) => (
                                            <Card key={moduleName}>
                                                <Heading size="2" mb="2" style={{ textTransform: 'capitalize' }}>
                                                    {moduleName} Module ({perms.length})
                                                </Heading>
                                                <Flex wrap="wrap" gap="1">
                                                    {perms.map((p) => (
                                                        <Badge key={p.id} size="1" color="purple" variant="outline">
                                                            {p.name}
                                                        </Badge>
                                                    ))}
                                                </Flex>
                                            </Card>
                                        ))}
                                    </Grid>
                                </Box>
                            </Panel>
                        </Tabs.Content>
                    </Box>
                </Tabs.Root>
            </Box>
        </App>
    );
};

export default RoleManagement;
