import React from 'react';
import { Container, List } from '@mantine/core';
import RubricForm from './RubricForm';
import prisma from '@/lib/db';
import RubricItem from './RubricItem';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
  };
};

export default async function RubricPage({
  params: { courseId, courseWorkId },
}: Props) {
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
    <Container mt='xl'>
      <RubricForm courseId={courseId} courseWorkId={courseWorkId} />

      <form>
        <List mt='xl'>
          {rubricItems.map((it) => (
            <RubricItem key={it.id} rubricItem={it} />
          ))}
        </List>
      </form>
    </Container>
  );
}
