import React from 'react';
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
                        background: 'var(--color-surface)',
                        backdropFilter: 'blur(16px)',
                        WebkitBackdropFilter: 'blur(16px)',
                        borderRadius: 'var(--radius-4)',
                        border: '1px solid var(--gray-a5)',
                        boxShadow: 'var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))',
                    }}
                >
                    {/* Search Field */}
                    <Box style={{ width: 190 }}>
                        <TextField.Root
                            size="1"
                            variant="surface"
                            placeholder="Search officer / ID..."
                            value={searchQuery}
                            onChange={(e) => onSearchChange(e.target.value)}
                        >
                            <TextField.Slot>
                                <MagnifyingGlassIcon style={{ color: 'var(--gray-9)' }} />
                            </TextField.Slot>
                            {searchQuery && (
                                <TextField.Slot>
                                    <IconButton
                                        size="1"
                                        variant="ghost"
                                        color="gray"
                                        style={{ cursor: 'pointer' }}
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
                            color="gray"
                            onClick={() => onStatusFilterChange('all')}
                            style={{ cursor: 'pointer', fontWeight: 600 }}
                        >
                            All ({stats.total})
                        </Button>
                        <Button
                            size="1"
                            variant={statusFilter === 'active' ? 'solid' : 'soft'}
                            color="green"
                            onClick={() => onStatusFilterChange('active')}
                            style={{ cursor: 'pointer', fontWeight: 600 }}
                        >
                            🟢 Active ({stats.active})
                        </Button>
                        <Button
                            size="1"
                            variant={statusFilter === 'completed' ? 'solid' : 'soft'}
                            color="blue"
                            onClick={() => onStatusFilterChange('completed')}
                            style={{ cursor: 'pointer', fontWeight: 600 }}
                        >
                            ✅ Done ({stats.completed})
                        </Button>
                    </Flex>
                </Flex>

                {/* Right Controls: Tile Switcher, Layer Menu, Fit Bounds & Drawer Toggle */}
                <Flex
                    align="center"
                    gap="2"
                    p="2"
                    style={{
                        background: 'var(--color-surface)',
                        backdropFilter: 'blur(16px)',
                        WebkitBackdropFilter: 'blur(16px)',
                        borderRadius: 'var(--radius-4)',
                        border: '1px solid var(--gray-a5)',
                        boxShadow: 'var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))',
                    }}
                >
                    {/* Multi-Tile Base Map Selector */}
                    <DropdownMenu.Root>
                        <DropdownMenu.Trigger>
                            <Button size="1" variant="soft" color="gray" style={{ cursor: 'pointer', fontWeight: 600 }}>
                                <GlobeIcon />
                                {TILE_LAYERS[currentTileId]?.name || 'Basemap'}
                            </Button>
                        </DropdownMenu.Trigger>
                        <DropdownMenu.Content variant="solid" size="1">
                            <DropdownMenu.Label>Select Map Tile</DropdownMenu.Label>
                            {Object.values(TILE_LAYERS).map((tile) => (
                                <DropdownMenu.Item
                                    key={tile.id}
                                    onClick={() => onTileChange(tile.id)}
                                    style={{ cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'between' }}
                                >
                                    <span>{tile.name}</span>
                                    {currentTileId === tile.id && <CheckIcon style={{ marginLeft: 8 }} />}
                                </DropdownMenu.Item>
                            ))}
                        </DropdownMenu.Content>
                    </DropdownMenu.Root>

                    {/* Layer Visibility Menu */}
                    <DropdownMenu.Root>
                        <DropdownMenu.Trigger>
                            <Button size="1" variant="soft" color="gray" style={{ cursor: 'pointer', fontWeight: 600 }}>
                                <LayersIcon />
                                Layers
                            </Button>
                        </DropdownMenu.Trigger>
                        <DropdownMenu.Content variant="solid" size="1">
                            <DropdownMenu.Label>Toggle Overlays</DropdownMenu.Label>
                            <DropdownMenu.Item
                                onClick={() => onToggleLayer('geofences')}
                                style={{ cursor: 'pointer' }}
                            >
                                <Flex align="center" gap="2">
                                    {layerVisibility.geofences ? <EyeOpenIcon style={{ color: 'var(--purple-9)' }} /> : <EyeClosedIcon />}
                                    <span>Geofence Zones</span>
                                </Flex>
                            </DropdownMenu.Item>
                            <DropdownMenu.Item
                                onClick={() => onToggleLayer('waypoints')}
                                style={{ cursor: 'pointer' }}
                            >
                                <Flex align="center" gap="2">
                                    {layerVisibility.waypoints ? <EyeOpenIcon style={{ color: 'var(--cyan-9)' }} /> : <EyeClosedIcon />}
                                    <span>Route Waypoints</span>
                                </Flex>
                            </DropdownMenu.Item>
                            <DropdownMenu.Item
                                onClick={() => onToggleLayer('trajectories')}
                                style={{ cursor: 'pointer' }}
                            >
                                <Flex align="center" gap="2">
                                    {layerVisibility.trajectories ? <EyeOpenIcon style={{ color: 'var(--blue-9)' }} /> : <EyeClosedIcon />}
                                    <span>Patrol Trajectories</span>
                                </Flex>
                            </DropdownMenu.Item>
                        </DropdownMenu.Content>
                    </DropdownMenu.Root>

                    {/* Fit Bounds 1-Click */}
                    <Button
                        size="1"
                        variant="soft"
                        color="gray"
                        onClick={onFitBounds}
                        style={{ cursor: 'pointer' }}
                        title="Fit all markers in view"
                    >
                        <SewingPinFilledIcon />
                        Fit All
                    </Button>

                    {/* Refresh Button */}
                    <IconButton
                        size="1"
                        variant="soft"
                        color="blue"
                        onClick={onRefresh}
                        disabled={isRefreshing}
                        style={{ cursor: 'pointer' }}
                        title="Refresh live coordinates"
                    >
                        <ReloadIcon className={isRefreshing ? 'animate-spin' : ''} />
                    </IconButton>

                    {/* Toggle Slide-over Team Roster */}
                    <Button
                        size="1"
                        variant={isDrawerOpen ? 'solid' : 'soft'}
                        color={isDrawerOpen ? 'blue' : 'gray'}
                        onClick={onToggleDrawer}
                        style={{ cursor: 'pointer', fontWeight: 600 }}
                    >
                        <PersonIcon />
                        Roster ({stats.total})
                    </Button>

                    {/* Fullscreen Toggle */}
                    <IconButton
                        size="1"
                        variant="soft"
                        color="gray"
                        onClick={onToggleFullscreen}
                        style={{ cursor: 'pointer' }}
                        title={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'}
                    >
                        {isFullscreen ? <ExitFullScreenIcon /> : <EnterFullScreenIcon />}
                    </IconButton>
                </Flex>
            </Flex>
        </Box>
    );
});

MapHudControls.displayName = 'MapHudControls';
