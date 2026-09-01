import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import App from '@/Layouts/App';
import ErrorBoundary from '@/Components/ErrorBoundary/ErrorBoundary';
import { showToast } from '@/utils/toastUtils';
import {
    useNotificationTypes,
    useUpdateNotificationType,
} from '@/api/queries/useNotificationSettingsQuery';
import {
    Box,
    Flex,
    Text,
    Heading,
    Table,
    Badge,
    Switch,
    Checkbox,
    Separator,
} from '@radix-ui/themes';
import { BellIcon } from '@radix-ui/react-icons';
import { Panel } from '@/Components/ui/Panel';
import { TableLoadingSkeleton } from '@/Components/LoadingSkeleton';

const CHANNELS = [
    { key: 'database', label: 'In-app' },
    { key: 'push', label: 'Push' },
    { key: 'mail', label: 'Email' },
];

// Fallback only — the live role list is passed from the server (availableRoles prop).
const DEFAULT_ROLES = ['Employee', 'Manager', 'Super Administrator', 'Administrator', 'HR Manager'];

function NotificationTypeRow({ type, roles = DEFAULT_ROLES }) {
    const [localType, setLocalType] = useState(type);
    const updateMutation = useUpdateNotificationType();

    const save = (patch) => {
        const next = { ...localType, ...patch };
        setLocalType(next);
        updateMutation.mutate(
            {
                id: next.id,
                default_channels: next.default_channels,
                locked_channels: next.locked_channels,
                recipient_roles: next.recipient_roles,
                is_active: next.is_active,
            },
            {
                onSuccess: () => showToast.success(`${next.label} updated.`),
                onError: () => {
                    setLocalType(localType);
                    showToast.error('Failed to save changes.');
                },
            }
        );
    };

    const toggleChannel = (channelKey) => {
        const isLocked = (localType.locked_channels || []).includes(channelKey);
        if (isLocked) return; // locked channels cannot be toggled off
        const current = localType.default_channels || [];
        const next = current.includes(channelKey)
            ? current.filter((c) => c !== channelKey)
            : [...current, channelKey];
        save({ default_channels: next });
    };

    const toggleRole = (role) => {
        const current = localType.recipient_roles || [];
        const next = current.includes(role)
            ? current.filter((r) => r !== role)
            : [...current, role];
        save({ recipient_roles: next });
    };

    const toggleActive = (active) => {
        save({ is_active: active });
    };

    return (
        <Table.Row align="center">
            <Table.RowHeaderCell>
                <Flex direction="column" gap="1">
                    <Text size="2" weight="medium">{localType.label}</Text>
                    {localType.description && (
                        <Text size="1" color="gray">{localType.description}</Text>
                    )}
                </Flex>
            </Table.RowHeaderCell>
            {CHANNELS.map(({ key, label }) => {
                const isLocked = (localType.locked_channels || []).includes(key);
                const isChecked = (localType.default_channels || []).includes(key);
                return (
                    <Table.Cell key={key}>
                        <Flex align="center" gap="1">
                            <Checkbox
                                checked={isChecked}
                                disabled={isLocked}
                                onCheckedChange={() => toggleChannel(key)}
                            />
                            {isLocked && (
                                <Badge size="1" color="gray" variant="surface">Req</Badge>
                            )}
                        </Flex>
                    </Table.Cell>
                );
            })}
            <Table.Cell>
                <Flex wrap="wrap" gap="1">
                    {roles.map((role) => {
                        const isSelected = (localType.recipient_roles || []).includes(role);
                        return (
                            <Badge
                                key={role}
                                size="1"
                                variant={isSelected ? 'solid' : 'surface'}
                                color={isSelected ? 'blue' : 'gray'}
                                style={{ cursor: 'pointer', userSelect: 'none' }}
                                onClick={() => toggleRole(role)}
                            >
                                {role}
                            </Badge>
                        );
                    })}
                </Flex>
            </Table.Cell>
            <Table.Cell>
                <Switch
                    checked={localType.is_active}
                    onCheckedChange={toggleActive}
                />
            </Table.Cell>
        </Table.Row>
    );
}

function CategorySection({ category, types, roles = DEFAULT_ROLES }) {
    return (
        <Box mb="4">
            <Flex align="center" gap="2" mb="2">
                <Badge color="blue" variant="soft" style={{ textTransform: 'capitalize', fontWeight: 700, borderRadius: 8 }}>
                    {category}
                </Badge>
            </Flex>
            <Box style={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', background: 'var(--aero-surface, var(--color-background))' }}>
                <Table.Root size="2" style={{ minWidth: 780, width: '100%' }}>
                    <Table.Header style={{
                        position: 'sticky',
                        top: 0,
                        zIndex: 2,
                        background: 'var(--aero-surface, var(--color-background))',
                        backdropFilter: 'blur(8px)',
                        boxShadow: '0 1px 0 var(--dl-border-color, rgba(0,0,0,0.06))'
                    }}>
                        <Table.Row>
                            <Table.ColumnHeaderCell style={{ minWidth: 200, background: 'inherit' }}>Notification Type</Table.ColumnHeaderCell>
                            {CHANNELS.map(({ key, label }) => (
                                <Table.ColumnHeaderCell key={key} style={{ minWidth: 80, background: 'inherit' }}>{label}</Table.ColumnHeaderCell>
                            ))}
                            <Table.ColumnHeaderCell style={{ minWidth: 220, background: 'inherit' }}>Recipients</Table.ColumnHeaderCell>
                            <Table.ColumnHeaderCell style={{ minWidth: 80, background: 'inherit' }}>Active</Table.ColumnHeaderCell>
                        </Table.Row>
                    </Table.Header>
                    <Table.Body>
                        {types.map((type) => (
                            <NotificationTypeRow key={type.id} type={type} roles={roles} />
                        ))}
                    </Table.Body>
                </Table.Root>
            </Box>
        </Box>
    );
}

const NotificationSettings = ({ title }) => {
    const { data, isLoading, isError } = useNotificationTypes();
    const pageProps = usePage().props;
    const roles = Array.isArray(pageProps?.availableRoles) && pageProps.availableRoles.length
        ? pageProps.availableRoles
        : DEFAULT_ROLES;

    const types = Array.isArray(data) ? data : (data?.data ?? []);

    const grouped = types.reduce((acc, type) => {
        const cat = type.category || 'general';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(type);
        return acc;
    }, {});

    return (
        <App>
            <Head title={title ?? 'Notification Settings'} />
            <ErrorBoundary>
                <Flex justify="center" p={{ initial: '3', sm: '4', md: '5' }}>
                    <Box style={{ width: '100%', maxWidth: 2000 }}>
                        <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '24px 20px' }}>
                            {/* ── Page Header ── */}
                            <Box mb="4">
                                <Flex direction={{ initial: 'column', sm: 'row' }} align={{ initial: 'start', sm: 'center' }} justify="between" gap="4">
                                    <Flex align="center" gap="3">
                                        <Box p="3" style={{ background: 'var(--blue-a3)', borderRadius: 12, border: '1px solid var(--blue-a5)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <BellIcon style={{ width: 22, height: 22, color: 'var(--blue-9)' }} />
                                        </Box>
                                        <Box>
                                            <Heading size="5" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 800, letterSpacing: '-0.02em' }}>Notification Settings</Heading>
                                            <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>
                                                Configure channels and recipient roles for each notification category.
                                            </Text>
                                        </Box>
                                    </Flex>
                                </Flex>
                            </Box>

                            <Separator size="4" mb="4" style={{ background: 'var(--dl-border-color, rgba(0,0,0,0.06))' }} />

                            {isLoading && (
                                <TableLoadingSkeleton rows={6} cols={5} />
                            )}

                            {isError && (
                                <Text color="red" size="2">Failed to load notification types. Please refresh.</Text>
                            )}

                            {!isLoading && !isError && Object.keys(grouped).length === 0 && (
                                <Text color="gray" size="2">No notification types found. Run the NotificationTypeSeeder first.</Text>
                            )}

                            {!isLoading && !isError && Object.entries(grouped).map(([category, catTypes]) => (
                                <CategorySection key={category} category={category} types={catTypes} roles={roles} />
                            ))}
                        </Panel>
                    </Box>
                </Flex>
            </ErrorBoundary>
        </App>
    );
};

export default NotificationSettings;
