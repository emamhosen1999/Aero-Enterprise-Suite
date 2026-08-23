import React, { useState, useMemo } from 'react';
import { Box, Flex, Text, TextField, Badge, Button, IconButton } from '@radix-ui/themes';
import {
    Cross2Icon,
    MagnifyingGlassIcon,
    PersonIcon,
    ClockIcon,
    CheckCircledIcon,
    DrawingPinIcon,
    CameraIcon,
    SewingPinFilledIcon,
    ChevronRightIcon
} from '@radix-ui/react-icons';
import { THEME_COLORS } from './mapConstants';

export const MapTeamRosterDrawer = React.memo(({
    isOpen,
    onClose,
    users = [],
    selectedUserId,
    onSelectOfficer,
    onOpenTelemetry,
    onOpenPhoto
}) => {
    const [filterQuery, setFilterQuery] = useState('');

    const filteredUsers = useMemo(() => {
        if (!filterQuery) return users;
        const q = filterQuery.toLowerCase();
        return users.filter(u =>
            u.name?.toLowerCase().includes(q) ||
            u.employee_id?.toLowerCase().includes(q) ||
            u.designation?.toLowerCase().includes(q) ||
            u.department?.toLowerCase().includes(q)
        );
    }, [users, filterQuery]);

    if (!isOpen) return null;

    return (
        <Box
            style={{
                position: 'absolute',
                top: 74,
                right: 14,
                bottom: 14,
                width: 320,
                maxWidth: 'calc(100vw - 28px)',
                background: 'var(--color-panel-solid, var(--color-surface, #ffffff))',
                backdropFilter: 'blur(20px)',
                WebkitBackdropFilter: 'blur(20px)',
                borderRadius: 'var(--radius-4)',
                border: '1px solid var(--gray-a5)',
                boxShadow: 'var(--shadow-5, 0 20px 40px rgba(0, 0, 0, 0.25))',
                zIndex: 1000,
                display: 'flex',
                flexDirection: 'column',
                overflow: 'hidden',
                animation: 'slideInRight 0.25s cubic-bezier(0.16, 1, 0.3, 1)'
            }}
        >
            {/* Header */}
            <Box
                p="3"
                style={{
                    borderBottom: '1px solid var(--gray-a4)',
                    background: 'var(--gray-a2)'
                }}
            >
                <Flex justify="between" align="center" mb="2">
                    <Flex align="center" gap="2">
                        <PersonIcon style={{ color: 'var(--blue-9)', width: 16, height: 16 }} />
                        <Text size="2" weight="bold" style={{ color: 'var(--gray-12)' }}>
                            On-Duty Team Roster
                        </Text>
                        <Badge size="1" color="blue" variant="solid" radius="full">
                            {users.length}
                        </Badge>
                    </Flex>
                    <IconButton
                        size="1"
                        variant="ghost"
                        color="gray"
                        style={{ cursor: 'pointer' }}
                        onClick={onClose}
                    >
                        <Cross2Icon />
                    </IconButton>
                </Flex>

                {/* Filter Input */}
                <TextField.Root
                    size="1"
                    variant="surface"
                    placeholder="Filter roster..."
                    value={filterQuery}
                    onChange={(e) => setFilterQuery(e.target.value)}
                >
                    <TextField.Slot>
                        <MagnifyingGlassIcon style={{ color: 'var(--gray-9)' }} />
                    </TextField.Slot>
                    {filterQuery && (
                        <TextField.Slot>
                            <IconButton
                                size="1"
                                variant="ghost"
                                color="gray"
                                onClick={() => setFilterQuery('')}
                            >
                                <Cross2Icon />
                            </IconButton>
                        </TextField.Slot>
                    )}
                </TextField.Root>
            </Box>

            {/* Officer List Area */}
            <Box
                p="2"
                style={{
                    flex: 1,
                    overflowY: 'auto',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 6
                }}
            >
                {filteredUsers.length === 0 ? (
                    <Flex align="center" justify="center" direction="column" gap="2" p="4" style={{ height: '100%' }}>
                        <PersonIcon style={{ color: 'var(--gray-8)', width: 28, height: 28 }} />
                        <Text size="1" color="gray">No matching officers found</Text>
                    </Flex>
                ) : (
                    filteredUsers.map((user) => {
                        const isSelected = selectedUserId === user.user_id;
                        const isActive = user.status === 'active';
                        const inTime = user.punchin_time || '--';
                        const outTime = user.punchout_time;
                        const photoUrl = user.punchin_photo_url || user.profile_image_url;

                        return (
                            <Box
                                key={user.user_id}
                                p="2"
                                style={{
                                    borderRadius: 'var(--radius-3)',
                                    background: isSelected ? 'var(--blue-a3)' : 'var(--gray-a2)',
                                    border: isSelected ? '1px solid var(--blue-a7)' : '1px solid var(--gray-a4)',
                                    transition: 'all 0.15s ease',
                                    cursor: 'pointer'
                                }}
                                onClick={() => onSelectOfficer(user)}
                            >
                                <Flex justify="between" align="start" gap="2">
                                    {/* Avatar & Officer Info */}
                                    <Flex align="center" gap="2" style={{ minWidth: 0, flex: 1 }}>
                                        <Box
                                            style={{
                                                position: 'relative',
                                                width: 34,
                                                height: 34,
                                                borderRadius: '50%',
                                                overflow: 'hidden',
                                                border: `2px solid ${isActive ? THEME_COLORS.active : 'var(--gray-7)'}`,
                                                background: 'var(--gray-a4)',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                color: 'white',
                                                fontWeight: 'bold',
                                                fontSize: 12,
                                                flexShrink: 0
                                            }}
                                        >
                                            {user.profile_image_url ? (
                                                <img
                                                    src={user.profile_image_url}
                                                    alt={user.name}
                                                    style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                                />
                                            ) : (
                                                user.name?.charAt(0)?.toUpperCase() || '?'
                                            )}

                                            {/* Status Dot */}
                                            <span
                                                style={{
                                                    position: 'absolute',
                                                    bottom: 0,
                                                    right: 0,
                                                    width: 8,
                                                    height: 8,
                                                    borderRadius: '50%',
                                                    background: isActive ? THEME_COLORS.active : THEME_COLORS.completed,
                                                    border: '1px solid var(--color-surface)'
                                                }}
                                            />
                                        </Box>

                                        <Box style={{ minWidth: 0, flex: 1 }}>
                                            <Text
                                                size="2"
                                                weight="bold"
                                                style={{
                                                    color: 'var(--gray-12)',
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    display: 'block'
                                                }}
                                            >
                                                {user.name}
                                            </Text>
                                            <Text
                                                size="1"
                                                color="gray"
                                                style={{
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    display: 'block'
                                                }}
                                            >
                                                {user.designation || 'Staff'} {user.employee_id ? `• ${user.employee_id}` : ''}
                                            </Text>
                                        </Box>
                                    </Flex>

                                    {/* Action Focus Pill */}
                                    <IconButton
                                        size="1"
                                        variant="soft"
                                        color={isSelected ? 'blue' : 'gray'}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onSelectOfficer(user);
                                        }}
                                        title="Fly to marker on map"
                                    >
                                        <SewingPinFilledIcon />
                                    </IconButton>
                                </Flex>

                                {/* Timestamps & Photo Badges */}
                                <Flex justify="between" align="center" mt="2" pt="2" style={{ borderTop: '1px solid var(--gray-a4)' }}>
                                    <Flex align="center" gap="1">
                                        <ClockIcon style={{ color: 'var(--green-9)', width: 12, height: 12 }} />
                                        <Text size="1" weight="medium" style={{ color: 'var(--green-11)' }}>
                                            In: {inTime}
                                        </Text>
                                        {outTime && (
                                            <Text size="1" color="gray" ml="1">
                                                • Out: {outTime}
                                            </Text>
                                        )}
                                    </Flex>

                                    <Flex align="center" gap="1">
                                        {user.punchin_photo_url && (
                                            <IconButton
                                                size="1"
                                                variant="ghost"
                                                color="blue"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onOpenPhoto({
                                                        url: user.punchin_photo_url,
                                                        title: `Check-In Verification: ${user.name}`,
                                                        timestamp: inTime,
                                                        officerName: user.name,
                                                        employeeId: user.employee_id,
                                                        designation: user.designation,
                                                        location: user.punchin_location
                                                    });
                                                }}
                                                title="View Check-In Selfie"
                                            >
                                                <CameraIcon />
                                            </IconButton>
                                        )}

                                        <Button
                                            size="1"
                                            variant="surface"
                                            color="gray"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onOpenTelemetry(user);
                                            }}
                                            style={{ cursor: 'pointer', height: 22, fontSize: 10, padding: '0 6px' }}
                                        >
                                            Telemetry
                                            <ChevronRightIcon />
                                        </Button>
                                    </Flex>
                                </Flex>
                            </Box>
                        );
                    })
                )}
            </Box>
        </Box>
    );
});

MapTeamRosterDrawer.displayName = 'MapTeamRosterDrawer';
