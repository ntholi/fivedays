import React from 'react';
import { Text } from '@mantine/core';
import Link from 'next/link';

export default function Logo({ size }: { size?: number }) {
  return (
    <Link href={'/'} style={{ textDecoration: 'none', color: 'inherit' }}>
      <Text>
        <Text component='span'>Five</Text>
        Days
      </Text>
    </Link>
  );
}
