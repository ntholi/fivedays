// Import styles of packages that you've installed.
// All packages except `@mantine/hooks` require styles imports
import '@mantine/core/styles.css';
import { MantineProvider, ColorSchemeScript } from '@mantine/core';
import { Metadata } from 'next';
import Navbar from './core/Navbar';
import { Providers } from './providers';

export const metadata: Metadata = {
  title: 'FiveDays',
  description:
    'AI-powered app that assists teachers with creating and grading assessments, integrated with Google Classroom',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang='en'>
      <head>
        <ColorSchemeScript />
      </head>
      <body>
        <MantineProvider defaultColorScheme='auto'>
          <Providers>
            <Navbar />
            {children}
          </Providers>
        </MantineProvider>
      </body>
    </html>
  );
}
