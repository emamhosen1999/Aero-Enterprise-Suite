import { Panel } from '@/Components/ui/Panel';
import React, { useState } from 'react';
import { Head, usePage, Link } from '@inertiajs/react';
import { Box, Flex, Text, Heading, Grid, Badge, Avatar, Progress, Separator, Button } from '@radix-ui/themes';
import { CalendarIcon, CheckCircledIcon, BackpackIcon, LightningBoltIcon, ShadowIcon, ArrowRightIcon, PlusIcon } from '@radix-ui/react-icons';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';

import App from '@/Layouts/App.jsx';
import ErrorBoundary from '@/Components/ErrorBoundary/ErrorBoundary';
import PunchStatusCard from '@/Components/PunchStatusCard.jsx';
import AttendanceOverview from './Attendance/Components/AttendanceOverview';
import MyRequests from './Attendance/Components/MyRequests';
import SwapResponses from './Attendance/Components/SwapResponses';
import { requestJson } from '@/api/client';

// Forms for Quick Actions
import SwapRequestForm from '@/Forms/SwapRequestForm';
import RegularizationForm from '@/Forms/RegularizationForm';
import OvertimeRequestForm from '@/Forms/OvertimeRequestForm';

export default function EmployeeDashboard() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const currentMonth = dayjs().format('YYYY-MM');
    const queryClient = useQueryClient();

    // Modal trigger states
    const [swapOpen, setSwapOpen] = useState(false);
    const [regOpen, setRegOpen] = useState(false);
    const [otOpen, setOtOpen] = useState(false);

    // Fetch leave balances/stats
    const { data: leaveStats, isLoading: leavesLoading } = useQuery({
        queryKey: ['leaves-stats-dashboard'],
        queryFn: () => requestJson('get', '/leaves-stats'),
    });

    // Today's shift — rostered if assigned, else company default office hours
    const { data: todaySchedule } = useQuery({
        queryKey: ['my-schedule-today'],
        queryFn: () => requestJson('get', route('attendance.myScheduleToday')),
        staleTime: 5 * 60 * 1000,
    });

    const handleSaved = () => {
        ['my-regularizations', 'my-overtime', 'my-comp-off', 'my-swaps', 'swaps', 'awaiting-me']
            .forEach(key => queryClient.invalidateQueries({ queryKey: [key] }));
    };

    return (
        <>
            <Head title="Employee Dashboard" />
            <Box p={{ initial: '3', sm: '4', md: '5' }}>
                
                {/* ── Hero Card — matching mobile EmployeeHeroCard ── */}
                <Panel mb="4" style={{ background: 'var(--aero-surface, var(--gray-2))', borderRadius: 20, padding: '28px 20px' }}>
                    <Flex direction="column" align="center" gap="3">
                        <Avatar
                            size="7"
                            src={user?.profile_image_url}
                            fallback={user?.name?.substring(0, 2).toUpperCase()}
                            style={{ borderRadius: '50%', border: '2px solid var(--aero-accent, var(--accent-9))' }}
                        />
                        <Box style={{ textAlign: 'center' }}>
                            <Heading size="6" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontWeight: 900, letterSpacing: '-0.5px', fontSize: 26 }}>
                                {user?.name}
                            </Heading>
                            {user?.employee_id && (
                                <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontVariantNumeric: 'tabular-nums', display: 'block', marginTop: 4 }}>
                                    {user.employee_id}
                                </Text>
                            )}
                            <Text size="2" style={{ color: 'var(--aero-color-faint, var(--gray-8))', display: 'block', marginTop: 2 }}>
                                {[user?.designation?.title, user?.department?.name].filter(Boolean).join(' · ') || 'Team Member'}
                            </Text>
                        </Box>

                        {/* Date pill — matching mobile shift badge */}
                        <Flex
                            align="center"
                            gap="2"
                            style={{
                                paddingInline: 12, paddingBlock: 6,
                                borderRadius: 999,
                                border: '1px solid var(--aero-surface-border, var(--gray-a4))',
                                background: 'var(--color-background)',
                            }}
                        >
                            <Text size="2" weight="bold">{dayjs().format('dddd')}</Text>
                            <Text size="2" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontVariantNumeric: 'tabular-nums' }}>
                                {dayjs().format('MMMM DD, YYYY')}
                            </Text>
                        </Flex>
                    </Flex>
                </Panel>

                {/* ── Attendance Overview (Stats for current month) ── */}
                <ErrorBoundary>
                    <AttendanceOverview mode="monthly" scope="self" month={currentMonth} />
                </ErrorBoundary>

                {/* ── Main Dashboard Grid ── */}
                <Grid columns={{ initial: '1', lg: '12' }} gap="4">
                    
                    {/* LEFT COLUMN: Punch Status Card & Shift details (6 spans) */}
                    <Box style={{ gridColumn: 'span 6' }}>
                        <Flex direction="column" gap="4">
                            
                            {/* Standalone GPS / Camera Punch Station */}
                            <ErrorBoundary>
                                <PunchStatusCard />
                            </ErrorBoundary>

                            {/* Peer Swap Responses (appears conditionally only if swap requests are pending) */}
                            <ErrorBoundary>
                                <SwapResponses />
                            </ErrorBoundary>

                            {/* Today's Shift/Roster Card */}
                            <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '20px 16px' }}>
                                <Flex align="center" gap="2" mb="3">
                                    <CalendarIcon style={{ color: 'var(--aero-accent, var(--accent-9))', width: 18, height: 18 }} />
                                    <Text size="3" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif` }}>Shift Schedule</Text>
                                </Flex>
                                <Text size="1" style={{ color: 'var(--aero-color-subtle, var(--gray-9))' }}>Today's Shift:</Text>
                                {todaySchedule?.is_working === false ? (
                                    <>
                                        <Heading size="4" mb="2" mt="1">Day off</Heading>
                                        <Badge color="gray" variant="soft">Not scheduled today</Badge>
                                    </>
                                ) : (
                                    <>
                                        <Heading size="4" mb="2" mt="1" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif`, color: 'var(--gray-12)' }}>
                                            {todaySchedule
                                                ? `${todaySchedule.start} – ${todaySchedule.end} (${todaySchedule.label})`
                                                : '—'}
                                        </Heading>
                                        <Badge color={todaySchedule?.source === 'roster' ? 'blue' : 'jade'} variant="soft">
                                            {todaySchedule?.source === 'roster' ? 'Rostered shift' : 'Company default hours'}
                                        </Badge>
                                    </>
                                )}
                            </Panel>

                            {/* Assigned Daily Works & Tasks Widget */}
                            <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '20px 16px' }}>
                                <Flex justify="between" align="center" mb="3">
                                    <Flex align="center" gap="2">
                                        <CheckCircledIcon style={{ color: 'var(--aero-accent, var(--accent-9))', width: 18, height: 18 }} />
                                        <Text size="3" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif` }}>Assigned Daily Tasks</Text>
                                    </Flex>
                                </Flex>

                                <Flex direction="column" gap="2">
                                    <TaskListItem
                                        title="Initial Sub-grade Inspection - Section C"
                                        id="RFI-2026-0422"
                                        status="Pending"
                                        date="Today"
                                    />
                                    <TaskListItem
                                        title="Site Surveying and Alignment Check"
                                        id="RFI-2026-0423"
                                        status="Completed"
                                        date="Yesterday"
                                    />
                                    <TaskListItem
                                        title="Concrete Cube Strength Test Report Upload"
                                        id="RFI-2026-0425"
                                        status="Pending"
                                        date="Tomorrow"
                                    />
                                </Flex>
                            </Panel>

                        </Flex>
                    </Box>

                    {/* RIGHT COLUMN: Leave Balances & Self Requests (6 spans) */}
                    <Box style={{ gridColumn: 'span 6' }}>
                        <Flex direction="column" gap="4">

                            {/* Quick Actions Panel */}
                            <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '20px 16px' }}>
                                <Flex align="center" gap="2" mb="3">
                                    <LightningBoltIcon style={{ color: 'var(--aero-accent, var(--accent-9))', width: 18, height: 18 }} />
                                    <Text size="3" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif` }}>Quick Workspace Actions</Text>
                                </Flex>
                                <Grid columns="2" gap="3">
                                    <Button size="2" variant="soft" color="blue" onClick={() => setSwapOpen(true)} style={{ cursor: 'pointer', borderRadius: 12 }}>
                                        <ShadowIcon style={{ marginRight: 6 }} /> Request Shift Swap
                                    </Button>
                                    <Button size="2" variant="soft" color="orange" onClick={() => setRegOpen(true)} style={{ cursor: 'pointer', borderRadius: 12 }}>
                                        <PlusIcon style={{ marginRight: 6 }} /> Regularize Punch
                                    </Button>
                                    <Button size="2" variant="soft" color="amber" onClick={() => setOtOpen(true)} style={{ cursor: 'pointer', borderRadius: 12 }}>
                                        <PlusIcon style={{ marginRight: 6 }} /> Request Overtime
                                    </Button>
                                    <Button asChild size="2" variant="soft" color="teal" style={{ cursor: 'pointer', borderRadius: 12 }}>
                                        <Link href={route('leaves-employee')}>
                                             <ArrowRightIcon style={{ marginRight: 6 }} /> Apply for Leave
                                        </Link>
                                    </Button>
                                </Grid>
                            </Panel>
                            
                            {/* Leave Balances Widget */}
                            <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '20px 16px' }}>
                                <Flex align="center" gap="2" mb="3">
                                    <BackpackIcon style={{ color: 'var(--aero-accent, var(--accent-9))', width: 18, height: 18 }} />
                                    <Text size="3" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif` }}>Time Off Summary</Text>
                                </Flex>

                                <Grid columns="3" gap="3">
                                    <LeaveTrackerItem
                                        title="Casual Leave"
                                        used={leaveStats?.balances?.casual?.used || 4}
                                        total={leaveStats?.balances?.casual?.total || 12}
                                        color="blue"
                                    />
                                    <LeaveTrackerItem
                                        title="Sick Leave"
                                        used={leaveStats?.balances?.sick?.used || 2}
                                        total={leaveStats?.balances?.sick?.total || 10}
                                        color="teal"
                                    />
                                    <LeaveTrackerItem
                                        title="Earned Leave"
                                        used={leaveStats?.balances?.earned?.used || 5}
                                        total={leaveStats?.balances?.earned?.total || 15}
                                        color="amber"
                                    />
                                </Grid>
                            </Panel>

                            {/* My Requests (Swaps, Regularizations, Overtime) */}
                            <Panel tinted style={{ borderRadius: 16, border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', padding: '20px 16px' }}>
                                <Flex align="center" gap="2" mb="3">
                                    <CalendarIcon style={{ color: 'var(--aero-accent, var(--accent-9))', width: 18, height: 18 }} />
                                    <Text size="3" weight="bold" style={{ fontFamily: `'Space Grotesk', system-ui, sans-serif` }}>My Requests</Text>
                                </Flex>
                                <ErrorBoundary>
                                    <MyRequests />
                                </ErrorBoundary>
                            </Panel>

                        </Flex>
                    </Box>

                </Grid>
            </Box>

            {/* Modals & Forms */}
            <SwapRequestForm open={swapOpen} onOpenChange={setSwapOpen} onSaved={handleSaved} />
            <RegularizationForm open={regOpen} onOpenChange={setRegOpen} onSaved={handleSaved} />
            <OvertimeRequestForm open={otOpen} onOpenChange={setOtOpen} onSaved={handleSaved} />
        </>
    );
}

// Helper components for visual styling
function LeaveTrackerItem({ title, used, total, color }) {
    const pct = Math.min(100, (used / total) * 100);
    return (
        <Box p="3" style={{ background: 'var(--color-background)', border: '1px solid var(--aero-surface-border, rgba(0,0,0,0.06))', borderRadius: 12 }}>
            <Text size="1" weight="bold" style={{ color: 'var(--aero-color-subtle, var(--gray-9))', fontSize: 11 }}>{title}</Text>
            <Heading size="4" mt="1" style={{ color: 'var(--gray-12)', fontFamily: `'Space Grotesk', system-ui, sans-serif`, fontVariantNumeric: 'tabular-nums' }}>
                {total - used} <Text size="1" style={{ color: 'var(--aero-color-faint, var(--gray-8))', fontWeight: 'normal' }}>left</Text>
            </Heading>
            <Progress mt="2" value={pct} color={color} size="1" style={{ borderRadius: 999 }} />
            <Text size="1" style={{ fontSize: 10, display: 'block', color: 'var(--aero-color-faint, var(--gray-8))', marginTop: 4 }}>
                {used} / {total} days used
            </Text>
        </Box>
    );
}

function TaskListItem({ title, id, status, date }) {
    const isCompleted = status === 'Completed';
    return (
        <Flex justify="between" align="center" p="2" style={{ borderBottom: '1px solid var(--dl-border-color, rgba(0,0,0,0.08))' }}>
            <Box>
                <Text size="2" weight="medium" style={{ textDecoration: isCompleted ? 'line-through' : 'none', color: isCompleted ? 'var(--gray-9)' : 'var(--gray-12)' }}>
                    {title}
                </Text>
                <Text size="1" style={{ display: 'block', color: 'var(--aero-color-subtle, var(--gray-8))', fontVariantNumeric: 'tabular-nums' }}>
                    {id} · {date}
                </Text>
            </Box>
            <Badge color={isCompleted ? 'jade' : 'amber'} variant="soft">
                {status}
            </Badge>
        </Flex>
    );
}

EmployeeDashboard.layout = (page) => <App>{page}</App>;
