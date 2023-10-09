'use client';
import {
  Paper,
  Title,
  Text,
  List,
  ActionIcon,
  Flex,
  LoadingOverlay,
} from '@mantine/core';
import { RubricItem } from '@prisma/client';
import { IconTrash } from '@tabler/icons-react';
import { deleteRubricItem } from './actions';
import { useTransition } from 'react';

type Props = {
  rubricItem: RubricItem;
};

export default function RubricItem({ rubricItem }: Props) {
  const { title, points, description } = rubricItem;
  const [isPending, startTransition] = useTransition();

  const handleDelete = async () => {
    startTransition(() => deleteRubricItem(rubricItem));
  };

  return (
    <Paper withBorder radius='md' mr={0} mb='md' p='md' component='li'>
      <Flex justify='space-between'>
        <div>
          <Title order={3}>{title}</Title>
          <Text c='dimmed' size='xs'>
            {points} Points
          </Text>
          <Text mt='xs'>{description}</Text>
        </div>
        <ActionIcon
          onClick={handleDelete}
          variant='outline'
          color='red'
          aria-label='Delete'
          loading={isPending}
        >
          <IconTrash size='0.7rem' />
        </ActionIcon>
      </Flex>
    </Paper>
  );
}
