import React from 'react';
import { Box, Flex, Text, Badge, Button, IconButton } from '@radix-ui/themes';
import {
    Cross2Icon,
    DownloadIcon,
    CopyIcon,
    CheckIcon,
    DrawingPinIcon,
    PersonIcon,
    ClockIcon
} from '@radix-ui/react-icons';

export const PhotoTelemetryLightbox = React.memo(({
    photoData,
    onClose
}) => {
    const [copied, setCopied] = React.useState(false);

    if (!photoData || !photoData.url) return null;

    const {
        url,
        title,
        officerName,
        designation,
        timestamp,
        location,
        type // 'punchin' or 'punchout'
    } = photoData;

    const coordString = location && location.lat && location.lng
        ? `${parseFloat(location.lat).toFixed(6)}, ${parseFloat(location.lng).toFixed(6)}`
        : null;

    const handleCopyCoords = () => {
        if (coordString) {
            navigator.clipboard.writeText(coordString);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <Box
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(16px)',
                WebkitBackdropFilter: 'blur(16px)',
                zIndex: 99999,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: 24,
                animation: 'fadeIn 0.2s ease-out'
            }}
            onClick={onClose}
        >
            {/* Close Button Top Right */}
            <IconButton
                size="3"
                variant="solid"
                color="gray"
                highContrast
                style={{
                    position: 'absolute',
                    top: 24,
                    right: 24,
                    borderRadius: '50%',
                    cursor: 'pointer',
                    zIndex: 10
                }}
                onClick={(e) => {
                    e.stopPropagation();
                    onClose();
                }}
                aria-label="Close photo preview"
            >
                <Cross2Icon style={{ width: 22, height: 22 }} />
            </IconButton>

            {/* Modal Card */}
            <Box
                style={{
                    maxWidth: '90vw',
                    maxHeight: '90vh',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    background: 'var(--color-panel-solid, var(--color-surface, #ffffff))',
                    border: '1px solid var(--gray-a5)',
                    borderRadius: 'var(--radius-4)',
                    boxShadow: 'var(--shadow-6, 0 25px 60px -15px rgba(0, 0, 0, 0.5))',
                    overflow: 'hidden',
                }}
                onClick={(e) => e.stopPropagation()}
            >
                {/* Photo Header Bar */}
                <Box
                    p="3"
                    style={{
                        width: '100%',
                        borderBottom: '1px solid var(--gray-a4)',
                        background: 'var(--gray-a2)'
                    }}
                >
                    <Flex justify="between" align="center" gap="3" px="2">
                        <Flex align="center" gap="2">
                            <PersonIcon style={{ color: 'var(--blue-9)', width: 18, height: 18 }} />
                            <Box>
                                <Text size="2" weight="bold" style={{ color: 'var(--gray-12)' }}>
                                    {officerName || 'Officer Photo'}
                                </Text>
                                {designation && (
                                    <Text size="1" color="gray" style={{ display: 'block' }}>
                                        {designation}
                                    </Text>
                                )}
                            </Box>
                        </Flex>

                        <Badge
                            size="1"
                            color={type === 'punchin' ? 'green' : type === 'punchout' ? 'red' : 'blue'}
                            variant="solid"
                        >
                            {type === 'punchin' ? 'Check-In Photo' : type === 'punchout' ? 'Check-Out Photo' : (title || 'Verification Selfie')}
                        </Badge>
                    </Flex>
                </Box>

                {/* Photo Viewer Container */}
                <Box
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: 16,
                        maxHeight: '65vh',
                        minWidth: 320,
                        maxWidth: 720,
                        overflow: 'hidden'
                    }}
                >
                    <img
                        src={url}
                        alt="Officer Telemetry Verification"
                        style={{
                            maxWidth: '100%',
                            maxHeight: '60vh',
                            objectFit: 'contain',
                            borderRadius: 'var(--radius-3)',
                            border: '1px solid var(--gray-a4)',
                            boxShadow: 'var(--shadow-4)'
                        }}
                    />
                </Box>

                {/* Telemetry HUD Bottom Bar */}
                <Box
                    p="3"
                    style={{
                        width: '100%',
                        borderTop: '1px solid var(--gray-a4)',
                        background: 'var(--gray-a2)'
                    }}
                >
                    <Flex justify="between" align="center" gap="3" wrap="wrap" px="2">
                        {/* Time & GPS */}
                        <Flex align="center" gap="4" wrap="wrap">
                            {timestamp && (
                                <Flex align="center" gap="1">
                                    <ClockIcon style={{ color: 'var(--purple-9)', width: 14, height: 14 }} />
                                    <Text size="1" style={{ color: 'var(--gray-12)' }}>
                                        {timestamp}
                                    </Text>
                                </Flex>
                            )}

                            {coordString && (
                                <Flex align="center" gap="2">
                                    <DrawingPinIcon style={{ color: 'var(--green-9)', width: 14, height: 14 }} />
                                    <Text size="1" style={{ color: 'var(--gray-12)', fontFamily: 'monospace' }}>
                                        {coordString}
                                    </Text>
                                    <Button
                                        size="1"
                                        variant="ghost"
                                        color="gray"
                                        style={{ cursor: 'pointer', padding: '0 4px', height: 20 }}
                                        onClick={handleCopyCoords}
                                    >
                                        {copied ? <CheckIcon style={{ color: 'var(--green-9)' }} /> : <CopyIcon />}
                                        <span style={{ fontSize: 10 }}>{copied ? 'Copied' : 'Copy'}</span>
                                    </Button>
                                </Flex>
                            )}
                        </Flex>

                        {/* Direct Download Button */}
                        <a
                            href={url}
                            target="_blank"
                            rel="noopener noreferrer"
                            download
                            style={{ textDecoration: 'none' }}
                        >
                            <Button size="1" variant="soft" color="blue" style={{ cursor: 'pointer' }}>
                                <DownloadIcon />
                                Download HD
                            </Button>
                        </a>
                    </Flex>
                </Box>
            </Box>
        </Box>
    );
});

PhotoTelemetryLightbox.displayName = 'PhotoTelemetryLightbox';
