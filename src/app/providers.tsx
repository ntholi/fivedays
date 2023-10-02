'use client';
import { SessionProvider } from 'next-auth/react';
import { AppProgressBar as ProgressBar } from 'next-nprogress-bar';

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <SessionProvider>
      {children}
      <ProgressBar
        height='3px'
        color='#2196F3'
        options={{ showSpinner: false }}
        shallowRouting
      />
    </SessionProvider>
  );
}
