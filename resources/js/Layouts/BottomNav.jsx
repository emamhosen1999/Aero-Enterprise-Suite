import { Panel } from '@/Components/ui/Panel';
import React, { useState, useCallback, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { Box, Flex, IconButton, Text, Tooltip } from '@radix-ui/themes';
import {
  HomeIcon,
  PersonIcon,
  ClockIcon,
  FileTextIcon,
  GearIcon,
  DotsHorizontalIcon,
} from '@radix-ui/react-icons';

const BottomNav = ({ toggleThemeDrawer }) => {
  const { url, auth } = usePage().props;
  const [activeTab, setActiveTab] = useState('dashboard');

  useEffect(() => {
    if (url.includes('/attendance-employee') || url.includes('/attendance')) setActiveTab('attendance');
    else if (url.includes('/leaves-employee')) setActiveTab('leaves');
    else if (url.includes('/petty-cash')) setActiveTab('petty-cash');
    else if (url.includes('/dashboard')) setActiveTab('dashboard');
    else if (url.includes('/profile/')) setActiveTab('profile');
    else setActiveTab('dashboard');
  }, [url, auth?.user?.id]);

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: HomeIcon, href: '/dashboard' },
    { id: 'attendance', label: 'Attendance', icon: ClockIcon, href: '/attendance' },
    { id: 'leaves', label: 'Leaves', icon: FileTextIcon, href: '/leaves-employee' },
    { id: 'petty-cash', label: 'Petty Cash', icon: DotsHorizontalIcon, href: '/petty-cash' },
    { id: 'profile', label: 'Profile', icon: PersonIcon, href: `/profile/${auth?.user?.id}` },
    { id: 'theme', label: 'Theme', icon: GearIcon, action: 'theme' },
  ];

  const handleNav = useCallback((item) => {
    if (item.action === 'theme') { toggleThemeDrawer?.(); return; }
    if (item.href) {
      setActiveTab(item.id);
      router.visit(item.href, { method: 'get', preserveState: true, preserveScroll: true });
    }
  }, [toggleThemeDrawer]);

  return (
    <Panel
      as="nav"
      style={{
        position: 'fixed',
        bottom: 0,
        left: 0,
        right: 0,
        height: 60,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-around',
        borderTop: '1px solid var(--dl-border-color, rgba(0,0,0,0.08))',
        zIndex: 200,
        paddingBottom: 'env(safe-area-inset-bottom)',
        borderRadius: 0,
        background: 'var(--color-background)',
      }}
      aria-label="Bottom navigation"
    >
      {navItems.map(item => {
        const isActive = activeTab === item.id;
        const Icon = item.icon;
        return (
          <Tooltip key={item.id} content={item.label}>
            <Flex
              direction="column"
              align="center"
              gap="1"
              style={{
                cursor: 'pointer',
                padding: '6px 10px',
                borderRadius: 'var(--radius-2)',
                background: 'transparent',
                color: isActive ? 'var(--aero-accent, var(--accent-9))' : 'var(--aero-color-faint, var(--gray-8))',
                transition: 'color 140ms ease',
                minWidth: 52,
                position: 'relative',
              }}
              onClick={() => handleNav(item)}
              role="button"
              tabIndex={0}
              aria-label={item.label}
              aria-current={isActive ? 'page' : undefined}
              onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') handleNav(item); }}
            >
              <Icon style={{ width: 20, height: 20 }} />
              <Text
                size="1"
                weight={isActive ? 'bold' : 'regular'}
                style={{ fontSize: 11, lineHeight: 1, color: isActive ? 'var(--gray-12)' : 'var(--aero-color-faint, var(--gray-8))' }}
              >
                {item.label}
              </Text>
            </Flex>
          </Tooltip>
        );
      })}
    </Panel>
  );
};

export default BottomNav;
