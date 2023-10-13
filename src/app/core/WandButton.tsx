import { ActionIcon } from '@mantine/core';
import { IconWand } from '@tabler/icons-react';
import React from 'react';
import { ActionIconProps } from '@mantine/core';

export default function WandButton(pros: ActionIconProps) {
  return (
    <ActionIcon
      variant='gradient'
      size='lg'
      {...pros}
      aria-label='Generate using AI'
      gradient={{ from: 'blue', to: 'teal', deg: 90 }}
    >
      <IconWand size='1.2rem' />
    </ActionIcon>
  );
}
