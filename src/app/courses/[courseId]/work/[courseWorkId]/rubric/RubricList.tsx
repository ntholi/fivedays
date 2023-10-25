import React from 'react';
import prisma from '@/lib/db';
import { List } from '@mantine/core';
import CriterionView from './CriterionView';

type Props = {
  courseId: string;
  courseWorkId: string;
};

export default async function Rubric({ courseId, courseWorkId }: Props) {
  const criteria = await prisma.criterion.findMany({
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
      {criteria.map((it) => (
        <CriterionView key={it.id} criterion={it} />
      ))}
    </List>
  );
}
