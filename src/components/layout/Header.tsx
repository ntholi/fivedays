import React from 'react';
import {
  Flex,
  Header as MantineHeader,
  Avatar,
  Menu,
  UnstyledButton,
} from '@mantine/core';
import Logo from './Logo';
import { useSession } from 'next-auth/react';
import { signOut } from 'next-auth/react';

export default function Header() {
  const { data: session, status } = useSession({
    required: true,
  });
  return (
    <MantineHeader height={60}>
      <Flex justify='space-between' align='center' h='100%' pr='lg'>
        <Logo />

        <Menu shadow='md' width={150}>
          <Menu.Target>
            <UnstyledButton>
              <Avatar src={session?.user?.image} radius='xl' size='sm' />
            </UnstyledButton>
          </Menu.Target>

          <Menu.Dropdown>
            <Menu.Item onClick={() => signOut()}>Sign Out</Menu.Item>
          </Menu.Dropdown>
        </Menu>
      </Flex>
    </MantineHeader>
  );
}
