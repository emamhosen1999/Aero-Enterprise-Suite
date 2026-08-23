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
                background: 'rgba(15, 23, 42, 0.92)',
                backdropFilter: 'blur(20px)',
                WebkitBackdropFilter: 'blur(20px)',
                borderRadius: 'var(--radius-4)',
                border: '1px solid rgba(255, 255, 255, 0.15)',
                boxShadow: '0 20px 40px rgba(0, 0, 0, 0.5)',
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
                    borderBottom: '1px solid rgba(255, 255, 255, 0.1)',
                    background: 'rgba(0, 0, 0, 0.25)'
                }}
            >
                <Flex justify="between" align="center" mb="2">
                    <Flex align="center" gap="2">
                        <PersonIcon style={{ color: '#38bdf8', width: 16, height: 16 }} />
                        <Text size="2" weight="bold" style={{ color: '#ffffff' }}>
                            On-Duty Team Roster
                        </Text>
                        <Badge size="1" color="blue" variant="solid" radius="full">
                            {users.length}
                        </Badge>
                    </Flex>
                    <IconButton
                        size="1"
                        variant="ghost"
                        style={{ color: '#94a3b8', cursor: 'pointer' }}
                        onClick={onClose}
                    >
                        <Cross2Icon />
                    </IconButton>
                </Flex>

                {/* Filter Input */}
                <TextField.Root
                    size="1"
                    placeholder="Filter roster..."
                    value={filterQuery}
                    onChange={(e) => setFilterQuery(e.target.value)}
                    style={{
                        background: 'rgba(255, 255, 255, 0.08)',
                        color: '#ffffff',
                        border: '1px solid rgba(255, 255, 255, 0.15)',
                    }}
                >
                    <TextField.Slot>
                        <MagnifyingGlassIcon style={{ color: '#94a3b8' }} />
                    </TextField.Slot>
                    {filterQuery && (
                        <TextField.Slot>
                            <IconButton
                                size="1"
                                variant="ghost"
                                style={{ color: '#cbd5e1', cursor: 'pointer' }}
                                onClick={() => setFilterQuery('')}
                            >
                                <Cross2Icon />
                            </IconButton>
                        </TextField.Slot>
                    )}
                </TextField.Root>
            </Box>

            {/* Officer Cards List */}
            <Box
                p="2"
                style={{
                    flex: 1,
                    overflowY: 'auto',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 8
                }}
            >
                {filteredUsers.length > 0 ? (
                    filteredUsers.map((user) => {
                        const isSelected = selectedUserId === user.user_id;
                        const isActive = user.status === 'active';
                        const lastPhoto = user.punchout_photo_url || user.punchin_photo_url;

                        return (
                            <Box
                                key={user.user_id}
                                p="2"
                                style={{
                                    background: isSelected
                                        ? 'rgba(56, 189, 248, 0.18)'
                                        : 'rgba(255, 255, 255, 0.05)',
                                    borderRadius: 'var(--radius-3)',
                                    border: isSelected
                                        ? '1px solid rgba(56, 189, 248, 0.5)'
                                        : '1px solid rgba(255, 255, 255, 0.08)',
                                    cursor: 'pointer',
                                    transition: 'all 0.15s ease',
                                }}
                                onMouseEnter={(e) => {
                                    if (!isSelected) e.currentTarget.style.background = 'rgba(255, 255, 255, 0.1)';
                                }}
                                onMouseLeave={(e) => {
                                    if (!isSelected) e.currentTarget.style.background = 'rgba(255, 255, 255, 0.05)';
                                }}
                                onClick={() => onSelectOfficer(user)}
                            >
                                <Flex justify="between" align="start" gap="2">
                                    <Flex align="start" gap="2" style={{ flex: 1, minWidth: 0 }}>
                                        {/* Avatar with Status Ring */}
                                        <Box
                                            style={{
                                                position: 'relative',
                                                width: 36,
                                                height: 36,
                                                borderRadius: '50%',
                                                overflow: 'hidden',
                                                border: `2px solid ${isActive ? THEME_COLORS.active : '#64748b'}`,
                                                background: '#1e293b',
                                                color: 'white',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                fontSize: 12,
                                                fontWeight: 'bold',
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
                                        </Box>

                                        {/* Info */}
                                        <Box style={{ flex: 1, minWidth: 0 }}>
                                            <Flex align="center" gap="1">
                                                <Text
                                                    size="2"
                                                    weight="bold"
                                                    style={{
                                                        color: '#ffffff',
                                                        whiteSpace: 'nowrap',
                                                        overflow: 'hidden',
                                                        textOverflow: 'ellipsis'
                                                    }}
                                                >
                                                    {user.name}
                                                </Text>
                                            </Flex>

                                            <Text
                                                size="1"
                                                style={{
                                                    color: '#94a3b8',
                                                    fontSize: 11,
                                                    whiteSpace: 'nowrap',
                                                    overflow: 'hidden',
                                                    textOverflow: 'ellipsis',
                                                    display: 'block'
                                                }}
                                            >
                                                {user.designation || 'Officer'}
                                            </Text>

                                            {/* Punch Info */}
                                            <Flex align="center" gap="2" mt="1" wrap="wrap">
                                                {user.punchin_time && (
                                                    <Flex align="center" gap="1">
                                                        <ClockIcon style={{ color: '#34d399', width: 10, height: 10 }} />
                                                        <Text size="1" style={{ color: '#cbd5e1', fontSize: 10 }}>
                                                            In: {user.punchin_time}
                                                        </Text>
                                                    </Flex>
                                                )}
                                                {user.punchout_time && (
                                                    <Flex align="center" gap="1">
                                                        <CheckCircledIcon style={{ color: '#f87171', width: 10, height: 10 }} />
                                                        <Text size="1" style={{ color: '#cbd5e1', fontSize: 10 }}>
                                                            Out: {user.punchout_time}
                                                        </Text>
                                                    </Flex>
                                                )}
                                            </Flex>
                                        </Box>
                                    </Flex>

                                    {/* Action column */}
                                    <Flex direction="column" align="end" gap="1">
                                        <Badge
                                            size="1"
                                            color={isActive ? 'green' : 'blue'}
                                            variant="solid"
                                            radius="full"
                                            style={{ fontSize: 9 }}
                                        >
                                            {isActive ? 'Live' : 'Done'}
                                        </Badge>

                                        <IconButton
                                            size="1"
                                            variant="ghost"
                                            style={{ color: '#38bdf8', cursor: 'pointer', height: 20, width: 20 }}
                                            title="Inspect full telemetry"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onOpenTelemetry(user);
                                            }}
                                        >
                                            <ChevronRightIcon />
                                        </IconButton>
                                    </Flex>
                                </Flex>
                            </Box>
                        );
                    })
                ) : (
                    <Flex direction="column" align="center" justify="center" p="4" gap="2">
                        <PersonIcon style={{ color: '#64748b', width: 28, height: 28 }} />
                        <Text size="1" style={{ color: '#94a3b8' }}>
                            {filterQuery ? 'No matching officers found' : 'No officers tracked'}
                        </Text>
                    </Flex>
                )}
            </Box>
        </Box>
    );
});

MapTeamRosterDrawer.displayName = 'MapTeamRosterDrawer';
export default MapTeamRosterDrawer;
