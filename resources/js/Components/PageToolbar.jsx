import React from 'react';
import { Flex, Box, Button, Select, IconButton, Tooltip } from '@radix-ui/themes';
import { TableIcon, StackIcon, ReloadIcon, PlusIcon, DownloadIcon } from '@radix-ui/react-icons';

export default function PageToolbar({
    // Left slot
    leftSlot = null,
    
    // View Mode Toggle Props
    viewMode = null,
    onViewModeChange = null,
    showViewToggle = false,
    
    // Per Page Selector Props
    perPage = null,
    onPerPageChange = null,
    perPageOptions = [10, 25, 50, 100],
    
    // Refresh Props
    onRefresh = null,
    refreshLoading = false,
    
    // Export Props
    onExport = null,
    exportLabel = 'Export',
    
    // Primary Action (Add/Create) Props
    onAdd = null,
    addLabel = 'Add New',
    addIcon = <PlusIcon width="16" height="16" />,
    canAdd = true,
    
    // Custom extra action elements
    extraActions = null,
    children = null,
    
    // Layout
    mb = '4',
    style,
    className,
}) {
    return (
        <Flex
            direction={{ initial: 'column', sm: 'row' }}
            gap="3"
            align={{ initial: 'stretch', sm: 'center' }}
            justify="between"
            wrap="wrap"
            mb={mb}
            className={className}
            style={style}
        >
            {/* Left Slot: Search, Filter, or custom */}
            <Box style={{ flex: 1, minWidth: 240 }}>
                {leftSlot}
                {children}
            </Box>

            {/* Right Slot: View Toggle, Per Page, Refresh, Export, Add */}
            <Flex gap="2" align="center" wrap="wrap" justify={{ initial: 'start', sm: 'end' }}>
                {/* View Mode Toggle (Table / Grid) */}
                {showViewToggle && onViewModeChange && (
                    <Flex
                        style={{
                            background: 'var(--aero-surface, var(--color-background))',
                            border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))',
                            borderRadius: 10,
                            padding: 2,
                        }}
                    >
                        <IconButton
                            size="1"
                            variant={viewMode === 'table' ? 'solid' : 'ghost'}
                            color={viewMode === 'table' ? 'indigo' : 'gray'}
                            onClick={() => onViewModeChange('table')}
                            aria-label="Table View"
                            style={{ borderRadius: 8, cursor: 'pointer' }}
                        >
                            <TableIcon width="16" height="16" />
                        </IconButton>
                        <IconButton
                            size="1"
                            variant={viewMode === 'grid' || viewMode === 'cards' ? 'solid' : 'ghost'}
                            color={viewMode === 'grid' || viewMode === 'cards' ? 'indigo' : 'gray'}
                            onClick={() => onViewModeChange('grid')}
                            aria-label="Grid View"
                            style={{ borderRadius: 8, cursor: 'pointer' }}
                        >
                            <StackIcon width="16" height="16" />
                        </IconButton>
                    </Flex>
                )}

                {/* Per Page Selector */}
                {onPerPageChange && perPage && (
                    <Select.Root
                        size="2"
                        value={String(perPage)}
                        onValueChange={(val) => onPerPageChange(Number(val))}
                    >
                        <Select.Trigger style={{ borderRadius: 10, minWidth: 90 }} />
                        <Select.Content>
                            {perPageOptions.map((opt) => (
                                <Select.Item key={opt} value={String(opt)}>
                                    {opt} / page
                                </Select.Item>
                            ))}
                        </Select.Content>
                    </Select.Root>
                )}

                {/* Refresh Button */}
                {onRefresh && (
                    <Tooltip content="Refresh data">
                        <Button
                            size="2"
                            variant="soft"
                            color="gray"
                            onClick={onRefresh}
                            disabled={refreshLoading}
                            style={{ borderRadius: 10, cursor: 'pointer' }}
                        >
                            <ReloadIcon
                                width="16"
                                height="16"
                                className={refreshLoading ? 'animate-spin' : ''}
                            />
                        </Button>
                    </Tooltip>
                )}

                {/* Export Button */}
                {onExport && (
                    <Button
                        size="2"
                        variant="soft"
                        color="green"
                        onClick={onExport}
                        style={{ borderRadius: 10, cursor: 'pointer' }}
                    >
                        <DownloadIcon width="16" height="16" />
                        {exportLabel}
                    </Button>
                )}

                {/* Custom Extra Actions */}
                {extraActions}

                {/* Add / Create Primary Button */}
                {canAdd && onAdd && (
                    <Button
                        size="2"
                        color="blue"
                        onClick={onAdd}
                        style={{
                            borderRadius: 10,
                            fontFamily: `'Space Grotesk', system-ui, sans-serif`,
                            fontWeight: 600,
                            cursor: 'pointer',
                        }}
                    >
                        {addIcon}
                        {addLabel}
                    </Button>
                )}
            </Flex>
        </Flex>
    );
}
