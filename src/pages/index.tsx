import { Button, Center, Flex, useMantineColorScheme } from '@mantine/core';
import { useSession } from 'next-auth/react';

export default function Home() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  const { data: session, status } = useSession({
    required: true,
  });
  return (
    <>
      <main>
        <Flex h='100vh' justify='center' align='center'>
          <Button onClick={() => toggleColorScheme()}>FiveDays</Button>
        </Flex>
      </main>
    </>
  );
}
