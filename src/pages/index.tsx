import { Button, Center, Flex, useMantineColorScheme } from '@mantine/core';

export default function Home() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
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
