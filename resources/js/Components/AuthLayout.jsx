import { Panel } from '@/Components/ui/Panel';
import React from 'react';

import { useRadixTheme } from '@/Contexts/RadixThemeContext';

import logo from '../../../public/assets/images/logo.png';

const AuthLayout = ({ children, title, subtitle }) => {
    const { settings: themeSettings } = useRadixTheme();

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '16px',
                position: 'relative',
                overflow: 'hidden',
                fontFamily: `'Inter', system-ui, -apple-system, sans-serif`,
                backgroundColor: 'var(--color-background)',
            }}
        >
            <div style={{ width: '100%', maxWidth: 420, padding: '0 4px' }}>
                <div style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    minHeight: '80vh',
                    paddingTop: 16,
                    paddingBottom: 16,
                }}>
                    {/* Auth Form Card — clean, cardless, matching mobile */}
                    <div style={{ width: '100%', maxWidth: 420 }}>
                        <Panel
                            tinted
                            style={{
                                padding: '32px 24px',
                                position: 'relative',
                                overflow: 'visible',
                                width: '100%',
                                borderRadius: 20,
                            }}
                        >
                            {/* Logo at top of form card */}
                            <div style={{ textAlign: 'center', marginBottom: 24 }}>
                                <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 16 }}>
                                    <div style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <img
                                            src={logo}
                                            alt="Logo"
                                            style={{ width: 120, height: 120, objectFit: 'contain' }}
                                            onError={(e) => {
                                                e.target.style.display = 'none';
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Header — matching mobile's clean typography */}
                            <div style={{ marginBottom: 24, textAlign: 'center' }}>
                                <h1
                                    style={{
                                        fontSize: 26,
                                        fontWeight: 900,
                                        marginBottom: 8,
                                        letterSpacing: '-0.5px',
                                        lineHeight: 1.2,
                                        fontFamily: `'Space Grotesk', system-ui, sans-serif`,
                                        color: 'var(--gray-12)',
                                        margin: '0 0 8px 0',
                                    }}
                                >
                                    {title}
                                </h1>
                                {subtitle && (
                                    <p
                                        style={{
                                            fontSize: 13,
                                            lineHeight: 1.5,
                                            color: 'var(--aero-color-subtle, var(--gray-9))',
                                            fontFamily: `'Inter', system-ui, sans-serif`,
                                            margin: 0,
                                        }}
                                    >
                                        {subtitle}
                                    </p>
                                )}
                            </div>

                            {/* Form Content */}
                            <div>{children}</div>
                        </Panel>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AuthLayout;
