'use client';
import {
  Flex,
  Button,
  Paper,
  Avatar,
  useMantineColorScheme,
  useComputedColorScheme,
} from '@mantine/core';
import Logo from './Logo';

export default function Navbar() {
  const { setColorScheme } = useMantineColorScheme();
  const computedColorScheme = useComputedColorScheme('light', {
    getInitialValueInEffect: true,
  });

  return (
    <Paper shadow='md' withBorder p='sm'>
      <Flex justify={'space-between'}>
        <Logo />
        <Avatar
          color='cyan'
          radius='xl'
          onClick={() =>
            setColorScheme(computedColorScheme === 'light' ? 'dark' : 'light')
          }
        >
          MK
        </Avatar>
      </Flex>
    </Paper>
  );
}
