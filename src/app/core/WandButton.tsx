import { ActionIcon } from '@mantine/core';
import { IconWand } from '@tabler/icons-react';
import React, { ButtonHTMLAttributes } from 'react';
import { ActionIconProps } from '@mantine/core';

export default function WandButton(
  pros: ActionIconProps &
    ButtonHTMLAttributes<any> & {
      iconSize?: string | number;
    } = { iconSize: '1.2rem' }
) {
  return (
    <ActionIcon
      variant='gradient'
      size='lg'
      {...pros}
      aria-label='Generate using AI'
      gradient={{ from: 'blue', to: 'teal', deg: 90 }}
    >
      <IconWand size={pros.iconSize} />
    </ActionIcon>
  );
}
