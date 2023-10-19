import React from 'react';
import RubricItem from './RubricItem';
import prisma from '@/lib/db';
import { List } from '@mantine/core';

type Props = {
  courseId: string;
  courseWorkId: string;
};

export default async function RubricList({ courseId, courseWorkId }: Props) {
  const rubricItems = await prisma.rubricItem.findMany({
    where: {
      courseId,
      courseWorkId,
    },
    orderBy: {
      createdAt: 'desc',
    },
  });

  return (
    <List mt='lg'>
      {rubricItems.map((it) => (
        <RubricItem key={it.id} rubricItem={it} />
      ))}
    </List>
  );
}
