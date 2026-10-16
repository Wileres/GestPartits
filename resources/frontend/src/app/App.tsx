import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { RouterProvider } from 'react-router';
import { Toaster } from '@/components/ui/sonner';
import { createQueryClient } from './queryClient';
import { createAppRouter } from './router';

export function App({ queryClient }: { queryClient?: QueryClient }) {
    const [client] = useState(() => queryClient ?? createQueryClient());
    const [router] = useState(createAppRouter);

    return (
        <QueryClientProvider client={client}>
            <RouterProvider router={router} />
            <Toaster position="top-right" richColors closeButton />
        </QueryClientProvider>
    );
}
