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
import { Criterion } from '@prisma/client';
import { IconTrash } from '@tabler/icons-react';
import { deleteCriterion } from './actions';
import { useTransition } from 'react';

type Props = {
  criterion: Criterion;
};

export default function CriterionView({ criterion }: Props) {
  const { title, points, description } = criterion;
  const [isPending, startTransition] = useTransition();

  const handleDelete = async () => {
    startTransition(() => deleteCriterion(criterion));
  };

  return (
    <Paper withBorder radius='md' mr={0} mb='md' component='li'>
      <Grid>
        <Grid.Col span={{ base: 12, md: 3 }}>
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
        <Grid.Col span={{ base: 12, md: 9 }}>
          <Flex justify='space-between' p='xs'>
            <div>
              <Title order={3} size={'sm'}>
                {title}
              </Title>
              <Text mt='xs' size={'sm'}>
                {description}
              </Text>
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
