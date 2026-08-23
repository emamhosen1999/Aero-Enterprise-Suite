import React, { useState } from 'react';
import { Box, Flex, Text, TextField, Badge, Button, IconButton, DropdownMenu } from '@radix-ui/themes';
import {
    MagnifyingGlassIcon,
    Cross2Icon,
    LayersIcon,
    GlobeIcon,
    EnterFullScreenIcon,
    ExitFullScreenIcon,
    ReloadIcon,
    PersonIcon,
    ViewVerticalIcon,
    CheckIcon,
    EyeOpenIcon,
    EyeClosedIcon,
    SewingPinFilledIcon
} from '@radix-ui/react-icons';
import { TILE_LAYERS, THEME_COLORS } from './mapConstants';

export const MapHudControls = React.memo(({
    searchQuery,
    onSearchChange,
    statusFilter,
    onStatusFilterChange,
    stats,
    currentTileId,
    onTileChange,
    layerVisibility,
    onToggleLayer,
    onFitBounds,
    onRefresh,
    isRefreshing,
    isDrawerOpen,
    onToggleDrawer,
    isFullscreen,
    onToggleFullscreen
}) => {
    return (
        <Box
            style={{
                position: 'absolute',
                top: 14,
                left: 14,
                right: 14,
                zIndex: 1000,
                pointerEvents: 'none' // Let clicks pass through empty spaces
            }}
        >
            <Flex
                gap="2"
                align="center"
                justify="between"
                wrap="wrap"
                style={{ pointerEvents: 'auto' }}
            >
                {/* Left Controls: Search Bar & Status Chips */}
                <Flex
                    align="center"
                    gap="2"
                    wrap="wrap"
                    p="2"
                    style={{
                        background: 'rgba(15, 23, 42, 0.85)',
                        backdropFilter: 'blur(16px)',
                        WebkitBackdropFilter: 'blur(16px)',
                        borderRadius: 'var(--radius-4)',
                        border: '1px solid rgba(255, 255, 255, 0.15)',
                        boxShadow: '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
                    }}
                >
                    {/* Search Field */}
                    <Box style={{ width: 200 }}>
                        <TextField.Root
                            size="1"
                            placeholder="Search officer or ID..."
                            value={searchQuery}
                            onChange={(e) => onSearchChange(e.target.value)}
                            style={{
                                background: 'rgba(255, 255, 255, 0.1)',
                                color: '#ffffff',
                                border: '1px solid rgba(255, 255, 255, 0.2)',
                                borderRadius: 'var(--radius-2)',
                            }}
                        >
                            <TextField.Slot>
                                <MagnifyingGlassIcon style={{ color: '#94a3b8' }} />
                            </TextField.Slot>
                            {searchQuery && (
                                <TextField.Slot>
                                    <IconButton
                                        size="1"
                                        variant="ghost"
                                        style={{ color: '#cbd5e1', cursor: 'pointer' }}
                                        onClick={() => onSearchChange('')}
                                    >
                                        <Cross2Icon />
                                    </IconButton>
                                </TextField.Slot>
                            )}
                        </TextField.Root>
                    </Box>

                    {/* Status Filter Chips */}
                    <Flex align="center" gap="1">
                        <Button
                            size="1"
                            variant={statusFilter === 'all' ? 'solid' : 'soft'}
                            color={statusFilter === 'all' ? 'blue' : 'gray'}
                            style={{
                                cursor: 'pointer',
                                borderRadius: 'var(--radius-2)',
                                fontSize: 11
                            }}
                            onClick={() => onStatusFilterChange('all')}
                        >
                            All ({stats?.total || 0})
                        </Button>

                        <Button
                            size="1"
                            variant={statusFilter === 'active' ? 'solid' : 'soft'}
                            color={statusFilter === 'active' ? 'green' : 'gray'}
                            style={{
                                cursor: 'pointer',
                                borderRadius: 'var(--radius-2)',
                                fontSize: 11
                            }}
                            onClick={() => onStatusFilterChange('active')}
                        >
                            🟢 Active ({stats?.checkedIn ?? stats?.active ?? 0})
                        </Button>

                        <Button
                            size="1"
                            variant={statusFilter === 'completed' ? 'solid' : 'soft'}
                            color={statusFilter === 'completed' ? 'blue' : 'gray'}
                            style={{
                                cursor: 'pointer',
                                borderRadius: 'var(--radius-2)',
                                fontSize: 11
                            }}
                            onClick={() => onStatusFilterChange('completed')}
                        >
                            ✅ Done ({stats?.completed || 0})
                        </Button>
                    </Flex>
                </Flex>

                {/* Right Controls: Tile Switcher, Layer Toggles, Fit Bounds, Roster, Fullscreen */}
                <Flex
                    align="center"
                    gap="2"
                    p="2"
                    style={{
                        background: 'rgba(15, 23, 42, 0.85)',
                        backdropFilter: 'blur(16px)',
                        WebkitBackdropFilter: 'blur(16px)',
                        borderRadius: 'var(--radius-4)',
                        border: '1px solid rgba(255, 255, 255, 0.15)',
                        boxShadow: '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
                    }}
                >
                    {/* Tile Layer Selector */}
                    <DropdownMenu.Root>
                        <DropdownMenu.Trigger>
                            <Button
                                size="1"
                                variant="soft"
                                color="gray"
                                style={{
                                    color: '#ffffff',
                                    background: 'rgba(255, 255, 255, 0.12)',
                                    cursor: 'pointer'
                                }}
                            >
                                <GlobeIcon />
                                <Text size="1" style={{ fontSize: 11 }}>
                                    {TILE_LAYERS[currentTileId]?.name || 'Base Map'}
                                </Text>
                            </Button>
                        </DropdownMenu.Trigger>
                        <DropdownMenu.Content
                            style={{
                                background: 'rgba(15, 23, 42, 0.95)',
                                backdropFilter: 'blur(12px)',
                                border: '1px solid rgba(255, 255, 255, 0.2)',
                                color: '#ffffff',
                                zIndex: 9999
                            }}
                        >
                            <DropdownMenu.Label style={{ color: '#94a3b8', fontSize: 10 }}>
                                MAP TILE THEME
                            </DropdownMenu.Label>
                            {Object.values(TILE_LAYERS).map((layer) => (
                                <DropdownMenu.Item
                                    key={layer.id}
                                    style={{
                                        cursor: 'pointer',
                                        color: currentTileId === layer.id ? '#38bdf8' : '#e2e8f0',
                                        fontWeight: currentTileId === layer.id ? 700 : 400
                                    }}
                                    onClick={() => onTileChange(layer.id)}
                                >
                                    <Flex align="center" justify="between" style={{ width: '100%' }}>
                                        <Text size="1">{layer.name}</Text>
                                        {currentTileId === layer.id && <CheckIcon style={{ width: 14, height: 14 }} />}
                                    </Flex>
                                </DropdownMenu.Item>
                            ))}
                        </DropdownMenu.Content>
                    </DropdownMenu.Root>

                    {/* Layer Visibility Menu */}
                    <DropdownMenu.Root>
                        <DropdownMenu.Trigger>
                            <Button
                                size="1"
                                variant="soft"
                                color="gray"
                                style={{
                                    color: '#ffffff',
                                    background: 'rgba(255, 255, 255, 0.12)',
                                    cursor: 'pointer'
                                }}
                            >
                                <LayersIcon />
                                <Text size="1" style={{ fontSize: 11 }}>Layers</Text>
                            </Button>
                        </DropdownMenu.Trigger>
                        <DropdownMenu.Content
                            style={{
                                background: 'rgba(15, 23, 42, 0.95)',
                                backdropFilter: 'blur(12px)',
                                border: '1px solid rgba(255, 255, 255, 0.2)',
                                color: '#ffffff',
                                zIndex: 9999
                            }}
                        >
                            <DropdownMenu.Label style={{ color: '#94a3b8', fontSize: 10 }}>
                                TOGGLE MAP OVERLAYS
                            </DropdownMenu.Label>

                            <DropdownMenu.Item
                                style={{ cursor: 'pointer', color: '#e2e8f0' }}
                                onClick={() => onToggleLayer('geofences')}
                            >
                                <Flex align="center" justify="between" style={{ width: '100%' }}>
                                    <Text size="1">Geofence Zones</Text>
                                    {layerVisibility.geofences ? <EyeOpenIcon style={{ color: '#34d399' }} /> : <EyeClosedIcon style={{ color: '#94a3b8' }} />}
                                </Flex>
                            </DropdownMenu.Item>

                            <DropdownMenu.Item
                                style={{ cursor: 'pointer', color: '#e2e8f0' }}
                                onClick={() => onToggleLayer('waypoints')}
                            >
                                <Flex align="center" justify="between" style={{ width: '100%' }}>
                                    <Text size="1">Route Waypoints</Text>
                                    {layerVisibility.waypoints ? <EyeOpenIcon style={{ color: '#34d399' }} /> : <EyeClosedIcon style={{ color: '#94a3b8' }} />}
                                </Flex>
                            </DropdownMenu.Item>

                            <DropdownMenu.Item
                                style={{ cursor: 'pointer', color: '#e2e8f0' }}
                                onClick={() => onToggleLayer('trajectories')}
                            >
                                <Flex align="center" justify="between" style={{ width: '100%' }}>
                                    <Text size="1">Patrol Trajectories</Text>
                                    {layerVisibility.trajectories ? <EyeOpenIcon style={{ color: '#34d399' }} /> : <EyeClosedIcon style={{ color: '#94a3b8' }} />}
                                </Flex>
                            </DropdownMenu.Item>
                        </DropdownMenu.Content>
                    </DropdownMenu.Root>

                    {/* Fit All Bounds Button */}
                    <IconButton
                        size="1"
                        variant="soft"
                        color="gray"
                        style={{
                            color: '#ffffff',
                            background: 'rgba(255, 255, 255, 0.12)',
                            cursor: 'pointer'
                        }}
                        title="Fit all markers in view"
                        onClick={onFitBounds}
                    >
                        <SewingPinFilledIcon />
                    </IconButton>

                    {/* Refresh Button */}
                    <IconButton
                        size="1"
                        variant="soft"
                        color="gray"
                        style={{
                            color: '#ffffff',
                            background: 'rgba(255, 255, 255, 0.12)',
                            cursor: 'pointer'
                        }}
                        title="Refresh live locations"
                        onClick={onRefresh}
                        disabled={isRefreshing}
                    >
                        <ReloadIcon className={isRefreshing ? 'animate-spin' : ''} />
                    </IconButton>

                    {/* Toggle Team Roster Drawer */}
                    <Button
                        size="1"
                        variant={isDrawerOpen ? 'solid' : 'soft'}
                        color={isDrawerOpen ? 'blue' : 'gray'}
                        style={{
                            color: '#ffffff',
                            cursor: 'pointer'
                        }}
                        onClick={onToggleDrawer}
                    >
                        <PersonIcon />
                        <Text size="1" style={{ fontSize: 11 }}>Team ({stats?.total || 0})</Text>
                    </Button>

                    {/* Fullscreen Button */}
                    <IconButton
                        size="1"
                        variant="soft"
                        color="gray"
                        style={{
                            color: '#ffffff',
                            background: 'rgba(255, 255, 255, 0.12)',
                            cursor: 'pointer'
                        }}
                        title={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'}
                        onClick={onToggleFullscreen}
                    >
                        {isFullscreen ? <ExitFullScreenIcon /> : <EnterFullScreenIcon />}
                    </IconButton>
                </Flex>
            </Flex>
        </Box>
    );
});

MapHudControls.displayName = 'MapHudControls';
export default MapHudControls;
