import React from 'react';
import StatsCards from '@/Components/StatsCards';
import { PersonIcon, HomeIcon, LayersIcon, SewingPinIcon } from '@radix-ui/react-icons';

export default function OrganizationOverview({ stats }) {
    if (!stats) return null;

    const statItems = [
        {
            key: 'employees',
            title: 'Total Employees',
            value: stats.total_employees ?? 0,
            icon: PersonIcon,
            color: 'blue',
            description: 'Active personnel count',
        },
        {
            key: 'departments',
            title: 'Departments',
            value: stats.total_departments ?? 0,
            icon: HomeIcon,
            color: 'indigo',
            description: 'Organizational units',
        },
        {
            key: 'designations',
            title: 'Designations',
            value: stats.total_designations ?? 0,
            icon: LayersIcon,
            color: 'violet',
            description: 'Job titles & roles',
        },
        {
            key: 'locations',
            title: 'Work Locations',
            value: stats.total_locations ?? 0,
            icon: SewingPinIcon,
            color: 'teal',
            description: 'Plazas, offices & camps',
        },
    ];

    return <StatsCards stats={statItems} columns={{ initial: '1', sm: '2', md: '4' }} mb="4" />;
}
