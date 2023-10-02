import './globals.css';
import '@mantine/core/styles.css';
import { MantineProvider, ColorSchemeScript } from '@mantine/core';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'FiveDays',
  description:
    'AI-powered app that assists teachers with creating and grading assessments, integrated with Google Classroom and utilizing OpenAI API',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang='en'>
      <head>
        <ColorSchemeScript defaultColorScheme='auto' />
      </head>
      <body>
        <MantineProvider>{children}</MantineProvider>
      </body>
    </html>
  );
}
