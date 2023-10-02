import { Button, Stack, Title } from '@mantine/core';

export default function Home() {
  return (
    <main className='flex h-screen justify-center items-center'>
      <Stack>
        <Title className='font-normal'>Hello World</Title>
        <Button>Click Me</Button>
      </Stack>
    </main>
  );
}
