import { Button, Center, Flex, useMantineColorScheme } from '@mantine/core';
import { useSession } from 'next-auth/react';
import Layout from '../../components/layout/Layout';

export default function Home() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  return (
    <>
      <Layout>
        <Flex h='100vh' justify='center' align='center'>
          <Button onClick={() => toggleColorScheme()}>FiveDays</Button>
        </Flex>
      </Layout>
    </>
  );
}
