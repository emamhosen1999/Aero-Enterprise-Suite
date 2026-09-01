import React from 'react';
import { usePage } from "@inertiajs/react";
import {
    Table, Badge, Tooltip, IconButton, DropdownMenu,
    Flex, Text, Box
} from '@radix-ui/themes';
import {
    Pencil1Icon, TrashIcon, PersonIcon,
    CheckCircledIcon, CrossCircledIcon, DotsVerticalIcon
} from '@radix-ui/react-icons';
import TablePagination from '../../../Components/TablePagination.jsx';

const DesignationTable = ({
    designations, onEdit, onDelete, loading,
    isMobile, pagination, onPageChange, onRowsPerPageChange,
    canEditDesignation = false, canDeleteDesignation = false
}) => {
    const { auth } = usePage().props;
    const hasEditPermission = canEditDesignation || auth.permissions?.includes('designations.update') || false;
    const hasDeletePermission = canDeleteDesignation || auth.permissions?.includes('designations.delete') || false;

    // Helper for Hierarchy Colors
    const getLevelColor = (level) => {
        const colors = { 1: 'indigo', 2: 'cyan', 3: 'green', 4: 'orange', 5: 'red' };
        return colors[level] || 'gray';
    };

    if (!loading && (!designations || !designations.data || designations.data.length === 0)) {
        return (
            <Flex direction="column" align="center" justify="center" py="9" gap="2">
                <Text size="3" weight="medium">No designations found</Text>
                <Text size="2" color="gray">Try adjusting your search or filters.</Text>
            </Flex>
        );
    }

    return (
        <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
            <Table.Root size={isMobile ? '1' : '2'} style={{ minWidth: isMobile ? 650 : 950, width: '100%' }}>
                <Table.Header>
                    <Table.Row>
                        <Table.ColumnHeaderCell style={{ minWidth: 180 }}>Title</Table.ColumnHeaderCell>
                        <Table.ColumnHeaderCell style={{ minWidth: 160 }}>Department</Table.ColumnHeaderCell>
                        <Table.ColumnHeaderCell style={{ minWidth: 120 }}>Hierarchy</Table.ColumnHeaderCell>
                        <Table.ColumnHeaderCell style={{ minWidth: 110 }}>Employees</Table.ColumnHeaderCell>
                        <Table.ColumnHeaderCell style={{ minWidth: 100 }}>Status</Table.ColumnHeaderCell>
                        <Table.ColumnHeaderCell justify="end" style={{ width: 80, textAlign: 'right' }}>Actions</Table.ColumnHeaderCell>
                    </Table.Row>
                </Table.Header>

                <Table.Body>
                    {designations.data?.map((designation) => (
                        <Table.Row key={designation.id} align="center">
                            
                            <Table.Cell>
                                <Text weight="bold" size="2" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, color: 'var(--gray-12)', whiteSpace: 'nowrap' }}>{designation.title}</Text>
                            </Table.Cell>

                            <Table.Cell>
                                <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', whiteSpace: 'nowrap' }}>{designation.department_name || '—'}</Text>
                            </Table.Cell>

                            <Table.Cell>
                                <Badge color={getLevelColor(designation.hierarchy_level)} variant="soft" size="1" style={{ borderRadius: 999, fontWeight: 700 }}>
                                    Level {designation.hierarchy_level || 1}
                                </Badge>
                            </Table.Cell>

                            <Table.Cell>
                                <Flex align="center" gap="2">
                                    <PersonIcon style={{ color: 'var(--aero-color-subtle, var(--gray-9))', width: 14, height: 14 }} />
                                    <Text size="2" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>{designation.employee_count || 0}</Text>
                                </Flex>
                            </Table.Cell>

                            <Table.Cell>
                                <Badge color={designation.is_active ? 'jade' : 'red'} variant="soft" size="1" style={{ borderRadius: 999, fontWeight: 700, padding: '2px 8px' }}>
                                    {designation.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </Table.Cell>

                            <Table.Cell justify="end">
                                {!isMobile ? (
                                    <Flex gap="3" justify="end" align="center">
                                        {hasEditPermission && (
                                            <Tooltip content="Edit Designation">
                                                <IconButton size="1" variant="ghost" color="gray" style={{ cursor: 'pointer' }} onClick={() => onEdit(designation)}>
                                                    <Pencil1Icon />
                                                </IconButton>
                                            </Tooltip>
                                        )}
                                        {hasDeletePermission && (
                                            <Tooltip content={designation.employee_count > 0 ? "Cannot delete designation with employees" : "Delete Designation"}>
                                                <IconButton size="1" variant="ghost" color="red" style={{ cursor: designation.employee_count > 0 ? 'not-allowed' : 'pointer' }} disabled={designation.employee_count > 0} onClick={() => onDelete(designation)}>
                                                    <TrashIcon />
                                                </IconButton>
                                            </Tooltip>
                                        )}
                                    </Flex>
                                ) : (
                                    <DropdownMenu.Root>
                                        <DropdownMenu.Trigger>
                                            <IconButton size="1" variant="ghost" color="gray"><DotsVerticalIcon /></IconButton>
                                        </DropdownMenu.Trigger>
                                        <DropdownMenu.Content align="end">
                                            {hasEditPermission && <DropdownMenu.Item onClick={() => onEdit(designation)}><Pencil1Icon /> Edit Designation</DropdownMenu.Item>}
                                            {hasDeletePermission && <DropdownMenu.Item color="red" disabled={designation.employee_count > 0} onClick={() => onDelete(designation)}><TrashIcon /> Delete Designation</DropdownMenu.Item>}
                                        </DropdownMenu.Content>
                                    </DropdownMenu.Root>
                                )}
                            </Table.Cell>

                        </Table.Row>
                    ))}
                </Table.Body>
            </Table.Root>

            <TablePagination
                pagination={{ ...pagination, total: designations?.total || 0 }}
                onPageChange={onPageChange}
                onRowsPerPageChange={onRowsPerPageChange}
                loading={loading}
            />
        </Box>
    );
};

export default DesignationTable;