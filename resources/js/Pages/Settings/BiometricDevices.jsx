import React, { lazy, Suspense } from 'react';
import { Head } from '@inertiajs/react';
import App from '@/Layouts/App.jsx';
import { Box, Skeleton } from '@radix-ui/themes';
import ErrorBoundary from '@/Components/ErrorBoundary/ErrorBoundary';

const BiometricPanel = lazy(() => import('@/Components/AdminUnified/BiometricPanel'));

const BiometricDevices = ({ title, devices = [], employees = [] }) => {
    return (
        <>
            <Head title={title} />
            <Box p="4">
                <ErrorBoundary>
                    <Suspense fallback={<Skeleton height="400px" />}>
                        <BiometricPanel
                            initialDevices={devices}
                            employees={employees}
                            isActive={true}
                        />
                    </Suspense>
                </ErrorBoundary>
            </Box>
        </>
    );
};

BiometricDevices.layout = (page) => <App>{page}</App>;

export default BiometricDevices;
