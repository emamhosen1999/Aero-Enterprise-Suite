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
                background: 'rgba(5, 10, 20, 0.94)',
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
            <button
                style={{
                    position: 'absolute',
                    top: 24,
                    right: 24,
                    width: 44,
                    height: 44,
                    borderRadius: '50%',
                    background: 'rgba(255, 255, 255, 0.12)',
                    border: '1px solid rgba(255, 255, 255, 0.25)',
                    color: 'white',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transition: 'all 0.15s ease',
                    zIndex: 10
                }}
                onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.25)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.12)'; }}
                onClick={(e) => {
                    e.stopPropagation();
                    onClose();
                }}
                aria-label="Close photo preview"
            >
                <Cross2Icon style={{ width: 22, height: 22 }} />
            </button>

            {/* Modal Card */}
            <Box
                style={{
                    maxWidth: '90vw',
                    maxHeight: '90vh',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    background: 'rgba(15, 23, 42, 0.9)',
                    border: '1px solid rgba(255, 255, 255, 0.15)',
                    borderRadius: 'var(--radius-4)',
                    boxShadow: '0 25px 60px -15px rgba(0, 0, 0, 0.8)',
                    overflow: 'hidden',
                }}
                onClick={(e) => e.stopPropagation()}
            >
                {/* Photo Header Bar */}
                <Box
                    p="3"
                    style={{
                        width: '100%',
                        borderBottom: '1px solid rgba(255, 255, 255, 0.1)',
                        background: 'rgba(0, 0, 0, 0.3)'
                    }}
                >
                    <Flex justify="between" align="center" gap="3" px="2">
                        <Flex align="center" gap="2">
                            <PersonIcon style={{ color: '#38bdf8', width: 18, height: 18 }} />
                            <Box>
                                <Text size="2" weight="bold" style={{ color: '#ffffff' }}>
                                    {officerName || 'Officer Photo'}
                                </Text>
                                {designation && (
                                    <Text size="1" style={{ color: '#94a3b8', display: 'block' }}>
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
                            {type === 'punchin' ? 'Check-In Photo' : type === 'punchout' ? 'Check-Out Photo' : (title || 'Selfie')}
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
                            border: '1px solid rgba(255, 255, 255, 0.1)',
                            boxShadow: '0 8px 30px rgba(0,0,0,0.5)'
                        }}
                    />
                </Box>

                {/* Telemetry HUD Bottom Bar */}
                <Box
                    p="3"
                    style={{
                        width: '100%',
                        borderTop: '1px solid rgba(255, 255, 255, 0.1)',
                        background: 'rgba(0, 0, 0, 0.4)'
                    }}
                >
                    <Flex justify="between" align="center" gap="3" wrap="wrap" px="2">
                        {/* Time & GPS */}
                        <Flex align="center" gap="4" wrap="wrap">
                            {timestamp && (
                                <Flex align="center" gap="1">
                                    <ClockIcon style={{ color: '#a78bfa', width: 14, height: 14 }} />
                                    <Text size="1" style={{ color: '#cbd5e1' }}>
                                        {timestamp}
                                    </Text>
                                </Flex>
                            )}

                            {coordString && (
                                <Flex align="center" gap="2">
                                    <DrawingPinIcon style={{ color: '#34d399', width: 14, height: 14 }} />
                                    <Text size="1" style={{ color: '#cbd5e1', fontFamily: 'monospace' }}>
                                        {coordString}
                                    </Text>
                                    <Button
                                        size="1"
                                        variant="ghost"
                                        style={{ color: '#94a3b8', cursor: 'pointer', padding: '0 4px', height: 20 }}
                                        onClick={handleCopyCoords}
                                    >
                                        {copied ? <CheckIcon style={{ color: '#34d399' }} /> : <CopyIcon />}
                                        <Text size="1" style={{ fontSize: 10 }}>{copied ? 'Copied' : 'Copy'}</Text>
                                    </Button>
                                </Flex>
                            )}
                        </Flex>

                        {/* Download button */}
                        <Button
                            size="1"
                            variant="soft"
                            color="gray"
                            style={{ cursor: 'pointer' }}
                            onClick={() => {
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = `${officerName || 'officer'}-${type || 'photo'}.jpg`;
                                a.target = '_blank';
                                a.click();
                            }}
                        >
                            <DownloadIcon />
                            <Text size="1">Download</Text>
                        </Button>
                    </Flex>
                </Box>
            </Box>
        </Box>
    );
});

PhotoTelemetryLightbox.displayName = 'PhotoTelemetryLightbox';
export default PhotoTelemetryLightbox;
