import { Button, Stack, Title } from '@mantine/core';
import Link from 'next/link';

export default function Home() {
  return (
    <main>
      <Stack>
        <Title className='font-normal'>Hello World</Title>
        <Button component={Link} href={'/courses'}>
          Courses
        </Button>
      </Stack>
    </main>
  );
}
