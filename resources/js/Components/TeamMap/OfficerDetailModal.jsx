import React, { useState } from 'react';
import { Box, Flex, Text, Badge, Button, IconButton, Separator } from '@radix-ui/themes';
import {
    Cross2Icon,
    PersonIcon,
    ClockIcon,
    DrawingPinIcon,
    CheckCircledIcon,
    CameraIcon,
    CopyIcon,
    CheckIcon,
    EnterFullScreenIcon,
    GlobeIcon,
    SewingPinFilledIcon
} from '@radix-ui/react-icons';
import { THEME_COLORS } from './mapConstants';

export const OfficerDetailModal = React.memo(({
    officer,
    selectedDate,
    onClose,
    onOpenPhoto,
    onFocusMap
}) => {
    const [copiedIndex, setCopiedIndex] = useState(null);

    if (!officer) return null;

    const {
        name,
        employee_id,
        designation,
        department,
        profile_image_url,
        status,
        cycles = [],
        punchin_time,
        punchout_time,
        punchin_location,
        punchout_location,
        punchin_photo_url,
        punchout_photo_url,
        attendance_type
    } = officer;

    const isActive = status === 'active';

    const handleCopy = (text, key) => {
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopiedIndex(key);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    // Parse cycles or fallback to single cycle
    const effectiveCycles = cycles && cycles.length > 0 ? cycles : [
        {
            attendance_id: 'default',
            punchin_time,
            punchout_time,
            punchin_location,
            punchout_location,
            punchin_photo_url,
            punchout_photo_url,
            is_complete: !!punchout_time
        }
    ];

    return (
        <Box
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(5, 10, 20, 0.75)',
                backdropFilter: 'blur(8px)',
                WebkitBackdropFilter: 'blur(8px)',
                zIndex: 99990,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 16,
                animation: 'fadeIn 0.2s ease-out'
            }}
            onClick={onClose}
        >
            <Box
                style={{
                    width: '100%',
                    maxWidth: 580,
                    maxHeight: '90vh',
                    background: 'var(--color-panel-solid, #1e293b)',
                    borderRadius: 'var(--radius-4)',
                    border: '1px solid var(--gray-a6)',
                    boxShadow: '0 25px 60px -15px rgba(0,0,0,0.5)',
                    display: 'flex',
                    flexDirection: 'column',
                    overflow: 'hidden',
                }}
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header Section */}
                <Box
                    p="4"
                    style={{
                        background: 'linear-gradient(135deg, var(--gray-a3), var(--gray-a4))',
                        borderBottom: '1px solid var(--gray-a5)',
                    }}
                >
                    <Flex justify="between" align="start">
                        <Flex align="center" gap="3">
                            {/* Avatar */}
                            <Box
                                style={{
                                    width: 52,
                                    height: 52,
                                    borderRadius: '50%',
                                    overflow: 'hidden',
                                    border: `3px solid ${isActive ? THEME_COLORS.active : 'var(--gray-a7)'}`,
                                    background: 'var(--gray-a4)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: 'white',
                                    fontWeight: 'bold',
                                    fontSize: 18,
                                    flexShrink: 0,
                                    boxShadow: isActive ? '0 0 12px rgba(16, 185, 129, 0.4)' : 'none'
                                }}
                            >
                                {profile_image_url ? (
                                    <img
                                        src={profile_image_url}
                                        alt={name}
                                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                    />
                                ) : (
                                    name?.charAt(0)?.toUpperCase() || '?'
                                )}
                            </Box>

                            <Box>
                                <Flex align="center" gap="2" wrap="wrap">
                                    <Text size="3" weight="bold" style={{ color: 'var(--gray-12)' }}>
                                        {name || 'Officer'}
                                    </Text>
                                    <Badge
                                        size="1"
                                        color={isActive ? 'green' : 'blue'}
                                        variant="solid"
                                        radius="full"
                                    >
                                        {isActive ? '🟢 Active On-Duty' : '✅ Shift Completed'}
                                    </Badge>
                                </Flex>

                                <Text size="1" color="gray">
                                    {designation || 'Employee'} {department ? `• ${department}` : ''}
                                    {employee_id ? ` • ID: ${employee_id}` : ''}
                                </Text>

                                {attendance_type && (
                                    <Badge size="1" color="purple" variant="soft" mt="1">
                                        Zone: {attendance_type.name || 'Standard'}
                                    </Badge>
                                )}
                            </Box>
                        </Flex>

                        {/* Close button */}
                        <IconButton
                            size="2"
                            variant="ghost"
                            color="gray"
                            onClick={onClose}
                            style={{ cursor: 'pointer' }}
                        >
                            <Cross2Icon />
                        </IconButton>
                    </Flex>
                </Box>

                {/* Body Timeline */}
                <Box
                    p="4"
                    style={{
                        overflowY: 'auto',
                        flex: 1,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 16
                    }}
                >
                    <Flex justify="between" align="center">
                        <Text size="2" weight="bold" style={{ color: 'var(--gray-11)' }}>
                            Attendance & Patrol Telemetry ({effectiveCycles.length} {effectiveCycles.length === 1 ? 'Cycle' : 'Cycles'})
                        </Text>
                        <Text size="1" color="gray">
                            Date: {selectedDate || 'Today'}
                        </Text>
                    </Flex>

                    {effectiveCycles.map((cycle, idx) => {
                        const inLoc = cycle.punchin_location;
                        const outLoc = cycle.punchout_location;
                        const inCoords = inLoc && inLoc.lat && inLoc.lng
                            ? `${parseFloat(inLoc.lat).toFixed(5)}, ${parseFloat(inLoc.lng).toFixed(5)}`
                            : null;
                        const outCoords = outLoc && outLoc.lat && outLoc.lng
                            ? `${parseFloat(outLoc.lat).toFixed(5)}, ${parseFloat(outLoc.lng).toFixed(5)}`
                            : null;

                        return (
                            <Box
                                key={idx}
                                p="3"
                                style={{
                                    background: 'var(--gray-a2)',
                                    borderRadius: 'var(--radius-3)',
                                    border: '1px solid var(--gray-a4)',
                                }}
                            >
                                <Flex justify="between" align="center" mb="3">
                                    <Badge size="1" color="gray" variant="surface">
                                        Shift Cycle #{idx + 1}
                                    </Badge>
                                    <Badge
                                        size="1"
                                        color={cycle.is_complete ? 'blue' : 'green'}
                                        variant="soft"
                                    >
                                        {cycle.is_complete ? 'Cycle Finished' : 'Active Cycle'}
                                    </Badge>
                                </Flex>

                                <Flex direction="column" gap="3">
                                    {/* Check-In Card */}
                                    <Flex
                                        align="start"
                                        justify="between"
                                        p="2"
                                        style={{
                                            background: 'var(--green-a2)',
                                            borderRadius: 'var(--radius-2)',
                                            border: '1px solid var(--green-a4)'
                                        }}
                                    >
                                        <Flex align="start" gap="2" style={{ flex: 1 }}>
                                            <Box
                                                style={{
                                                    width: 24,
                                                    height: 24,
                                                    borderRadius: '50%',
                                                    background: THEME_COLORS.punchin,
                                                    color: 'white',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    flexShrink: 0
                                                }}
                                            >
                                                <ClockIcon style={{ width: 14, height: 14 }} />
                                            </Box>
                                            <Box>
                                                <Text size="1" weight="bold" style={{ color: 'var(--green-11)' }}>
                                                    Check-In: {cycle.punchin_time || '--'}
                                                </Text>
                                                {inCoords ? (
                                                    <Flex align="center" gap="1" mt="1">
                                                        <DrawingPinIcon style={{ color: 'var(--green-9)', width: 12, height: 12 }} />
                                                        <Text size="1" style={{ fontSize: 11, fontFamily: 'monospace', color: 'var(--gray-11)' }}>
                                                            {inCoords}
                                                        </Text>
                                                        <IconButton
                                                            size="1"
                                                            variant="ghost"
                                                            style={{ height: 18, width: 18 }}
                                                            onClick={() => handleCopy(inCoords, `in-${idx}`)}
                                                        >
                                                            {copiedIndex === `in-${idx}` ? <CheckIcon /> : <CopyIcon />}
                                                        </IconButton>
                                                    </Flex>
                                                ) : (
                                                    <Text size="1" color="gray" style={{ fontSize: 11 }}>No GPS coordinates</Text>
                                                )}
                                            </Box>
                                        </Flex>

                                        {/* Selfie Preview */}
                                        {cycle.punchin_photo_url && (
                                            <Box
                                                style={{
                                                    width: 48,
                                                    height: 48,
                                                    borderRadius: 'var(--radius-2)',
                                                    overflow: 'hidden',
                                                    border: '1px solid var(--green-a6)',
                                                    cursor: 'pointer',
                                                    position: 'relative',
                                                    flexShrink: 0
                                                }}
                                                onClick={() => onOpenPhoto && onOpenPhoto({
                                                    url: cycle.punchin_photo_url,
                                                    officerName: name,
                                                    designation,
                                                    timestamp: cycle.punchin_time,
                                                    location: inLoc,
                                                    type: 'punchin'
                                                })}
                                            >
                                                <img
                                                    src={cycle.punchin_photo_url}
                                                    alt="Check-in selfie"
                                                    style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                                />
                                                <Box
                                                    style={{
                                                        position: 'absolute',
                                                        bottom: 0,
                                                        insetInline: 0,
                                                        background: 'rgba(0,0,0,0.6)',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        padding: 1
                                                    }}
                                                >
                                                    <EnterFullScreenIcon style={{ color: 'white', width: 10, height: 10 }} />
                                                </Box>
                                            </Box>
                                        )}
                                    </Flex>

                                    {/* Check-Out Card */}
                                    {cycle.punchout_time ? (
                                        <Flex
                                            align="start"
                                            justify="between"
                                            p="2"
                                            style={{
                                                background: 'var(--red-a2)',
                                                borderRadius: 'var(--radius-2)',
                                                border: '1px solid var(--red-a4)'
                                            }}
                                        >
                                            <Flex align="start" gap="2" style={{ flex: 1 }}>
                                                <Box
                                                    style={{
                                                        width: 24,
                                                        height: 24,
                                                        borderRadius: '50%',
                                                        background: THEME_COLORS.punchout,
                                                        color: 'white',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        flexShrink: 0
                                                    }}
                                                >
                                                    <CheckCircledIcon style={{ width: 14, height: 14 }} />
                                                </Box>
                                                <Box>
                                                    <Text size="1" weight="bold" style={{ color: 'var(--red-11)' }}>
                                                        Check-Out: {cycle.punchout_time}
                                                    </Text>
                                                    {outCoords ? (
                                                        <Flex align="center" gap="1" mt="1">
                                                            <DrawingPinIcon style={{ color: 'var(--red-9)', width: 12, height: 12 }} />
                                                            <Text size="1" style={{ fontSize: 11, fontFamily: 'monospace', color: 'var(--gray-11)' }}>
                                                                {outCoords}
                                                            </Text>
                                                            <IconButton
                                                                size="1"
                                                                variant="ghost"
                                                                style={{ height: 18, width: 18 }}
                                                                onClick={() => handleCopy(outCoords, `out-${idx}`)}
                                                            >
                                                                {copiedIndex === `out-${idx}` ? <CheckIcon /> : <CopyIcon />}
                                                            </IconButton>
                                                        </Flex>
                                                    ) : (
                                                        <Text size="1" color="gray" style={{ fontSize: 11 }}>No GPS coordinates</Text>
                                                    )}
                                                </Box>
                                            </Flex>

                                            {/* Selfie Preview */}
                                            {cycle.punchout_photo_url && (
                                                <Box
                                                    style={{
                                                        width: 48,
                                                        height: 48,
                                                        borderRadius: 'var(--radius-2)',
                                                        overflow: 'hidden',
                                                        border: '1px solid var(--red-a6)',
                                                        cursor: 'pointer',
                                                        position: 'relative',
                                                        flexShrink: 0
                                                    }}
                                                    onClick={() => onOpenPhoto && onOpenPhoto({
                                                        url: cycle.punchout_photo_url,
                                                        officerName: name,
                                                        designation,
                                                        timestamp: cycle.punchout_time,
                                                        location: outLoc,
                                                        type: 'punchout'
                                                    })}
                                                >
                                                    <img
                                                        src={cycle.punchout_photo_url}
                                                        alt="Check-out selfie"
                                                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                                    />
                                                    <Box
                                                        style={{
                                                            position: 'absolute',
                                                            bottom: 0,
                                                            insetInline: 0,
                                                            background: 'rgba(0,0,0,0.6)',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            padding: 1
                                                        }}
                                                    >
                                                        <EnterFullScreenIcon style={{ color: 'white', width: 10, height: 10 }} />
                                                    </Box>
                                                </Box>
                                            )}
                                        </Flex>
                                    ) : (
                                        <Flex
                                            align="center"
                                            gap="2"
                                            p="2"
                                            style={{
                                                background: 'var(--gray-a3)',
                                                borderRadius: 'var(--radius-2)',
                                                border: '1px dashed var(--gray-a5)'
                                            }}
                                        >
                                            <ClockIcon style={{ color: 'var(--amber-9)' }} />
                                            <Text size="1" color="gray">
                                                Officer is currently on active patrol. Check-out not recorded yet.
                                            </Text>
                                        </Flex>
                                    )}
                                </Flex>
                            </Box>
                        );
                    })}
                </Box>

                {/* Footer Controls */}
                <Box
                    p="3"
                    style={{
                        background: 'var(--gray-a2)',
                        borderTop: '1px solid var(--gray-a4)',
                    }}
                >
                    <Flex justify="between" align="center" gap="2">
                        <Button
                            variant="surface"
                            color="blue"
                            size="2"
                            onClick={() => {
                                onClose();
                                if (onFocusMap) {
                                    const targetLoc = punchin_location || punchout_location;
                                    if (targetLoc && targetLoc.lat && targetLoc.lng) {
                                        onFocusMap([parseFloat(targetLoc.lat), parseFloat(targetLoc.lng)]);
                                    }
                                }
                            }}
                        >
                            <SewingPinFilledIcon /> Focus on Map
                        </Button>

                        <Button variant="outline" color="gray" size="2" onClick={onClose}>
                            Close
                        </Button>
                    </Flex>
                </Box>
            </Box>
        </Box>
    );
});

OfficerDetailModal.displayName = 'OfficerDetailModal';
export default OfficerDetailModal;
