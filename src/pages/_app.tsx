import Head from 'next/head';
import { MantineProvider } from '@mantine/core';
import '@/styles/globals.css';
import { AppProps } from 'next/app';

export default function App(props: AppProps) {
  const { Component, pageProps } = props;

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

      <MantineProvider
        withGlobalStyles
        withNormalizeCSS
        theme={{
          /** Put your mantine theme override here */
          colorScheme: 'light',
        }}
      >
        <Component {...pageProps} />
      </MantineProvider>
    </>
  );
}
