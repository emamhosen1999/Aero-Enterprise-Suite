import React from 'react';
import { usePage } from "@inertiajs/react";
import {
    Table, Badge, Tooltip, IconButton, DropdownMenu,
    Button, Flex, Text, Box
} from '@radix-ui/themes';
import {
    Pencil1Icon, TrashIcon, PersonIcon,
    CheckCircledIcon, CrossCircledIcon, DotsVerticalIcon
} from '@radix-ui/react-icons';
import NoDataMessage from '@/Components/NoDataMessage';
import TablePagination from '@/Components/TablePagination.jsx';

const DesignationTable = ({
    designations,
    onEdit,
    onDelete,
    loading,
    isMobile,
    pagination,
    onPageChange,
    onRowsPerPageChange,
    canEditDesignation = false,
    canDeleteDesignation = false
}) => {
    const { auth } = usePage().props;
    const hasEditPermission = canEditDesignation || auth.permissions?.includes('designations.update') || false;
    const hasDeletePermission = canDeleteDesignation || auth.permissions?.includes('designations.delete') || false;

    // Helper for Hierarchy Colors
    const getLevelColor = (level) => {
        const colors = { 1: 'indigo', 2: 'cyan', 3: 'green', 4: 'orange', 5: 'red' };
        return colors[level] || 'gray';
    };

    // Calculate Pagination
    const totalPages = Math.ceil((designations?.total || 0) / pagination.perPage);
    const startRecord = ((pagination.currentPage - 1) * pagination.perPage) + 1;
    const endRecord = Math.min(pagination.currentPage * pagination.perPage, designations?.total || 0);

    // Empty State Handling
    if (!loading && (!designations || !designations.data || designations.data.length === 0)) {
        return (
            <Box py="6">
                <NoDataMessage 
                    message="No designations found" 
                    description="Try adjusting your search or filters"
                />
            </Box>
        );
    }

    return (
        <Box>
            <Box style={{ 
                overflowX: 'auto', 
                WebkitOverflowScrolling: 'touch', 
                borderRadius: 16, 
                border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                position: 'relative'
            }}>
                <Table.Root size="2" style={{ minWidth: 840, width: '100%', opacity: loading ? 0.6 : 1, transition: 'opacity 0.2s ease' }}>
                    <Table.Header style={{
                        position: 'sticky',
                        top: 0,
                        zIndex: 2,
                        background: 'var(--aero-surface, var(--color-background))',
                        backdropFilter: 'blur(8px)',
                        boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))'
                    }}>
                        <Table.Row>
                            <Table.ColumnHeaderCell style={{ minWidth: 200, background: 'inherit' }}><Text size="1" weight="bold">TITLE</Text></Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell style={{ minWidth: 180, background: 'inherit' }}><Text size="1" weight="bold">DEPARTMENT</Text></Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell style={{ minWidth: 130, background: 'inherit' }}><Text size="1" weight="bold">HIERARCHY</Text></Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell style={{ minWidth: 120, background: 'inherit' }}><Text size="1" weight="bold">EMPLOYEES</Text></Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell style={{ minWidth: 110, background: 'inherit' }}><Text size="1" weight="bold">STATUS</Text></Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell justify="end" style={{ minWidth: 80, background: 'inherit' }}><Text size="1" weight="bold">ACTIONS</Text></Table.ColumnHeaderCell>
                        </Table.Row>
                    </Table.Header>

                    <Table.Body>
                        {loading && (!designations?.data || designations.data.length === 0) ? (
                            <Table.Row>
                                <Table.Cell colSpan={6} style={{ textAlign: 'center', padding: '32px' }}>
                                    <Flex justify="center" py="6" align="center" gap="2">
                                        <Text size="2" color="gray">Loading designations...</Text>
                                    </Flex>
                                </Table.Cell>
                            </Table.Row>
                        ) : designations.data?.map((designation) => (
                        <Table.Row key={designation.id} align="center">
                            
                            {/* Title */}
                            <Table.Cell>
                                <Text weight="bold" size="2">{designation.title}</Text>
                            </Table.Cell>

                            {/* Department */}
                            <Table.Cell>
                                <Text color="gray" size="2">{designation.department_name || '-'}</Text>
                            </Table.Cell>

                            {/* Hierarchy Level */}
                            <Table.Cell>
                                <Badge color={getLevelColor(designation.hierarchy_level)} variant="soft" size="1">
                                    Level {designation.hierarchy_level || 1}
                                </Badge>
                            </Table.Cell>

                            {/* Employees Count */}
                            <Table.Cell>
                                <Flex align="center" gap="2">
                                    <PersonIcon color="gray" />
                                    <Text size="2">{designation.employee_count || 0}</Text>
                                </Flex>
                            </Table.Cell>

                            {/* Status */}
                            <Table.Cell>
                                <Badge 
                                    color={designation.is_active ? 'green' : 'red'} 
                                    variant={designation.is_active ? 'solid' : 'soft'}
                                    size="1"
                                >
                                    {designation.is_active ? <CheckCircledIcon /> : <CrossCircledIcon />}
                                    {designation.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </Table.Cell>

                            {/* Actions */}
                            <Table.Cell justify="end">
                                {!isMobile ? (
                                    <Flex gap="3" justify="end" align="center">
                                        {hasEditPermission && (
                                            <Tooltip content="Edit Designation">
                                                <IconButton 
                                                    size="1" 
                                                    variant="ghost" 
                                                    color="gray"
                                                    style={{ cursor: 'pointer' }}
                                                    onClick={() => onEdit(designation)}
                                                >
                                                    <Pencil1Icon width="16" height="16" />
                                                </IconButton>
                                            </Tooltip>
                                        )}
                                        {hasDeletePermission && (
                                            <Tooltip content={designation.employee_count > 0 ? "Cannot delete designation with employees" : "Delete Designation"}>
                                                <IconButton 
                                                    size="1" 
                                                    variant="ghost" 
                                                    color="red"
                                                    style={{ cursor: designation.employee_count > 0 ? 'not-allowed' : 'pointer' }}
                                                    disabled={designation.employee_count > 0}
                                                    onClick={() => onDelete(designation)}
                                                >
                                                    <TrashIcon width="16" height="16" />
                                                </IconButton>
                                            </Tooltip>
                                        )}
                                    </Flex>
                                ) : (
                                    <DropdownMenu.Root>
                                        <DropdownMenu.Trigger>
                                            <IconButton size="1" variant="ghost" color="gray">
                                                <DotsVerticalIcon />
                                            </IconButton>
                                        </DropdownMenu.Trigger>
                                        <DropdownMenu.Content align="end">
                                            {hasEditPermission && (
                                                <DropdownMenu.Item onClick={() => onEdit(designation)}>
                                                    <Pencil1Icon /> Edit Designation
                                                </DropdownMenu.Item>
                                            )}
                                            {hasDeletePermission && (
                                                <DropdownMenu.Item 
                                                    color="red" 
                                                    disabled={designation.employee_count > 0}
                                                    onClick={() => onDelete(designation)}
                                                >
                                                    <TrashIcon /> Delete Designation
                                                </DropdownMenu.Item>
                                            )}
                                        </DropdownMenu.Content>
                                    </DropdownMenu.Root>
                                )}
                            </Table.Cell>

                        </Table.Row>
                    ))}
                </Table.Body>
            </Table.Root>
            </Box>

            {/* Pagination */}
            <TablePagination
                pagination={pagination}
                onPageChange={onPageChange}
                onRowsPerPageChange={onRowsPerPageChange}
                loading={loading}
            />
        </Box>
    );
};

export default DesignationTable;