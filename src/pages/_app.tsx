import NextApp, { AppProps, AppContext } from 'next/app';
import Head from 'next/head';
import {
  ColorScheme,
  ColorSchemeProvider,
  MantineProvider,
} from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { useState } from 'react';
import { getCookie, setCookie } from 'cookies-next';
import { ModalsProvider } from '@mantine/modals';
import '@/styles/globals.css';
import { SessionProvider } from 'next-auth/react';
import { Session } from 'next-auth';

export default function App(
  props: AppProps & { colorScheme: ColorScheme } & {
    session: Session;
  }
) {
  const { Component, pageProps } = props;
  const [colorScheme, setColorScheme] = useState<ColorScheme>(
    props.colorScheme
  );

  const toggleColorScheme = (value?: ColorScheme) => {
    const nextColorScheme =
      value || (colorScheme === 'dark' ? 'light' : 'dark');
    setColorScheme(nextColorScheme);
    setCookie('mantine-color-scheme', nextColorScheme, {
      maxAge: 60 * 60 * 24 * 30,
    });
  };

  return (
    <>
      <Head>
        <title>FiveDays</title>
        <meta
          name='description'
          content='FiveDays: An AI-powered app that assists lecturers with creating and grading assessments, integrated with Google Classroom and utilizing OpenAI API.'
        />
        <meta name='author' content='Ntholi Nkhatho' />
        <meta name='keywords' content='FiveDays, OpenAI, Google Classroom' />
        <meta
          name='viewport'
          content='minimum-scale=1, initial-scale=1, width=device-width'
        />
      </Head>

      <ColorSchemeProvider
        colorScheme={colorScheme}
        toggleColorScheme={toggleColorScheme}
      >
        <MantineProvider
          theme={{ colorScheme }}
          withGlobalStyles
          withNormalizeCSS
        >
          <Notifications />
          <ModalsProvider>
            <SessionProvider session={props.session}>
              <Component {...pageProps} />
            </SessionProvider>
          </ModalsProvider>
        </MantineProvider>
      </ColorSchemeProvider>
    </>
  );
}

App.getInitialProps = async (appContext: AppContext) => {
  const appProps = await NextApp.getInitialProps(appContext);
  return {
    ...appProps,
    colorScheme: getCookie('mantine-color-scheme', appContext.ctx) || 'dark',
  };
};
