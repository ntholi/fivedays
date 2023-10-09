'use client';
import {
  Paper,
  Title,
  Text,
  Flex,
  Grid,
  Stack,
  ActionIcon,
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
    <Paper withBorder radius='md' mr={0} mb='md' component='li'>
      <Grid>
        <Grid.Col span={{ base: 12, md: 2 }}>
          <Stack
            h='100%'
            bg='var(--mantine-color-blue-light)'
            align='center'
            justify='center'
            gap={0}
            p='md'
          >
            <Text size='lg'>{points}</Text>
            <Text size='xs' c='dimmed'>
              Points
            </Text>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 10 }}>
          <Flex justify='space-between' p='md'>
            <div>
              <Title order={3}>{title}</Title>
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
        </Grid.Col>
      </Grid>
    </Paper>
  );
}
