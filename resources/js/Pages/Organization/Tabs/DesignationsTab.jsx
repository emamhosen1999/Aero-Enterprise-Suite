import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { showToast } from '@/utils/toastUtils';
import { useMediaQuery } from '@/Hooks/useMediaQuery.js';
import { 
    Box, Flex, Grid, Text, Button, TextField, 
    Select, Separator, Spinner, Badge, IconButton
} from '@radix-ui/themes';
import { 
    LayersIcon, CheckCircledIcon, CrossCircledIcon, 
    PersonIcon, MagnifyingGlassIcon, PlusIcon, Cross2Icon
} from '@radix-ui/react-icons';
import * as useDesignationsQuery from '@/api/queries/useDesignationsQuery';
import StatsCards from '@/Components/StatsCards';
import SearchFilterBar from '@/Components/SearchFilterBar';
import PageToolbar from '@/Components/PageToolbar';

// Placeholder imports for next steps
import DesignationTable from '../Tables/DesignationTable.jsx';
import DesignationForm from '../Components/DesignationForm.jsx';
import DeleteDesignationForm from '../Components/DeleteDesignationForm.jsx';

const DesignationsTab = ({ isActive }) => {
    const { auth, initialDesignations, departments, allDesignations, designationStats: initialStats } = usePage().props;
    const isMobile = useMediaQuery('(max-width: 767px)');

    const [modalState, setModalState] = useState({ type: null, designation: null });

    const defaultDepartment = useMemo(() => {
        if (!departments || departments.length === 0) return 'all';
        const deptCounts = {};
        allDesignations?.forEach(des => {
            if (des.department_id) {
                deptCounts[des.department_id] = (deptCounts[des.department_id] || 0) + 1;
            }
        });
        const maxDept = Object.entries(deptCounts).sort((a, b) => b[1] - a[1])[0];
        return maxDept ? String(maxDept[0]) : 'all';
    }, [departments, allDesignations]);

    const [filters, setFilters] = useState({ search: '', status: 'all', department: defaultDepartment });
    const [pagination, setPagination] = useState({ currentPage: 1, perPage: 10 });

    const canCreate = auth.permissions?.includes('designations.create') || false;
    const canEdit = auth.permissions?.includes('designations.update') || false;
    const canDelete = auth.permissions?.includes('designations.delete') || false;

    // React Query hooks
    const { data: designationsData, isLoading: loading, refetch } = useDesignationsQuery.useDesignationsList({
        page: pagination.currentPage,
        per_page: pagination.perPage,
        search: filters.search,
        status: filters.status,
        department: filters.department !== 'all' ? filters.department : undefined
    });

    const { data: stats } = useDesignationsQuery.useDesignationStats();

    // Auto-refetch when filters or pagination changes
    useEffect(() => {
        if (isActive) {
            refetch();
        }
    }, [pagination.currentPage, pagination.perPage, filters.search, filters.status, filters.department, isActive, refetch]);

    const handleFilterChange = (key, value) => { setFilters(prev => ({ ...prev, [key]: value })); setPagination(prev => ({ ...prev, currentPage: 1 })); };
    const clearFilters = () => { setFilters({ search: '', status: 'all', department: 'all' }); setPagination(p => ({ ...p, currentPage: 1 })); };

    const openModal = (type, designation = null) => setModalState({ type, designation });
    const closeModal = () => setModalState({ type: null, designation: null });

    const handleSuccess = () => {
        refetch();
    };

    const statPills = [
        { key: 'total', label: 'Total', value: stats?.total || 0, color: 'blue' },
        { key: 'active', label: 'Active', value: stats?.active || 0, color: 'green' },
        { key: 'inactive', label: 'Inactive', value: stats?.inactive || 0, color: 'red' },
        { key: 'parent', label: 'Top-Level', value: stats?.parent_designations || 0, color: 'purple' },
    ];

    const activeFilterChips = useMemo(() => {
        const chips = [];
        if (filters.search) chips.push({ label: 'Search', value: filters.search, onRemove: () => handleFilterChange('search', '') });
        if (filters.department !== 'all') {
            const dept = departments?.find(d => String(d.id) === String(filters.department));
            chips.push({ label: 'Department', value: dept?.name || filters.department, onRemove: () => handleFilterChange('department', 'all') });
        }
        if (filters.status !== 'all') {
            chips.push({ label: 'Status', value: filters.status === 'active' ? 'Active' : 'Inactive', onRemove: () => handleFilterChange('status', 'all') });
        }
        return chips;
    }, [filters, departments]);

    return (
        <Box>
            {/* Quick Stats Pills */}
            <StatsCards stats={statPills} variant="pill" mb="4" />

            {/* Toolbar & Search/Filter Bar */}
            <PageToolbar
                canAdd={canCreate}
                onAdd={() => openModal('add_designation')}
                addLabel={!isMobile ? 'Add Designation' : 'Add'}
                leftSlot={
                    <SearchFilterBar
                        searchValue={filters.search}
                        onSearchChange={(val) => handleFilterChange('search', val)}
                        searchPlaceholder="Search designations..."
                        showFilterToggle={false}
                        activeFilterChips={activeFilterChips}
                        onClearFilters={activeFilterChips.length > 0 ? clearFilters : null}
                        mb="0"
                        extraActions={
                            <Flex gap="2" wrap="wrap">
                                <Box style={{ minWidth: '180px' }}>
                                    <Select.Root value={filters.department} onValueChange={(v) => handleFilterChange('department', v)}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="all">All Departments</Select.Item>
                                            {departments?.map(dept => <Select.Item key={dept.id} value={String(dept.id)}>{dept.name}</Select.Item>)}
                                        </Select.Content>
                                    </Select.Root>
                                </Box>

                                <Box style={{ minWidth: '140px' }}>
                                    <Select.Root value={filters.status} onValueChange={(v) => handleFilterChange('status', v)}>
                                        <Select.Trigger style={{ width: '100%' }} />
                                        <Select.Content>
                                            <Select.Item value="all">All Status</Select.Item>
                                            <Select.Item value="active">Active</Select.Item>
                                            <Select.Item value="inactive">Inactive</Select.Item>
                                        </Select.Content>
                                    </Select.Root>
                                </Box>
                            </Flex>
                        }
                    />
                }
            />

            {/* Data Table */}
            <Box>
                {loading && !designationsData?.data ? (
                    <Flex justify="center" align="center" py="8" direction="column" gap="3">
                        <Spinner size="3" />
                        <Text color="gray">Loading designations...</Text>
                    </Flex>
                ) : (
                    <DesignationTable
                        designations={designationsData}
                        loading={loading}
                        onEdit={canEdit ? (d) => openModal('edit_designation', d) : undefined}
                        onDelete={canDelete ? (d) => openModal('delete_designation', d) : undefined}
                        pagination={pagination}
                        onPageChange={(page) => setPagination(p => ({ ...p, currentPage: page }))}
                        onRowsPerPageChange={(perPage) => setPagination(p => ({ ...p, perPage, currentPage: 1 }))}
                    />
                )}
            </Box>

            {/* Modals placeholders */}
            {(modalState.type === 'add_designation' || modalState.type === 'edit_designation') && (
                <DesignationForm
                    open={true}
                    departments={departments}
                    designations={allDesignations}
                    onClose={closeModal}
                    onSuccess={(d) => handleSuccess(d, modalState.type === 'add_designation' ? 'add' : 'edit')}
                    designation={modalState.designation}
                />
            )}

            {modalState.type === 'delete_designation' && (
                <DeleteDesignationForm
                    open={true}
                    onClose={closeModal}
                    onSuccess={(d) => handleSuccess(d, 'delete')}
                    designation={modalState.designation}
                />
            )}
        </Box>
    );
};

export default DesignationsTab;