import React, { useState, useEffect } from 'react';
import { Box } from '@radix-ui/themes';
import { CheckCircledIcon, CrossCircledIcon, ExclamationTriangleIcon, CalendarIcon } from '@radix-ui/react-icons';
import axios from 'axios';
import StatsCards from '@/Components/StatsCards';

export default function AttendanceOverview({ date, mode = 'daily', month, scope = 'all' }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const isMonthly = mode === 'monthly';
    const isSelf = scope === 'self';

    useEffect(() => {
        let isMounted = true;
        setLoading(true);

        const request = isMonthly
            ? (() => {
                const [year, m] = (month || '').split('-');
                const params = {
                    currentMonth: parseInt(m, 10) || (new Date().getMonth() + 1),
                    currentYear: parseInt(year, 10) || new Date().getFullYear(),
                };
                // self scope → the current user's own monthly stats; otherwise org-wide
                return isSelf
                    ? axios.get('/attendance/my-monthly-stats', { params })
                    : axios.get(route('attendance.monthlyStats', params));
            })()
            : axios.get(route('attendance.dailyOverview', { date }));

        request
            .then(res => {
                if (!isMounted) return;
                if (isMonthly) {
                    const a = res.data?.stats?.attendance || res.data?.data?.attendance || {};
                    setStats({ present: a.present, absent: a.absent, late: a.lateArrivals, on_leave: a.leaves });
                } else {
                    setStats(res.data);
                }
                setLoading(false);
            })
            .catch(err => {
                console.error('Failed to fetch attendance overview:', err);
                if (isMounted) setLoading(false);
            });

        return () => { isMounted = false; };
    }, [date, mode, month, isMonthly, isSelf]);

    const statItems = [
        { key: 'present', title: 'Present', value: stats?.present ?? 0, icon: <CheckCircledIcon />, color: 'green', isLoading: loading },
        { key: 'absent', title: 'Absent', value: stats?.absent ?? 0, icon: <CrossCircledIcon />, color: 'red', isLoading: loading },
        { key: 'late', title: 'Late Arrivals', value: stats?.late ?? 0, icon: <ExclamationTriangleIcon />, color: 'amber', isLoading: loading },
        { key: 'leave', title: 'On Leave', value: stats?.on_leave ?? 0, icon: <CalendarIcon />, color: 'cyan', isLoading: loading },
    ];

    return (
        <Box mb="5">
            <StatsCards stats={statItems} columns={{ initial: '1', sm: '2', md: '4' }} />
        </Box>
    );
}
