import {
  Button,
  Center,
  Container,
  Flex,
  useMantineColorScheme,
} from '@mantine/core';
import Header from '@/components/layout/Header';

export default function Home() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  return (
    <>
      <Header requireLogin={false} />
      <Container size="lg">
        <Flex h="100vh" justify="center" align="center">
          <Button onClick={() => toggleColorScheme()}>FiveDays</Button>
        </Flex>
      </Container>
    </>
  );
}
