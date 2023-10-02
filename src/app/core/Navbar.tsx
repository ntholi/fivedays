import { Flex, Button, Paper, Avatar } from '@mantine/core';
import Logo from './Logo';

export default function Navbar() {
  return (
    <Paper shadow='md' withBorder p='sm'>
      <Flex justify={'space-between'}>
        <Logo />
        <Avatar color='cyan' radius='xl'>
          MK
        </Avatar>
      </Flex>
    </Paper>
  );
}
