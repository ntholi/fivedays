import { Container } from '@mantine/core';
import Header from '@/components/layout/Header';
import Hero from '@/components/home/Hero';

export default function Home() {
  return (
    <>
      <Header loginRequired={false} />
      <Hero />
    </>
  );
}
