import React from 'react';
import { Container } from '@mantine/core';
import RubricForm from './RubricForm';
import prisma from '@/lib/db';

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
  });

  return (
    <Container mt='xl'>
      <RubricForm />

      <ul>
        {rubricItems.map((it) => (
          <li key={it.id}>{it.title}</li>
        ))}
      </ul>
    </Container>
  );
}
