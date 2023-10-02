import React from 'react';
import { Text } from '@mantine/core';

export default function Logo({ size }: { size?: number }) {
  return (
    <div>
      <Text>
        <Text component='span'>Five</Text>
        Days
      </Text>
    </div>
  );
}
