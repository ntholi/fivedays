import React from 'react';
import { Flex, Header as MantineHeader, Avatar } from '@mantine/core';
import Logo from './Logo';
import { useSession } from 'next-auth/react';
import { Text } from '@mantine/core';

export default function Header() {
  const { data: session, status } = useSession({
    required: true,
  });
  return (
    <MantineHeader height={60}>
      <Flex justify='space-between' align='center' h='100%' pr='lg'>
        <Logo />
        <Avatar src={session?.user?.image} radius='xl' />
      </Flex>
    </MantineHeader>
  );
}
