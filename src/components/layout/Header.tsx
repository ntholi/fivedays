import React from 'react';
import {
  Flex,
  Header as MantineHeader,
  Avatar,
  Menu,
  UnstyledButton,
  useMantineColorScheme,
  Group,
  Button,
} from '@mantine/core';
import {
  IconSun,
  IconMoonStars,
  IconLogin,
  IconLogout,
} from '@tabler/icons-react';
import Logo from './Logo';
import { useSession } from 'next-auth/react';
import { signOut, signIn } from 'next-auth/react';
import Link from 'next/link';
import { IconSchool } from '@tabler/icons-react';

type Props = {
  loginRequired?: boolean;
};

export default function Header({ loginRequired }: Props) {
  const { data: session, status } = useSession({
    required: loginRequired ?? true,
  });
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  return (
    <MantineHeader height={60}>
      <Flex justify="space-between" align="center" h="100%" pr="lg">
        <Group>
          <Link href="/" style={{ textDecoration: 'none', color: 'inherit' }}>
            <Logo />
          </Link>
          <Link href="/classes">
            <Button leftIcon={<IconSchool />} variant="default" color="dark">
              Classes
            </Button>
          </Link>
        </Group>

        <Menu shadow="md" width={150}>
          <Menu.Target>
            <UnstyledButton>
              <Avatar src={session?.user?.image} radius="xl" size="sm" />
            </UnstyledButton>
          </Menu.Target>

          <Menu.Dropdown>
            <Menu.Item
              onClick={() => toggleColorScheme()}
              icon={
                colorScheme === 'dark' ? (
                  <IconSun size={14} />
                ) : (
                  <IconMoonStars size={14} />
                )
              }
            >
              {colorScheme === 'dark' ? 'Light Mode' : 'Dark Mode'}
            </Menu.Item>
            <Menu.Divider />
            {status === 'authenticated' ? (
              <Menu.Item icon={<IconLogin />} onClick={() => signOut()}>
                Sign Out
              </Menu.Item>
            ) : (
              <Menu.Item icon={<IconLogout />} onClick={() => signIn()}>
                Sign In
              </Menu.Item>
            )}
          </Menu.Dropdown>
        </Menu>
      </Flex>
    </MantineHeader>
  );
}
